<?php

namespace Tests\Feature\Design\D2;

use App\Design\D2\Prefill;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Design 2 FAQ: the home section #questions (Design 2.1 §5.1 item 9, §5.2: the eight approved questions as
 * accordions with H3 questions, then "View All FAQs") and the reusable <x-d2.faq.browser> of the P05 FAQ page
 * (upgrade spec §12 C11; D41: tabs that wrap, a select on phones). Everything is server-rendered: every question
 * and answer is in the HTML.
 */
final class FaqTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['en', 'ar', 'ru'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    private function home(string $locale): string
    {
        return $this->withUnencryptedCookies(['summit_design' => '2', 'summit_qa' => '1'])->get("/{$locale}/")->assertOk()->getContent();
    }

    /** The #questions section of the Design 2 home page. */
    private function section(string $html): string
    {
        $start = strpos($html, '<section class="section faq-section" id="questions"');
        $this->assertNotFalse($start, 'the FAQ section is on the home page');
        $end = strpos($html, '</section>', $start);

        return substr($html, $start, $end - $start);
    }

    /** The FAQ browser section (#faq-search) of the Design 2 P05 page. */
    private function browser(string $locale): string
    {
        $html = $this->withUnencryptedCookies(['summit_design' => '2', 'summit_qa' => '1'])->get("/{$locale}/faq/")->assertOk()->getContent();
        $start = strpos($html, '<section class="section faq-browse" id="faq-search"');
        $this->assertNotFalse($start, 'the FAQ browser is on P05');
        $end = strpos($html, '</section>', $start);

        return substr($html, $start, $end - $start);
    }

    public function test_home_section_has_the_approved_heading_and_the_eight_home_questions(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $faq = content()->page('P01', $locale)['content']['faq'];
            $section = $this->section($this->home($locale));

            $this->assertStringContainsString('aria-labelledby="faq-title"', $section);
            $this->assertStringContainsString('<h2 id="faq-title">'.e($faq['heading']).'</h2>', $section);
            $this->assertCount(8, $faq['items']);
            foreach ($faq['items'] as $i => $item) {
                $n = $i + 1;
                // Design 1 anchors: a link to /en/#home-faq-3 opens that question.
                $this->assertMatchesRegularExpression('#<details class="acc__item"[^>]*\\sid="home-faq-'.$n.'"#', $section, "{$locale} home-faq-{$n}");
                $this->assertStringContainsString(e($item['question']), $section, "{$locale} question {$n}");
            }
            $this->assertSame(1, substr_count($section, 'id="home-faq-3"'));
            // The homepage file's Section 08: visible H3 questions with their complete answers in the HTML.
            $this->assertSame(8, substr_count($section, '<details class="acc__item"'), $locale);
            $this->assertSame(8, substr_count($section, '<h3 class="acc__title">'), "{$locale}: H3 questions");
            $this->assertStringNotContainsString('<h4', $section, $locale);
            foreach ($faq['items'] as $item) {
                $this->assertStringContainsString(e(\App\Design\D2\Bidi::isolate($item['answer'])), $section, "{$locale}: complete answer");
            }
            // Nothing else between the heading and "View All FAQs": no category tabs, search or other questions.
            $this->assertStringNotContainsString('data-faq-browser', $section, $locale);
            $this->assertStringNotContainsString('tabs__tab', $section, $locale);
            $this->assertStringNotContainsString('role="search"', $section, $locale);
            $this->assertStringContainsString('href="'.\App\Support\SiteUrl::page('P05').'"', $section, 'View All FAQs');
            $this->assertStringContainsString(e(ui('cta.view_all_faqs', $locale)), $section);
            $this->assertDoesNotMatchRegularExpression('#<div class="faq-section__foot">(?:(?!</div>).)*btn__icon#s', $section, "{$locale}: View All FAQs without an arrow");
        }
    }

    public function test_p05_browser_has_the_twelve_approved_category_tabs_in_order(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $section = $this->browser($locale);
            preg_match_all('#<a class="tabs__tab" href="\#(cat-\d{2})" id="cat-\d{2}-tab" data-tab="cat-\d{2}"(?: data-tab-initial)?><span class="tabs__label">([^<]*)</span></a>#', $section, $m);
            $categories = content()->faqCategories($locale);
            $this->assertSame(array_column($categories, 'anchor'), $m[1], $locale);
            $this->assertSame(array_map(fn ($c) => e($c['name']), $categories), $m[2], "{$locale}: approved category names");
            foreach ($m[1] as $anchor) {
                $this->assertStringContainsString('id="'.$anchor.'" data-tab-panel', $section);
            }
            $this->assertStringContainsString('aria-label="'.e(d2p('faq.categories_label')).'"', $section);
        }
    }

    /**
     * The tabbed first paint (docs/d2/engineering.md §4b): with html.js set and before d2Tabs mounts, CSS shows
     * only the panel marked [data-faq-initial] (or the one a #hash points into) and draws its tab, marked
     * [data-tab-initial], as selected. Exactly one of each, and they name the same category.
     */
    public function test_the_initial_category_is_marked_for_the_first_paint(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $section = $this->browser($locale);
            $this->assertSame(1, substr_count($section, 'data-tab-initial'), $locale);
            $this->assertSame(1, substr_count($section, ' data-faq-initial="'), $locale);
            $this->assertMatchesRegularExpression('#data-tab="cat-01" data-tab-initial>#', $section, "{$locale}: P05 opens on cat-01");
            $this->assertMatchesRegularExpression('#<div class="tabs__panel faq-panel"[^>]* data-faq-initial="data-faq-initial"[^>]* id="cat-01" data-tab-panel>#', $section, $locale);
        }

        // P05 (default active: the first category) and an explicit active panel.
        app()->setLocale('en');
        $p05 = Blade::render('<x-d2.faq.browser id="faq-browser" :copy-links="true" :expand-all="true" />');
        $this->assertSame(1, substr_count($p05, 'data-tab-initial'));
        $this->assertMatchesRegularExpression('#data-tab="cat-01" data-tab-initial>#', $p05);
        $other = Blade::render('<x-d2.faq.browser id="x" active="cat-07" />');
        $this->assertMatchesRegularExpression('#data-tab="cat-07" data-tab-initial>#', $other);
        $this->assertMatchesRegularExpression('#data-faq-initial="data-faq-initial"[^>]* id="cat-07" data-tab-panel>#', $other);
        $this->assertSame(1, substr_count($other, ' data-faq-initial="'));
    }

    public function test_every_category_shows_its_approved_questions_and_answers(): void
    {
        app()->setLocale('en');
        $section = $this->browser('en');
        foreach (content()->faqs('en') as $faq) {
            $this->assertStringContainsString('id="'.$faq['anchor'].'"', $section, $faq['anchor']);
            $this->assertStringContainsString(e($faq['question']), $section);
            foreach ($faq['answer'] as $block) {
                $this->assertStringContainsString(e($block['text']), $section, "{$faq['anchor']} answer");
            }
        }
        $this->assertSame(74, substr_count($section, '<details class="acc__item"'), 'every question is in the HTML (readable without JavaScript)');
        // The FAQ file's note 7: twelve category H2 headings and question H3 headings.
        $this->assertSame(12, substr_count($section, '<h2 class="tabs__panel-title">'));
        $this->assertSame(74, substr_count($section, '<h3 class="acc__title">'));
    }

    /**
     * D41: the category tabs are never cut. They wrap onto more rows (no scroll buttons, no fade), and on phones a
     * select labelled with P05's approved "Choose a topic" chooses the category (it drives the same tabs, d2Faq).
     */
    public function test_d41_the_categories_wrap_and_phones_choose_them_with_a_select(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $section = $this->browser($locale);
            $label = content()->page('P05', $locale)['content']['category_menu']['heading'];
            $this->assertMatchesRegularExpression('#<label class="field__label" for="faq-browser-category">'.preg_quote(e($label), '#').'</label>#', $section, "{$locale}: the approved label");
            preg_match('#<select[^>]*data-faq-picker[^>]*>(.*?)</select>#s', $section, $m);
            $this->assertNotEmpty($m, $locale);
            preg_match_all('#<option value="(cat-\d{2})"( selected)?[^>]*>([^<]*)</option>#', $m[1], $options);
            $categories = content()->faqCategories($locale);
            $this->assertSame(array_column($categories, 'anchor'), $options[1], $locale);
            $this->assertSame(array_map(fn ($c) => e($c['name']), $categories), $options[3], "{$locale}: approved names");
            $this->assertSame(' selected', $options[2][0], "{$locale}: the open category is chosen");
            $this->assertMatchesRegularExpression('#<select(?=[^>]*\sid="faq-browser-category")(?=[^>]*\saria-controls="faq-browser-results")[^>]*>#', $section);
        }

        $css = (string) file_get_contents(resource_path('css/d2/sections/faq.css'));
        $this->assertMatchesRegularExpression('/\.faq-browser__tabs \.tabs__list \{\s*flex-wrap: wrap;\s*overflow: visible;\s*mask-image: none;\s*\}/', $css, 'the tabs wrap, never scroll under a fade');
        $this->assertMatchesRegularExpression('/\.faq-browser__tabs \.tabs__scroll \{\s*display: none;\s*\}/', $css, 'no chevron scroll buttons');
        $this->assertMatchesRegularExpression('/@media \(width < 1024px\) \{\s*\.js \.faq-browser__picker \{\s*display: block;/', $css, 'the select on phones and tablets (with JavaScript)');
        $js = (string) file_get_contents(resource_path('js/d2/components/faq.js'));
        $this->assertStringContainsString("root.querySelector('[data-faq-picker]')", $js);
    }

    public function test_search_field_status_and_approved_strings(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $section = $this->browser($locale);
            $this->assertStringContainsString('role="search"', $section);
            $this->assertStringContainsString('for="faq-browser-search"', $section);
            $this->assertStringContainsString(e(ui('faq.search_label', $locale)), $section);
            // D09: the approved placeholder is drawn inside the field by the box, as text that wraps, so the whole
            // example shows on a phone (a native placeholder cannot wrap and was cut); it is the input's description.
            $this->assertMatchesRegularExpression('#<div class="faq-browser__box" data-faq-box>\s*<span class="control[^"]*"\s*>.*?<input class="input"(?=[^>]*\sid="faq-browser-search")(?=[^>]*\saria-describedby="faq-browser-search-example")[^>]*data-faq-search[^>]*>.*?</span>\s*<span class="faq-browser__hint" id="faq-browser-search-example" data-faq-hint>'.preg_quote(e(ui('faq.search_placeholder', $locale)), '#').'</span>\s*</div>#s', $section, "{$locale}: the approved example drawn in the search box");
            $this->assertDoesNotMatchRegularExpression('#<input class="input"(?=[^>]*\sid="faq-browser-search")[^>]*\splaceholder=#', $section, "{$locale}: no native placeholder to cut");
            $this->assertStringContainsString('role="status" data-faq-status', $section);
            $this->assertStringContainsString(e(ui('faq.search_reset', $locale)), $section);

            preg_match('#<script type="application/json" data-faq-copy>(.*?)</script>#s', $section, $m);
            $copy = json_decode($m[1] ?? 'null', true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(ui('faq.no_results', $locale), $copy['noResults']);
            $this->assertSame($locale, $copy['locale']);
            $forms = ['en' => ['one', 'other'], 'ru' => ['one', 'few', 'many'], 'ar' => ['zero', 'one', 'two', 'few', 'many', 'other']][$locale];
            $this->assertSame($forms, array_keys($copy['results']));
            foreach ($copy['results'] as $category => $form) {
                if (! ($locale === 'ar' && in_array($category, ['zero', 'one', 'two'], true))) {
                    $this->assertStringContainsString(':count', $form, "{$locale} {$category}");
                }
            }
        }
    }

    public function test_useful_links_and_start_here_use_approved_targets(): void
    {
        app()->setLocale('en');
        $section = $this->browser('en');
        $this->assertSame(12, substr_count($section, 'class="faq-panel__aside"'));
        $this->assertStringContainsString(e(ui('footer.useful_links')), $section);
        // cat-07's approved links: the service, its guide and the official portal (new tab, noopener).
        $this->assertStringContainsString('href="'.\App\Support\SiteUrl::service('S06').'"', $section);
        $this->assertStringContainsString('href="'.\App\Support\SiteUrl::guide('G01').'"', $section);
        $this->assertMatchesRegularExpression('#href="https://u\\.ae/en/information-and-services/jobs/Sector-of-employment/employment-in-the-private-sector/end-of-service-benefits-for-employees-in-the-private-sector"\\s+target="_blank" rel="noopener noreferrer"#', $section);
        // "Start here" carries the category's practice area to the wizard; cat-01 has none.
        $this->assertStringContainsString('href="'.e(Prefill::url(['service' => 'S06'])).'"', $section);
        $this->assertStringContainsString('data-faq-start="S06"', $section);
        $this->assertStringContainsString('data-faq-start=""', $section);
        $this->assertStringContainsString(e(d2p('faq.start_question')), $section);
    }

    /**
     * Design 2.1, Phase 1 (brief §3.6; D03, D04): the Useful Links are text links (no service or document icon,
     * no chevron); a link to another website ends with the external mark (an SVG) and its hidden "opens in a
     * new tab"; "Start here" and "View All FAQs" carry no arrow; P05's "Copy question link" is a text button
     * (the link glyph is not one of the allowed functional icons).
     */
    public function test_design_2_1_links_and_buttons_carry_no_decorative_icons_or_arrows(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $section = $this->browser($locale);
            $this->assertStringNotContainsString('faq-link__icon', $section, "{$locale}: no icon before a useful link");
            $this->assertStringNotContainsString('faq-link__go', $section, "{$locale}: no chevron after a useful link");
            preg_match_all('#<a class="faq-link" href="([^"]+)"[^>]*>(.*?)</a>#s', $section, $links, PREG_SET_ORDER);
            $this->assertNotEmpty($links);
            foreach ($links as [, $href, $inner]) {
                $external = str_starts_with($href, 'http') && ! str_starts_with($href, url('/'));
                $this->assertSame($external ? 1 : 0, substr_count($inner, 'data-icon="external"'), "{$locale}: {$href}");
                $this->assertSame($external ? 1 : 0, substr_count($inner, 'data-icon='), "{$locale}: {$href} has only the external mark, if any");
            }
            $this->assertDoesNotMatchRegularExpression('#data-faq-start="[^"]*"[^>]*>(?:(?!</a>).)*btn__icon#s', $section, "{$locale}: Start here without an arrow");
        }

        app()->setLocale('en');
        $html = Blade::render('<x-d2.faq.browser id="faq-browser" :panel-level="2" :question-level="3" :copy-links="true" :expand-all="true" />');
        $this->assertSame(74, preg_match_all('#<button[^>]*data-faq-copy-link="faq-\d{2}"[^>]*>\s*<span class="btn__spinner" aria-hidden="true"></span>\s*<span class="btn__label">'.preg_quote(e(ui('faq.copy_link')), '#').'</span>#', $html), 'Copy question link is a text button');
        $this->assertStringNotContainsString('data-icon="link"', $html);
    }

    /**
     * Design 2.1, Phase 1 (brief §3.6 rule 8, D09; §3.4, D05): the search field leaves room for the approved
     * placeholder (Russian "Введите слово, например: чек, зарплата, завещание, налог" needs 36rem; at 30rem it was cut
     * after "завеш"), and nothing in the FAQ stylesheet moves on hover, clamps text or draws a shadow.
     */
    public function test_design_2_1_faq_styles_keep_approved_copy_whole_and_still(): void
    {
        $css = (string) file_get_contents(resource_path('css/d2/sections/faq.css'));
        $this->assertMatchesRegularExpression('/\.faq-browser__field \{[^}]*max-inline-size: 36rem;/', $css, 'room for the approved placeholder');

        // Round 1 (D09): below 768 px no field holds the placeholder (English 367 px of text, Russian 484), so the
        // box draws the example as text that wraps while the field is empty: its line height is the field's inner
        // 46 px less its padding (one line = the 48 px field, its first line on the input's own line), it wraps and
        // balances (never nowrap or an ellipsis), it goes once a word is typed (.is-filled), and the box draws the
        // field's edge, hover and focus ring.
        $this->assertMatchesRegularExpression('/\.faq-browser__hint \{[^}]*display: block;[^}]*line-height: calc\(var\(--field-h\) - 2 \* var\(--line-w\) - 2 \* var\(--hint-pad\)\);[^}]*text-wrap: balance;[^}]*pointer-events: none;/s', $css);
        $this->assertDoesNotMatchRegularExpression('/\.faq-browser__hint \{[^}]*white-space: nowrap/s', $css);
        $this->assertMatchesRegularExpression('/\.faq-browser__box\.is-filled \.faq-browser__hint \{\s*display: none;\s*\}/', $css);
        $this->assertMatchesRegularExpression('/\.faq-browser__box \{[^}]*border: var\(--line-w\) solid var\(--ctl-edge\);/s', $css);
        $this->assertMatchesRegularExpression('/\.faq-browser__box:focus-within \{[^}]*outline: var\(--focus-w\) solid var\(--focus-color\);\s*outline-offset: var\(--focus-offset\);/s', $css);
        $this->assertMatchesRegularExpression('/\.faq-browser__box \.input \{[^}]*min-block-size: calc\(var\(--field-h\) - 2 \* var\(--line-w\)\);/s', $css);
        // Round 2 (§3.2 type roles): the aside's question ("Was this about your matter?") and its "Useful Links"
        // heading both take the Minor heading role (16/600), never Body size at heading weight (17/600).
        foreach (['faq-start__question', 'faq-aside__title'] as $class) {
            $this->assertMatchesRegularExpression('/\.'.$class.' \{[^}]*font-size: var\(--fs-minor\);\s*font-weight: var\(--fw-semibold\);\s*line-height: var\(--lh-minor\);/s', $css, "{$class}: Minor heading role");
        }
        $this->assertDoesNotMatchRegularExpression('/\.faq-start__question \{[^}]*--fs-body/s', $css);
        $this->assertDoesNotMatchRegularExpression('/line-clamp|text-overflow:\s*ellipsis/', $css, 'no clamp or ellipsis');
        $this->assertDoesNotMatchRegularExpression('/:hover[^{]*\{[^}]*(transform|translate|scale)/', $css, 'nothing moves on hover');
        $this->assertDoesNotMatchRegularExpression('/box-shadow:(?!\s*none\b)/', $css, 'no shadow');
    }

    public function test_arabic_latin_runs_are_isolated(): void
    {
        app()->setLocale('ar');
        $section = $this->browser('ar');
        // faq-23 mentions USDT in the approved Arabic question.
        $this->assertStringContainsString('<bdi dir="ltr">USDT</bdi>', $section);
    }

    public function test_ids_inside_the_sections_are_unique(): void
    {
        foreach (self::LOCALES as $locale) {
            foreach ([$this->section($this->home($locale)), $this->browser($locale)] as $section) {
                preg_match_all('# id="([^"]+)"#', $section, $m);
                $this->assertSame(array_values(array_unique($m[1])), $m[1], $locale);
            }
        }
    }

    public function test_browser_component_renders_the_p05_set_with_original_anchors(): void
    {
        app()->setLocale('ru');
        $html = Blade::render('<x-d2.faq.browser id="faq-browser" :panel-level="2" :question-level="3" :copy-links="true" :expand-all="true" />');

        for ($n = 1; $n <= 74; $n++) {
            $this->assertStringContainsString(sprintf('id="faq-%02d"', $n), $html);
        }
        for ($n = 1; $n <= 12; $n++) {
            $this->assertStringContainsString(sprintf('id="cat-%02d" data-tab-panel', $n), $html);
        }
        $this->assertSame(74, substr_count($html, '<details class="acc__item"'));
        $this->assertSame(74, substr_count($html, 'data-faq-copy-link="faq-'));
        $this->assertSame(12, substr_count($html, 'data-faq-expand="data-faq-expand"'));
        $this->assertStringContainsString('<h2 class="tabs__panel-title">', $html);
        $this->assertStringContainsString('<h3 class="acc__title">', $html);
        $this->assertStringContainsString(e(ui('faq.copy_link', 'ru')), $html);
        $this->assertStringContainsString(e(ui('faq.expand_all', 'ru')), $html);
    }

    public function test_browser_without_links_or_search(): void
    {
        app()->setLocale('en');
        $html = Blade::render('<x-d2.faq.browser id="x" :search="false" :links="false" />');
        $this->assertStringNotContainsString('role="search"', $html);
        $this->assertStringNotContainsString('faq-panel__aside', $html);
        $this->assertSame(74, substr_count($html, '<details class="acc__item"'));
    }
}
