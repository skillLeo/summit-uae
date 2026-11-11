<?php

namespace Tests\Feature\Design\D2;

use App\Design\D2\Hours;
use App\Design\D2\Icons;
use App\Support\SiteUrl;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Design 2 home sections owned by the sections builder (upgrade spec §12 C2, C4, C7; the trust row left in Phase 2, D20):
 * the practice areas, how we work + what a review can clarify, and who we help. Rendered through the real
 * home page with the Design 2 cookie, in all three languages, against the approved imported content.
 * Design 2.1 Phase 1 (D01–D09, D11): no decorative icons or arrows, approved copy in full, the home page file's
 * hours line without a live status, Ink buttons, sans step markers, plain question list.
 */
final class HomeSectionsTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['en', 'ar', 'ru'];

    /** @var array<string, string> rendered home per locale */
    private array $pages = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    private function home(string $locale): string
    {
        return $this->pages[$locale] ??= $this->withUnencryptedCookies(['summit_design' => '2'])
            ->get("/{$locale}/")->assertOk()->getContent();
    }

    /** The outer HTML of the <section> whose id is $id (these sections never nest another section). */
    private function section(string $locale, string $id): string
    {
        $html = $this->home($locale);
        $at = strpos($html, ' id="'.$id.'"');
        $this->assertNotFalse($at, "#{$id} is rendered ({$locale})");
        $open = strrpos(substr($html, 0, $at), '<section');
        $this->assertNotFalse($open, "#{$id} is a <section> ({$locale})");
        $close = strpos($html, '</section>', $at);

        return substr($html, $open, $close - $open + strlen('</section>'));
    }

    /** @return array<string, mixed> approved content of a page in a locale */
    private function page(string $code, string $locale): array
    {
        return content()->page($code, $locale)['content'] ?? [];
    }

    private function text(string $html): string
    {
        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
    }

    public function test_sections_render_in_the_shared_order_with_their_anchors_and_one_h2_each(): void
    {
        foreach (self::LOCALES as $locale) {
            $html = $this->home($locale);
            $positions = [];
            // Brief §5.1 (D26): 02 services, 03 who we help, 04 review, in the DOCX order; the trust row is gone (D20).
            $this->assertStringNotContainsString('id="trust"', $html, "no trust row ({$locale})");
            foreach (['services', 'who-we-help', 'review'] as $id) {
                $section = $this->section($locale, $id);
                preg_match('/<section[^>]* aria-labelledby="([^"]+)"/', $section, $m);
                $this->assertNotEmpty($m[1] ?? null, "#{$id} is labelled ({$locale})");
                $this->assertMatchesRegularExpression('/<h2[^>]* id="'.preg_quote($m[1], '/').'"/', $section, "#{$id} is labelled by its H2 ({$locale})");
                $this->assertSame(1, substr_count($section, '<h2'), "#{$id} has exactly one H2 ({$locale})");
                $this->assertStringNotContainsString('<h1', $section, 'the hero owns the only H1');
                $this->assertStringNotContainsString(' style="', $section, "no inline style attributes (CSP) in #{$id}");
                $positions[] = strpos($html, ' id="'.$id.'"');
            }
            $sorted = $positions;
            sort($sorted);
            $this->assertSame($sorted, $positions, "services → who-we-help → review ({$locale})");
        }
    }

    public function test_practice_area_cards_are_equal_text_cards_with_the_full_summary_and_explore_this_service(): void
    {
        foreach (self::LOCALES as $locale) {
            $html = $this->section($locale, 'services');
            $copy = $this->page('P01', $locale)['services'];
            $services = content()->services($locale);
            $explore = e(content()->ui('cta.explore_service', $locale));
            $this->assertMatchesRegularExpression('/<h2 id="services-title">'.preg_quote(e($copy['heading']), '/').'<\/h2>/', $html);
            $this->assertStringContainsString(e($copy['intro']), $html);
            $this->assertCount(11, $services);
            // D21: the eleven cards in the supplied order, each its own <li> of one plain list (no carousel, no bento).
            $this->assertStringNotContainsString('carousel', $html, "no swipe rail ({$locale})");
            preg_match_all('/<li class="[^"]*\bsvc-card\b[^"]*" data-svc="(S\d\d)"/', $html, $order);
            $this->assertSame(array_column($services, 'code'), $order[1], "the DOCX order ({$locale})");
            foreach ($services as $service) {
                $code = $service['code'];
                $url = SiteUrl::service($code, $locale);
                $this->assertMatchesRegularExpression('/<h3 class="card__title">\s*<a class="card__link" href="'.preg_quote($url, '/').'">'.preg_quote(e($service['title']), '/').'<\/a>/', $html, "{$code}: the H3 title is the card's one link ({$locale})");
                // D04: no service object icon; D09: the approved summary in full.
                $this->assertStringNotContainsString('data-icon="'.Icons::forService($code).'"', $html, "{$code}: no decorative icon ({$locale})");
                $this->assertStringContainsString($service['home_summary'], $this->text($html), "{$code}: the complete summary ({$locale})");
            }
            // One link per card (the stretched title); the visible approved "Explore This Service" is its label, not a second link.
            $this->assertSame(11, substr_count($html, '<a class="card__link"'));
            $this->assertSame(11, substr_count($html, '<span class="svc-card__cta" aria-hidden="true" data-card-link>'.$explore.'</span>'), "a visible Explore This Service on every card ({$locale})");
            $this->assertDoesNotMatchRegularExpression('/<a\b[^>]*>\s*'.preg_quote($explore, '/').'/', $html, "Explore This Service is not a second link ({$locale})");
            // D03: no corner arrow and no arrow on the button; D06: the button is Ink. D22: no documents line.
            $this->assertStringNotContainsString('svc-card__arrow', $html);
            $this->assertDoesNotMatchRegularExpression('/data-icon="(arrow|arrow-start)"/', $html);
            $this->assertStringNotContainsString('svc-card__docs', $html, "no documents line ({$locale})");
            $this->assertStringNotContainsString('usually needed', $html);
            $this->assertStringNotContainsString('<bdi>4</bdi>', $html);
            $this->assertStringNotContainsString('svc-card--wide', $html, 'no bento widths');
            $this->assertStringNotContainsString('data-svc-span', $html, 'no bento spans');
            // D23: the approved closing and "View All Practice Areas" as one row under the grid, not a tile in it.
            $this->assertStringNotContainsString('svc-closing', $html);
            $this->assertMatchesRegularExpression('/<\/ul>\s*<div class="svc__more">\s*<p class="svc__more-text">'.preg_quote(e($copy['closing']), '/').'<\/p>\s*<a class="btn btn--ink btn--48 svc__more-action" href="'.preg_quote(SiteUrl::page('P02', $locale), '/').'"/', $html);
            $this->assertStringContainsString(e(content()->ui('cta.view_all_practice_areas', $locale)), $html);
        }
        $this->assertArrayNotHasKey('documents', require lang_path('en/proposed-d2/services.php'), 'D22: the documents key is gone');

        // D21: an equal grid (1 / 2 / 3 columns) whose cards share their row tracks (subgrid: title, summary, label).
        $css = (string) file_get_contents(resource_path('css/d2/sections/services.css'));
        $this->assertMatchesRegularExpression('/@media \(width >= 768px\) \{\s*\.svc__grid \{\s*grid-template-columns: repeat\(2, minmax\(0, 1fr\)\);/', $css);
        $this->assertMatchesRegularExpression('/@media \(width >= 1280px\) \{\s*\.svc__grid \{\s*grid-template-columns: repeat\(3, minmax\(0, 1fr\)\);/', $css);
        $this->assertMatchesRegularExpression('/\.svc-card \{\s*display: grid;\s*grid-row: span 3;\s*grid-template-rows: subgrid;/', $css);
        // D23: one row from 768 px, the text on the start side, the button on the end side.
        $this->assertMatchesRegularExpression('/\.svc__more \{\s*flex-direction: row;\s*align-items: center;\s*justify-content: space-between;/', $css);
        foreach (['line-clamp', 'text-overflow', '--lift', 'translate', 'box-shadow', 'svc-card__icon', 'svc-card__arrow', '--svc-span', 'svc-card--wide', 'svc-closing', 'svc-card__docs'] as $gone) {
            $this->assertStringNotContainsString($gone, $css, "services.css: no {$gone}");
        }
    }

    public function test_practice_area_filter_chips_follow_the_hero_audience_mapping(): void
    {
        $map = config('audience_map.hero');
        $html = $this->section('en', 'services');
        $this->assertMatchesRegularExpression('/data-svc-audience="all" data-svc-result="11 practice areas shown"\s+type="button"\s+aria-pressed="true"/', $html);
        $titles = ['individual' => 'Individuals', 'business' => 'Businesses', 'investor' => 'Investors'];
        foreach ($map as $audience => $codes) {
            $count = count($codes);
            $this->assertMatchesRegularExpression('/data-svc-audience="'.$audience.'" data-svc-result="'.$count.' practice areas shown"\s+type="button"\s+aria-pressed="false"\s*>\s*<span class="chip__label">'.$titles[$audience].'<\/span>\s*<span class="chip__count">'.$count.'<\/span>/', $html, "{$audience} chip");
        }
        foreach (array_keys(config('summit.services')) as $code) {
            preg_match('/data-svc="'.$code.'" data-svc-audiences="([^"]*)"/', $html, $m);
            $this->assertNotEmpty($m, "{$code} carries its filter data");
            $audiences = array_keys(array_filter($map, fn (array $codes) => in_array($code, $codes, true)));
            $this->assertSame($audiences, $m[1] === '' ? [] : explode(' ', $m[1]), "{$code} audiences");
        }
        $this->assertStringContainsString('x-data="d2Services"', $html);
        $this->assertStringContainsString('x-on:click="pick"', $html);
        // The chips need JavaScript, so the CSS draws them only under .js; every card is in the HTML.
        $this->assertSame(11, substr_count($html, 'data-svc-audiences="'));
        $this->assertDoesNotMatchRegularExpression('/<li[^>]*\shidden[\s>=]/', $html, 'no card is hidden on the server');
        // Russian / Arabic plural forms in the announcements.
        $ru = $this->section('ru', 'services');
        $this->assertStringContainsString('data-svc-result="Показано 5 направлений"', $ru);
        $this->assertStringContainsString('data-svc-result="Показано 11 направлений"', $ru);
        $ar = $this->section('ar', 'services');
        $this->assertStringContainsString('data-svc-result="تُعرض 7 مناطق ممارسة"', $ar);
        $this->assertStringContainsString('data-svc-result="تُعرض 11 منطقة ممارسة"', $ar);
    }

    public function test_arabic_latin_runs_in_summaries_are_isolated(): void
    {
        $this->assertStringContainsString('<bdi dir="ltr">USDT</bdi>', $this->section('ar', 'services'), 'S03 keeps USDT left-to-right');
    }

    public function test_review_section_shows_the_approved_method_and_questions(): void
    {
        foreach (self::LOCALES as $locale) {
            $html = $this->section($locale, 'review');
            $review = $this->page('P01', $locale)['review'];
            $text = $this->text($html);
            $this->assertMatchesRegularExpression('/<h2 class="method__title" id="review-title">'.preg_quote(e($review['heading']), '/').'<\/h2>/', $html);
            foreach ($review['paragraphs'] as $paragraph) {
                $this->assertStringContainsString($paragraph, $text);
            }
            $this->assertStringContainsString('<h3 class="method__heading" id="review-method-title">'.e($review['method_heading']).'</h3>', $html);
            $this->assertStringContainsString('<h3 class="method__heading" id="review-questions-title">'.e($review['questions_heading']).'</h3>', $html);
            $this->assertMatchesRegularExpression('/<ol class="method__steps"\s+role="list"\s+aria-labelledby="review-method-title"\s*>/', $html);
            $this->assertCount(4, $review['steps']);
            foreach ($review['steps'] as $i => $step) {
                $this->assertStringContainsString('<span class="method-step__num" aria-hidden="true">'.sprintf('%02d', $i + 1).'</span>', $html);
                $this->assertMatchesRegularExpression('/<h4 class="method-step__title">\s*'.preg_quote(e($step['title']), '/').'\s*<\/h4>/', $html);
                $this->assertStringContainsString($step['text'], $text);
            }
            $this->assertCount(5, $review['questions']);
            foreach ($review['questions'] as $question) {
                $this->assertStringContainsString('<li class="method__question">'.e($question).'</li>', $html);
            }
            // The approved questions have no answers: a plain list, never an accordion with invented text, and no
            // decorative check marks (D04). D49: no card and no still-life (StartReviewWhoTest pins the layout).
            $this->assertStringNotContainsString('<details', $html);
            $this->assertStringNotContainsString('class="method__check"', $html);
            $this->assertStringNotContainsString('data-icon=', $html);
            $this->assertStringNotContainsString('method__still', $html);
            $this->assertStringNotContainsString('class="card', $html);
            $this->assertStringNotContainsString('method-sheet', $html, 'the stand-in sheets are gone (the documents always render)');
            // D01: the step markers are the interface sans (no mono), with no bronze halo and no scroll-driven fill.
            $css = (string) file_get_contents(resource_path('css/d2/sections/method.css'));
            foreach (['--font-mono', '--ring-pin', 'animation-timeline', '--bronze'] as $gone) {
                $this->assertStringNotContainsString($gone, $css, "method.css: no {$gone}");
            }
        }
    }

    public function test_who_we_help_shows_the_five_approved_audience_blocks_with_the_design_1_ids(): void
    {
        foreach (self::LOCALES as $locale) {
            $html = $this->section($locale, 'who-we-help');
            $who = $this->page('P01', $locale)['who_we_help'];
            $text = $this->text($html);
            $this->assertMatchesRegularExpression('/<h2 id="who-title">'.preg_quote(e($who['heading']), '/').'<\/h2>/', $html);
            $this->assertStringContainsString($who['intro'], $text);
            $this->assertCount(5, $who['items']);
            $this->assertSame(5, substr_count($html, '<li class="who__item"'), "five audience blocks ({$locale})");
            foreach ($who['items'] as $item) {
                $key = $item['key'];
                $this->assertStringContainsString('<li class="who__item" id="who-'.$key.'">', $html, "#who-{$key} ({$locale})");
                $this->assertStringContainsString('<h3 class="who__title" id="who-'.$key.'-title">'.e($item['title']).'</h3>', $html);
                $this->assertStringContainsString($item['text'], $text);
            }
        }
    }

    public function test_proposed_copy_exists_in_every_language_with_translations_and_plural_forms(): void
    {
        $forms = ['en' => 2, 'ar' => 6, 'ru' => 3];
        foreach (['services', 'method'] as $area) {
            $files = [];
            foreach (self::LOCALES as $locale) {
                $files[$locale] = require lang_path("{$locale}/proposed-d2/{$area}.php");
            }
            $this->assertSame(array_keys($files['en']), array_keys($files['ar']), "{$area}: same keys in ar");
            $this->assertSame(array_keys($files['en']), array_keys($files['ru']), "{$area}: same keys in ru");
            foreach ($files['en'] as $key => $value) {
                foreach (['ar', 'ru'] as $locale) {
                    $this->assertNotSame($value, $files[$locale][$key], "{$area}.{$key} is translated ({$locale})");
                    $this->assertNotSame('', trim($files[$locale][$key]));
                }
            }
        }
        foreach (self::LOCALES as $locale) {
            $this->assertArrayNotHasKey('watermark', require lang_path("{$locale}/proposed-d2/method.php"), 'the stand-in sheets\' watermark key is deleted');
            $services = require lang_path("{$locale}/proposed-d2/services.php");
            $this->assertCount($forms[$locale], explode('|', $services['result']), "services.result has every plural form ({$locale})");
            $this->assertArrayNotHasKey('documents', $services, "D22: no documents line ({$locale})");
        }
        // trans_choice picks the right form (Laravel: ar 0 | 1 | 2 | 3–10 | 11–99 | 100+, ru 1 | 2–4 | 5–20).
        $this->assertSame('Показано 21 направление', trans_choice('proposed.d2.services.result', 21, [], 'ru'));
        $this->assertSame('Показано 12 направлений', trans_choice('proposed.d2.services.result', 12, [], 'ru'));
        $this->assertSame('تُعرض منطقتا ممارسة', trans_choice('proposed.d2.services.result', 2, [], 'ar'));
        $this->assertSame('تُعرض 12 منطقة ممارسة', trans_choice('proposed.d2.services.result', 12, [], 'ar'));
        $this->assertSame('1 practice area shown', trans_choice('proposed.d2.services.result', 1, [], 'en'));
    }

    public function test_sections_styleguide_partial_shows_every_state_without_duplicate_ids(): void
    {
        foreach (['en', 'ar'] as $locale) {
            $html = $this->withUnencryptedCookies(['summit_design' => '2'])->get("/{$locale}/styleguide/")->assertOk()->getContent();
            $start = strpos($html, '<section class="sg-section" id="sg-sections">');
            $this->assertNotFalse($start, "the sections area is listed ({$locale})");
            $end = strpos($html, '<section class="sg-section"', $start + 10);
            $area = substr($html, $start, $end === false ? null : $end - $start);
            foreach (['data-sg="svc-states"', 'svc-card is-hover', 'svc-card is-focus', 'data-sg="svc-row"', 'data-sg="svc-more"',
                'data-sg="svc-live"', 'data-sg="svc-filtered"', 'data-sg="steps"',
                'data-sg="steps-stacked"', 'data-sg="questions"', 'data-sg="who-list"', 'data-sg="start-picks"', 'data-sg="start-documents"',
                'data-sg="start-none"'] as $marker) {
                $this->assertStringContainsString($marker, $area, "styleguide shows {$marker} ({$locale})");
            }
            // The filtered snapshot hides the non-investor cards on the server (S01, S02 here).
            $filtered = substr($area, strpos($area, 'data-sg="svc-filtered"'));
            $filtered = substr($filtered, 0, strpos($filtered, 'data-sg="steps"'));
            $this->assertSame(2, preg_match_all('/<li(?=[^>]*\bsvc-card\b)(?=[^>]*\shidden="hidden")[^>]*>/', $filtered), "filtered snapshot ({$locale})");
            preg_match_all('/ id="([^"]+)"/', $area, $ids);
            $this->assertSame(array_unique($ids[1]), $ids[1], "no duplicate ids in the sections area ({$locale})");
            foreach (['services', 'review', 'who-we-help', 'who-individuals', 'start', 'hero-tool-title'] as $homeId) {
                $this->assertNotContains($homeId, $ids[1], "specimens never reuse the home anchors ({$locale})");
            }
        }
    }
}
