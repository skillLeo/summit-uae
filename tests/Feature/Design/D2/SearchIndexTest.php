<?php

namespace Tests\Feature\Design\D2;

use App\Console\Commands\SearchIndex;
use App\Content\ContentRepository;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Design 2 command palette: the generated index (php artisan summit:search-index, upgrade spec §12 C12),
 * the palette partial on every page, and the proposed copy of the search area (faq, palette, hours).
 */
final class SearchIndexTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['en', 'ar', 'ru'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    private function d2(): array
    {
        return ['summit_design' => '2', 'summit_qa' => '1'];
    }

    /** @return array{version: int, locale: string, items: list<array<string, string>>} */
    private function index(string $locale): array
    {
        return json_decode(File::get(public_path("search/{$locale}.json")), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_committed_index_files_are_current(): void
    {
        // Fails when the DOCX content changed without `php artisan summit:search-index`.
        $this->artisan('summit:search-index', ['--check' => true])->assertExitCode(0);
    }

    public function test_check_reports_missing_and_stale_files(): void
    {
        $dir = storage_path('framework/testing/search-index-'.bin2hex(random_bytes(4)));
        try {
            $this->artisan('summit:search-index', ['--check' => true, '--dir' => $dir])->assertExitCode(1);
            $this->artisan('summit:search-index', ['--dir' => $dir])->assertExitCode(0);
            foreach (self::LOCALES as $locale) {
                $this->assertFileEquals(public_path("search/{$locale}.json"), "{$dir}/{$locale}.json");
            }
            $this->artisan('summit:search-index', ['--check' => true, '--dir' => $dir])->assertExitCode(0);

            File::put("{$dir}/ru.json", str_replace('"version":1', '"version":0', File::get("{$dir}/ru.json")));
            $this->artisan('summit:search-index', ['--check' => true, '--dir' => $dir])->assertExitCode(1);
        } finally {
            File::deleteDirectory($dir);
        }
    }

    public function test_every_service_guide_faq_and_case_study_is_indexed_in_order(): void
    {
        $content = app(ContentRepository::class);
        foreach (self::LOCALES as $locale) {
            $data = $this->index($locale);
            $this->assertSame(SearchIndex::VERSION, $data['version']);
            $this->assertSame($locale, $data['locale']);
            $items = $data['items'];

            $types = array_count_values(array_column($items, 'type'));
            $this->assertSame(['service' => 11, 'guide' => 9, 'faq' => 74, 'case' => 6], $types, $locale);
            // Grouped in a fixed order: services, guides, FAQs, case studies.
            $this->assertSame(['service', 'guide', 'faq', 'case'], array_values(array_unique(array_column($items, 'type'))));

            $ids = array_column($items, 'id');
            $this->assertSame($ids, array_values(array_unique($ids)), "{$locale}: ids are unique");
            $this->assertSame(['S01', 'S02'], array_slice($ids, 0, 2));
            $this->assertContains('faq-74', $ids);
            $this->assertContains('case-06', $ids);

            foreach ($items as $item) {
                $this->assertSame(['id', 'type', 'title', 'summary', 'url', 'text'], array_keys($item));
                $this->assertNotSame('', $item['title'], "{$locale} {$item['id']} has a title");
                $this->assertNotSame('', $item['summary'], "{$locale} {$item['id']} has a summary");
                $this->assertStringStartsWith("/{$locale}/", $item['url'], 'site-relative URL');
                $this->assertStringNotContainsString('⟦', $item['title'].$item['summary'].$item['text'], 'no missing translation');
            }

            // Approved copy exactly as stored, and the Design 1 URL scheme (SiteUrl).
            $byId = array_column($items, null, 'id');
            $this->assertSame($content->service('S06', $locale)['title'], $byId['S06']['title']);
            $this->assertSame("/{$locale}/practice-areas/labor-law/", $byId['S06']['url']);
            $this->assertSame($content->guide('G01', $locale)['card_title'], $byId['G01']['title']);
            $this->assertSame("/{$locale}/resources/#unpaid-salary", $byId['G01']['url']);
            $faq = collect($content->faqs($locale))->firstWhere('anchor', 'faq-42');
            $this->assertSame($faq['question'], $byId['faq-42']['title']);
            $this->assertSame($faq['answer'][0]['text'], $byId['faq-42']['summary']);
            $this->assertSame("/{$locale}/faq/#faq-42", $byId['faq-42']['url']);
            $this->assertSame("/{$locale}/case-studies/#case-04", $byId['case-04']['url']);
        }
    }

    public function test_index_is_deterministic_and_within_budget(): void
    {
        $content = app(ContentRepository::class);
        foreach (self::LOCALES as $locale) {
            $a = SearchIndex::encode(SearchIndex::documents($content, $locale), $locale);
            $content->flush();
            $b = SearchIndex::encode(SearchIndex::documents($content, $locale), $locale);
            $this->assertSame($a, $b, $locale);
            $this->assertStringEndsWith("]}\n", $a);
            // Spec §5.5: at most 150 KB per language, gzip.
            $this->assertLessThan(150 * 1024, strlen((string) gzencode($a, 9)), $locale);
        }
    }

    public function test_palette_and_copy_blocks_are_on_every_design_two_page(): void
    {
        foreach (['/en/', '/ar/faq/', '/ru/practice-areas/labor-law/', '/en/about/'] as $path) {
            $html = $this->withUnencryptedCookies($this->d2())->get($path)->assertOk()->getContent();
            $locale = explode('/', $path)[1];
            $this->assertSame(1, substr_count($html, 'data-modal="palette"'), $path);
            $this->assertSame(1, substr_count($html, 'id="d2-palette-copy"'), $path);
            $this->assertSame(1, substr_count($html, 'id="d2-hours-copy"'), $path);
            $this->assertMatchesRegularExpression('#data-index="/search/'.$locale.'\.json\?v=[0-9a-f]{12}"#', $html, $path);
            $this->assertStringContainsString('role="combobox"', $html);
            $this->assertStringContainsString('aria-controls="palette-list"', $html);
        }
    }

    public function test_palette_empty_state_lists_six_services_with_approved_titles(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $html = $this->withUnencryptedCookies($this->d2())->get("/{$locale}/")->assertOk()->getContent();
            preg_match('#<div class="palette__group" role="group" aria-labelledby="palette-g-featured" data-palette-featured>(.*?)</div>\s*<a class="palette-option palette-option--describe#s', $html, $m);
            $this->assertNotEmpty($m, $locale);
            $this->assertSame(7, substr_count($m[1], 'role="option"'), "{$locale}: six services + View All Practice Areas");
            foreach (array_slice(content()->services($locale), 0, 6) as $service) {
                $this->assertStringContainsString('id="palette-o-'.$service['code'].'"', $m[1]);
            }
            $this->assertStringContainsString(e(ui('cta.view_all_practice_areas', $locale)), $m[1]);
        }
    }

    /**
     * Design 2.1, Phase 1 (brief §3.6; D03, D04, D09): the palette's options are text rows (no icon tiles, no
     * arrows, no icon template for the results), the type chips carry no icon, and nothing is cut: the empty
     * state lists the six practice areas by their full approved titles (their summaries come with the results,
     * in full; palette.css has no ellipsis or line clamp).
     */
    public function test_design_2_1_palette_options_are_text_rows_without_icons_or_arrows(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $html = $this->withUnencryptedCookies($this->d2())->get("/{$locale}/")->assertOk()->getContent();
            $this->assertSame(1, preg_match('#<dialog[^>]*data-modal="palette".*?</dialog>#s', $html, $m), $locale);
            $palette = $m[0];
            foreach (['palette-option__icon', 'palette-option__go', 'data-palette-icons', 'chip__icon', 'data-slot="icon"'] as $gone) {
                $this->assertStringNotContainsString($gone, $palette, "{$locale}: {$gone}");
            }
            // Round 1 (brief 3.5): the group headings are plain text in the UI-label role, never chips (a chip is a
            // control; these labels are not filters). The featured group and the four result templates.
            $this->assertStringNotContainsString('class="chip', $palette, "{$locale}: no chip in the palette");
            $this->assertStringContainsString('<div class="palette__group-label" id="palette-g-featured">'.e(ui('nav.practice_areas', $locale)).'</div>', $palette);
            foreach (['nav.practice_areas', 'nav.resources', 'nav.faq', 'nav.case_studies'] as $key) {
                $this->assertStringContainsString('<div class="palette__group-label">'.e(ui($key, $locale)).'</div>', $palette, "{$locale}: {$key} heading");
            }
            preg_match_all('/data-icon="([^"]+)"/', $palette, $icons);
            $this->assertSame([], array_values(array_diff(array_unique($icons[1]), ['search', 'close', 'check', 'warning', 'error'])), "{$locale}: functional icons only");
            foreach (array_slice(content()->services($locale), 0, 6) as $service) {
                $this->assertMatchesRegularExpression('#id="palette-o-'.$service['code'].'"[^>]*>\s*<span class="palette-option__text">\s*<span class="palette-option__title">'.preg_quote(e($service['title']), '#').'</span>\s*</span>#', $palette, "{$locale}: {$service['code']} by its full title");
            }
            $this->assertStringContainsString('<template data-palette-option-template>', $palette);
        }
        $css = File::get(resource_path('css/d2/components/palette.css'));
        $this->assertStringNotContainsString('text-overflow', $css);
        $this->assertStringNotContainsString('line-clamp', $css);
        $this->assertMatchesRegularExpression('/\.palette__group-label \{[^}]*color: var\(--ink-3\);\s*font-size: var\(--fs-ui\);\s*font-weight: var\(--fw-medium\);\s*line-height: var\(--lh-ui\);/', $css, 'group headings in the UI-label role');

        // No proposed palette string ends with an ellipsis (brief §3.6 rule 8, lint rule 5; the kit's "Loading" too).
        foreach (self::LOCALES as $locale) {
            foreach (Arr::dot(require lang_path("{$locale}/proposed-d2/palette.php")) as $key => $line) {
                $this->assertDoesNotMatchRegularExpression('/(…|\.\.\.)\s*$/u', (string) $line, "{$locale}: palette.{$key}");
            }
        }
    }

    /**
     * Round 1 (home-b; brief 3.6 rule 8 in spirit): the palette's placeholder was cut on phones (Russian
     * "Поиск по услугам, материалам…", Arabic too). The hint is drawn inside the field's box as text that wraps (the
     * field's description, proposed copy), shown while the field is empty, with no placeholder attribute left to
     * cut; the close button stays level with the field's first line; the footer's keyboard hints keep their class.
     */
    public function test_round_1_palette_placeholder_wraps_instead_of_being_cut(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $html = $this->withUnencryptedCookies($this->d2())->get("/{$locale}/")->assertOk()->getContent();
            $this->assertSame(1, preg_match('#<dialog[^>]*data-modal="palette".*?</dialog>#s', $html, $m), $locale);
            $hint = e(d2p('shell.search_placeholder'));
            $this->assertMatchesRegularExpression('#<div class="palette__box" data-palette-box>\s*<span class="control[^"]*palette__field"\s*>.*?<input class="input"(?=[^>]*\sid="palette-input")(?=[^>]*\saria-describedby="palette-hint")[^>]*>.*?</span>\s*<span class="palette__placeholder" id="palette-hint" data-copy-status="proposed">'.preg_quote($hint, '#').'</span>\s*</div>#s', $m[0], "{$locale}: the hint drawn in the field");
            $this->assertDoesNotMatchRegularExpression('#<input class="input"(?=[^>]*\sid="palette-input")[^>]*\splaceholder=#', $m[0], "{$locale}: no native placeholder to cut");
        }
        $css = File::get(resource_path('css/d2/components/palette.css'));
        $this->assertMatchesRegularExpression('/\.palette__placeholder \{[^}]*display: block;[^}]*line-height: calc\(var\(--field-h\) - 2 \* var\(--line-w\) - 2 \* var\(--hint-pad\)\);[^}]*text-wrap: balance;/s', $css);
        $this->assertMatchesRegularExpression('/\.palette__box\.is-filled \.palette__placeholder \{\s*display: none;/', $css);
        $this->assertMatchesRegularExpression('/\.palette \.modal__head \{\s*align-items: flex-start;/', $css);
        $this->assertMatchesRegularExpression('/\.palette__hint \{\s*display: inline-flex;/', $css, 'the footer hints keep their own rule');
    }

    public function test_hours_copy_block_carries_config_and_plural_forms(): void
    {
        foreach (self::LOCALES as $locale) {
            $html = $this->withUnencryptedCookies($this->d2())->get("/{$locale}/")->assertOk()->getContent();
            preg_match('#<script type="application/json" id="d2-hours-copy">(.*?)</script>#s', $html, $m);
            $data = json_decode($m[1] ?? 'null', true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(['timeZone' => 'Asia/Dubai', 'days' => [1, 2, 3, 4, 5], 'open' => '09:00', 'close' => '18:00'], $data['hours']);
            $this->assertSame($locale, $data['locale']);
            $this->assertCount(7, $data['text']['days']);
            // CLDR categories for Intl.PluralRules; Russian adds "other" for fractions ("1,5 часа").
            $expected = ['en' => ['one', 'other'], 'ru' => ['one', 'few', 'many', 'other'], 'ar' => ['zero', 'one', 'two', 'few', 'many', 'other']][$locale];
            $this->assertSame($expected, array_keys($data['text']['ahead']));
            $this->assertSame($expected, array_keys($data['text']['behind']));
            $this->assertStringContainsString(':time', $data['text']['open']);
            $this->assertStringContainsString(':day', $data['text']['closed']);
            // "opens today at 9 am" / "opens tomorrow at 9 am": a time, no weekday (modules/hours.js).
            foreach (['closedToday' => 'hours.closed_today', 'closedTomorrow' => 'hours.closed_tomorrow'] as $field => $key) {
                $this->assertSame(d2p($key, [], $locale), $data['text'][$field], "{$locale} {$field}");
                $this->assertStringContainsString(':time', $data['text'][$field]);
                $this->assertStringNotContainsString(':day', $data['text'][$field]);
            }
            // How :time is written (the approved style of the language: 12-hour in English and Arabic, 24-hour in Russian).
            $this->assertSame(['am', 'pm', 'amMinutes', 'pmMinutes'], array_keys($data['text']['time']));
            $this->assertMatchesRegularExpression($locale === 'ru' ? '/:hour_of_day::minute/' : '/:hour(?![a-z_])/', $data['text']['time']['pm']);
        }
    }

    public function test_search_area_proposed_copy_exists_in_every_language(): void
    {
        foreach (['faq', 'palette', 'hours'] as $area) {
            $keys = [];
            foreach (self::LOCALES as $locale) {
                $strings = Arr::dot(require lang_path("{$locale}/proposed-d2/{$area}.php"));
                $keys[$locale] = array_keys($strings);
                foreach ($strings as $key => $value) {
                    $this->assertIsString($value, "{$locale}/{$area}.{$key}");
                    $this->assertNotSame('', trim($value), "{$locale}/{$area}.{$key}");
                    // Keycap names stay as printed on keyboards; every other string is translated.
                    if (in_array($key, ['key_up', 'key_down', 'key_enter', 'key_esc'], true)) {
                        continue;
                    }
                    if ($locale === 'ar') {
                        $this->assertMatchesRegularExpression('/\p{Arabic}/u', $value, "ar/{$area}.{$key} is Arabic");
                    }
                    if ($locale === 'ru') {
                        $this->assertMatchesRegularExpression('/\p{Cyrillic}/u', $value, "ru/{$area}.{$key} is Russian");
                    }
                }
            }
            $this->assertSame($keys['en'], $keys['ar'], "{$area}: same keys in en and ar");
            $this->assertSame($keys['en'], $keys['ru'], "{$area}: same keys in en and ru");
        }

        // Plurals: the full set of forms (English 2, Russian 3, Arabic 6). The hours difference can be a fraction
        // (India: 1.5 hours), so its Russian line also has the fourth form "other" ("1,5 часа").
        foreach (['faq.results', 'palette.results', 'hours.ahead', 'hours.behind'] as $key) {
            [$area, $name] = explode('.', $key);
            foreach (['en' => 2, 'ru' => $area === 'hours' ? 4 : 3, 'ar' => 6] as $locale => $forms) {
                $line = (require lang_path("{$locale}/proposed-d2/{$area}.php"))[$name];
                $this->assertCount($forms, explode('|', $line), "{$locale} {$key}");
            }
        }
    }
}
