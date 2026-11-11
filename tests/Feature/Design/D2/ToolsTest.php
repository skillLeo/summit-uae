<?php

namespace Tests\Feature\Design\D2;

use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Design 2 calculators (/{locale}/calculators/ since Design 2.1 Phase 2, brief §5.1; upgrade spec §12 C6, §5.2,
 * §5.3): the gratuity estimator and the Dubai rent increase check. The calculations are unit-tested in Vitest (resources/js/d2/tools/*.test.js); this test
 * covers the server side: markup, anchors, no-JS path, prefill links, proposed copy in three languages,
 * plurals, compliance and the styleguide area.
 */
final class ToolsTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['en', 'ar', 'ru'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    private function page(string $path): string
    {
        return $this->withUnencryptedCookies(['summit_design' => '2'])->get($path)->assertOk()->getContent();
    }

    /** The tools <section> (it contains the result <section>s, so match the tags). */
    private function section(string $html): string
    {
        $start = strpos($html, '<section class="section tools" id="tools"');
        $this->assertNotFalse($start, 'the tools section renders');
        $depth = 0;
        $offset = $start;
        while (preg_match('#<section[\s>]|</section>#', $html, $m, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $depth += $m[0][0] === '</section>' ? -1 : 1;
            $offset = $m[0][1] + strlen($m[0][0]);
            if ($depth === 0) {
                return substr($html, $start, $offset - $start);
            }
        }
        $this->fail('the tools section is closed');
    }

    /** @return array<string, mixed> */
    public function test_the_page_is_design_2_only_noindex_and_hidden_without_pending_copy(): void
    {
        foreach (self::LOCALES as $locale) {
            $html = $this->page("/{$locale}/calculators/");
            $this->assertStringContainsString('noindex', $html, "{$locale}: noindex while pending");
            $this->assertStringNotContainsString('id="tools"', $this->page("/{$locale}/"), "{$locale}: the calculators left the home");
            $this->withUnencryptedCookies(['summit_design' => '1'])->get("/{$locale}/calculators/")
                ->assertRedirect(url('/')."/{$locale}/");
        }
        $this->assertStringNotContainsString('calculators', $this->get('/sitemap.xml')->getContent(), 'out of the sitemap');
        config(['design.d2.show_pending' => false]);
        $this->withUnencryptedCookies(['summit_design' => '2'])->get('/en/calculators/')->assertNotFound();
    }

    private function config(string $html, string $tool): array
    {
        $this->assertSame(1, preg_match('#data-tool="'.$tool.'".*?<script type="application/json" data-tool-config>(.*?)</script>#s', $html, $m), $tool);

        return json_decode(html_entity_decode($m[1]), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_section_frame_anchors_and_switch_in_every_language(): void
    {
        foreach (self::LOCALES as $locale) {
            $html = $this->page("/{$locale}/calculators/");
            $section = $this->section($html);
            // Brief §5.1: the calculators have their own page; its H1 is the proposed tools title.
            $this->assertStringContainsString('aria-labelledby="page-title"', $section);
            $this->assertStringContainsString('x-data="d2Tools"', $section);
            $this->assertMatchesRegularExpression('/<h1 class="inner-head__title" id="page-title">\s*'.preg_quote(e(d2p('tools.section.title', [], $locale)), '/').'/', $html);
            $this->assertSame(1, preg_match_all('/<h1[\s>]/', $html), "{$locale}: one H1");

            // D35: [Gratuity estimator | Rent increase check] are underline tabs (<x-d2.ui.tabs>), gratuity first;
            // without JavaScript they are jump links to the two panels (the deep links #tool-gratuity, #tool-rent).
            $this->assertStringNotContainsString('name="tools_tool"', $section, 'no segmented switch');
            $this->assertMatchesRegularExpression('#<div class="tabs tools__tabs"[^>]*x-data="d2Tabs" data-active="tool-gratuity"[^>]*data-sync-hash#', $section);
            $this->assertStringContainsString('aria-label="'.e(d2p('tools.switch.label', [], $locale)).'"', $section);
            $this->assertStringContainsString('<a class="tabs__tab" href="#tool-gratuity" id="tool-gratuity-tab" data-tab="tool-gratuity" data-tab-initial>', $section);
            $this->assertStringContainsString('<a class="tabs__tab" href="#tool-rent" id="tool-rent-tab" data-tab="tool-rent"><span class="tabs__label">'.e(d2p('tools.switch.rent', [], $locale)).'</span>', $section);
            $this->assertStringContainsString('<div class="tabs__panel tools__panel tools__panel--gratuity" data-tool-panel="gratuity" id="tool-gratuity" data-tab-panel>', $section);
            $this->assertStringContainsString('<div class="tabs__panel tools__panel tools__panel--rent" data-tool-panel="rent" id="tool-rent" data-tab-panel>', $section);
            $this->assertStringContainsString('x-data="d2ToolGratuity"', $section);
            $this->assertStringContainsString('x-data="d2ToolRent"', $section);
            // Logical headings: the H1, then one (visually hidden) H2 per tool; the out-of-scope title is an H3.
            $this->assertStringContainsString('<h2 class="sr-only" id="gr-title"', $section);
            $this->assertStringContainsString('<h2 class="sr-only" id="rent-title"', $section);
            $this->assertMatchesRegularExpression('#<h3 class="tool__out-title"#', $section);

            // Links to the page use its own address (App\Design\D2\Calculators::url()).
            $this->assertSame(url('/').'/'.$locale.'/calculators/', \App\Design\D2\Calculators::url($locale));
        }
    }

    public function test_gratuity_inputs_scope_and_out_of_scope_panel(): void
    {
        $section = $this->section($this->page('/en/calculators/'));
        foreach (['startDate', 'lastWorkingDay', 'unpaidAbsenceDays', 'basicMonthly', 'totalMonthlyWage', 'contractType', 'hoursPerWeek',
            'fullTimeHoursPerWeek', 'unusedLeaveDays', 'noticeDaysInContract', 'noticeDaysServed', 'unpaidWages', 'scope'] as $key) {
            $this->assertStringContainsString('data-input="'.$key.'"', $section, $key);
        }
        foreach (['mainland', 'freezone', 'difc', 'adgm', 'government', 'domestic'] as $scope) {
            $this->assertMatchesRegularExpression('#name="gr_scope" value="'.$scope.'"#', $section, $scope);
            // D35: the same six sectors in the select that replaces the segmented control on phones.
            $this->assertMatchesRegularExpression('#<option value="'.$scope.'"#', $section, $scope);
        }
        $this->assertMatchesRegularExpression('#<div class="tool__scope" data-scope-control>\s*<fieldset class="segmented segmented--48 tool__scope-seg"#', $section);
        $this->assertMatchesRegularExpression('#<select class="select" id="gr-scope-pick" data-scope-select="data-scope-select">#', $section);
        $this->assertStringContainsString('<label class="field__label" for="gr-scope-pick">'.e(d2p('tools.gratuity.scope.label')).'</label>', $section);
        $this->assertDoesNotMatchRegularExpression('#<select[^>]*data-scope-select[^>]*\bname=#', $section, 'the select is not a second value');
        $this->assertStringContainsString('data-scope="in"', $section);
        $this->assertStringContainsString(e(d2p('tools.gratuity.out.text')), $section);
        // Every field error is rendered (hidden) for the script, ids follow the field contract.
        $this->assertStringContainsString('id="gr-end-error" data-field-error', $section);
        $this->assertStringContainsString('min="30" max="90"', $section);
        // Badges, notes and the info row are server-rendered (hidden until they apply).
        foreach (['result.cap', 'result.cap_basic', 'result.may_apply', 'result.payment', 'result.references', 'result.disclaimer'] as $key) {
            $this->assertStringContainsString(e(d2p('tools.gratuity.'.$key)), $section, $key);
        }
    }

    public function test_rent_inputs_dld_link_and_band_scale(): void
    {
        foreach (self::LOCALES as $locale) {
            $section = $this->section($this->page("/{$locale}/calculators/"));
            foreach (['currentAnnualRent', 'indexAverageRent', 'expiryDate', 'noticeReceivedDate'] as $key) {
                $this->assertStringContainsString('data-input="'.$key.'"', $section, $key);
            }
            $url = d2p('tools.rent.dld.url', [], $locale);
            $this->assertStringStartsWith('https://dubailand.gov.ae/', $url);
            $this->assertStringContainsString('href="'.$url.'" target="_blank" rel="noopener noreferrer"', $section, $locale);
            $this->assertSame(5, preg_match_all('/<li class="tool__step" data-band="[0-4]">/', $section), "{$locale}: five bands");
            // Figures in the band labels keep their order inside Arabic text.
            $this->assertStringContainsString('<bdi dir="ltr">', $section);
            $this->assertStringContainsString(e(d2p('tools.rent.result.disclaimer', [], $locale)), $section);
        }
        $this->assertStringContainsString('https://dubailand.gov.ae/ar/', d2p('tools.rent.dld.url', [], 'ar'));
    }

    public function test_without_javascript_the_tools_keep_their_fields_note_and_consultation_link(): void
    {
        $section = $this->section($this->page('/en/calculators/'));
        $this->assertSame(2, substr_count($section, 'class="tool__nojs"'));
        $this->assertStringContainsString(e(d2p('tools.common.nojs')), $section);
        // Consultation links: service + tool only, never a figure (and no <form>: nothing can be submitted).
        $this->assertStringContainsString('/en/?service=S06&amp;tool=gratuity#consultation-form', $section);
        $this->assertStringContainsString('/en/?service=S05&amp;tool=rent#consultation-form', $section);
        $this->assertStringNotContainsString('<form', $section);
        $this->assertStringContainsString(e(ui('cta.request_consultation')), $section);
        // CSP: no inline style attribute anywhere in the section.
        $this->assertStringNotContainsString(' style="', $section);
        // The live result is announced politely.
        $this->assertSame(2, substr_count($section, 'aria-live="polite" data-live'));
        // The breakdown table is the chart's text alternative; the bar itself is hidden from assistive tech.
        $this->assertStringContainsString('<table class="tool__table', $section);
        $this->assertStringContainsString('class="tool__bar" aria-hidden="true"', $section);
    }

    public function test_script_config_carries_the_page_language_and_every_plural_form(): void
    {
        $expected = ['en' => ['one', 'other'], 'ru' => ['one', 'few', 'many'], 'ar' => ['zero', 'one', 'two', 'few', 'many', 'other']];
        foreach (self::LOCALES as $locale) {
            $section = $this->section($this->page("/{$locale}/calculators/"));
            foreach (['gratuity', 'rent'] as $tool) {
                $config = $this->config($section, $tool);
                $this->assertSame($locale, $config['locale']);
                $this->assertSame($expected[$locale], array_keys($config['plurals']['days']), "{$locale} {$tool}");
                foreach ($config['plurals']['days'] as $form) {
                    $this->assertNotSame('', $form);
                }
                $this->assertNotEmpty($config['summary']['title']);
                $this->assertNotEmpty($config['errors']['date']);
            }
            $gratuity = $this->config($section, 'gratuity');
            $this->assertSame('S06', $gratuity['service']);
            $this->assertCount(6, $gratuity['scopes']);
            $this->assertSame('S05', $this->config($section, 'rent')['service']);
        }
        // Arabic and Russian use the full sets of forms through trans_choice().
        $this->assertSame('يومان', trans_choice('proposed.d2.tools.common.days', 2, [], 'ar'));
        $this->assertSame('11 يومًا', trans_choice('proposed.d2.tools.common.days', 11, [], 'ar'));
        $this->assertSame('103 أيام', trans_choice('proposed.d2.tools.common.days', 103, [], 'ar'));
        $this->assertSame('22 дня', trans_choice('proposed.d2.tools.common.days', 22, [], 'ru'));
        $this->assertSame('25 дней', trans_choice('proposed.d2.tools.common.days', 25, [], 'ru'));
        $this->assertSame('21 день', trans_choice('proposed.d2.tools.common.days', 21, [], 'ru'));
    }

    public function test_proposed_copy_exists_in_all_three_languages_and_is_translated(): void
    {
        $flatten = function (array $items, string $prefix = '') use (&$flatten): array {
            $out = [];
            foreach ($items as $key => $value) {
                $out += is_array($value) ? $flatten($value, $prefix.$key.'.') : [$prefix.$key => (string) $value];
            }

            return $out;
        };
        $files = [];
        foreach (self::LOCALES as $locale) {
            $files[$locale] = $flatten(require lang_path("{$locale}/proposed-d2/tools.php"));
        }
        $this->assertSame(array_keys($files['en']), array_keys($files['ar']), 'ar has the same keys');
        $this->assertSame(array_keys($files['en']), array_keys($files['ru']), 'ru has the same keys');

        // Strings that may stay identical: symbols, example numbers, Latin codes and the shared DLD link.
        $same = '/^(—|[\d,.%\s\x{00A0}–]+|DIFC|ADGM|:days \(:years\)|https:\/\/dubailand\.gov\.ae\/en\/.*)$/u';
        foreach (['ar', 'ru'] as $locale) {
            foreach ($files[$locale] as $key => $value) {
                $this->assertNotSame('', trim($value), "{$locale}: {$key} is empty");
                if ($value === $files['en'][$key] && preg_match($same, $value) !== 1) {
                    $this->fail("{$locale}: {$key} is still English: {$value}");
                }
            }
        }
        $this->assertMatchesRegularExpression('/\p{Arabic}/u', $files['ar']['gratuity.out.text']);
        $this->assertMatchesRegularExpression('/\p{Cyrillic}/u', $files['ru']['rent.result.scope']);
    }

    public function test_compliance_no_banned_claims_and_no_claim_time_limit(): void
    {
        $banned = ['24/7', 'free consultation', 'law firm', 'years of experience', 'award', 'rated', 'success rate', 'lawyer', 'advocate', 'licensed'];
        foreach (self::LOCALES as $locale) {
            $text = mb_strtolower(strip_tags($this->section($this->page("/{$locale}/calculators/"))));
            foreach ($banned as $needle) {
                $this->assertStringNotContainsString($needle, $text, "{$locale}: {$needle}");
            }
            // Spec §5.2: no time limit for claims until a lawyer confirms it (Art. 54, one vs two years).
            $this->assertStringNotContainsString('art. 54', $text);
            $this->assertStringNotContainsString('time limit', $text);
            $this->assertStringNotContainsString('المادة 54', $text);
            $this->assertStringNotContainsString('ст. 54', $text);
        }
    }

    /**
     * Design 2.1, Phase 1 (brief §3.6, D01, D04, D06): no eyebrow over the result (its invented key is gone in
     * every language), no icon tile on the out-of-scope panel, no decorative icon on a button (the copy button is
     * text only), only functional icons in the section, and no gold button: every action is Ink or Secondary.
     */
    public function test_design_2_1_no_eyebrow_no_decorative_icons_and_no_gold_button(): void
    {
        foreach (self::LOCALES as $locale) {
            $section = $this->section($this->page("/{$locale}/calculators/"));
            $this->assertStringNotContainsString('t-eyebrow', $section, "{$locale}: no eyebrow");
            $this->assertStringNotContainsString('tool__result-title', $section, "{$locale}: no result eyebrow");
            $this->assertStringNotContainsString('tool__out-icon', $section, "{$locale}: no icon tile");
            $this->assertStringNotContainsString('btn--signature', $section, "{$locale}: no gold button");
            $this->assertStringNotContainsString('btn--gold', $section, "{$locale}: no gold button");
            $this->assertSame(3, substr_count($section, 'class="btn btn--ink'), "{$locale}: the consultation buttons are Ink");
            foreach (['gratuity', 'rent'] as $tool) {
                $this->assertFalse(\Illuminate\Support\Facades\Lang::has("proposed.d2.tools.{$tool}.result.title", $locale, false), "{$locale}: {$tool} result eyebrow key deleted");
                // The result card opens with its state line (no heading of its own any more).
                $this->assertMatchesRegularExpression('#<div class="tool__result" id="'.($tool === 'gratuity' ? 'gr' : 'rent').'-result" data-result>\s*<p class="tool__nojs"#', $section, "{$locale} {$tool}");
            }
            // The copy buttons are text only.
            $this->assertSame(2, preg_match_all('#<button[^>]*data-action="copy"[^>]*>\s*<span class="btn__spinner" aria-hidden="true"></span>\s*<span class="btn__label">#', $section), "{$locale}: copy buttons without an icon");
            // Functional icons only: the state icons, the info note, field and stepper controls, the external link.
            preg_match_all('#data-icon="([^"]+)"#', $section, $m);
            $this->assertNotEmpty($m[1]);
            // (chevron / chevron-start: the tabs' pointer scroll buttons; carousel-prev / -next: the calendar's months)
            $this->assertSame([], array_values(array_diff(array_unique($m[1]), ['info', 'warning', 'error', 'check', 'chevron-down', 'calendar', 'minus', 'plus', 'external',
                'chevron', 'chevron-start', 'carousel-prev', 'carousel-next'])), "{$locale}: decorative icons");
        }
    }

    /**
     * Design 2.1, Phase 1 (brief §3.2–3.4, D06, D08): the calculators' stylesheet keeps colour on the data marks only
     * (bronze fills the gratuity segment, its table key and the timeline's years after the fifth, never a text, a
     * button or a surface; round 1: no gold at all, since --gold is the hero primary's fill only, so the leave
     * segment, its key and the first five years are neutrals), draws no shadow, uses the type roles (no legacy sizes
     * or weights) and resets the browser's 1px table-cell padding, so every padding of the breakdown table is on the
     * spacing scale (design lint rule 10).
     */
    public function test_design_2_1_calculator_styles_keep_colour_on_the_marks_and_the_scales(): void
    {
        $css = (string) file_get_contents(resource_path('css/d2/sections/tools.css'));

        // Rules without their comments (a comment above a rule would read as part of its selector).
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', (string) preg_replace('#/\*.*?\*/#s', '', $css), $rules, PREG_SET_ORDER);
        $metal = 0;
        foreach ($rules as [, $selector, $body]) {
            if (preg_match('/var\(--(gold|bronze|bronze-700)\)/', $body) === 1) {
                $metal++;
                $this->assertMatchesRegularExpression('/^\s*(\.tool__(seg|key)--[a-z]+,?\s*)+$|^\s*\.tl__fill--\d+\s*$/', $selector, 'gold or bronze only on a data mark: '.trim($selector));
                $this->assertDoesNotMatchRegularExpression('/(^|;)\s*color:/', $body, 'never as text: '.trim($selector));
            }
        }
        $this->assertSame(2, $metal, 'the gratuity segment with its key, and the timeline fill after five years');
        $this->assertStringNotContainsString('var(--gold', $css, 'no gold in the calculators (D06)');
        $this->assertMatchesRegularExpression('/\.tool__seg--leave,\s*\.tool__key--leave \{\s*background: var\(--field-border\);/', $css);
        $this->assertMatchesRegularExpression('/\.tl__fill--21 \{\s*fill: var\(--field-border\);/', $css);

        $this->assertDoesNotMatchRegularExpression('/box-shadow:(?!\s*none\b)/', $css, 'no shadow');
        $this->assertDoesNotMatchRegularExpression('/var\(--(fs-meta|lh-meta|fs-h4|lh-h4|fs-body-l|fw-bold|fw-ui|fw-meta|r-lg|r-md|r-sm|r-pill|surface|surface-sunken|shadow-\d)\)/', $css, 'Design 2.1 tokens only');
        $this->assertMatchesRegularExpression('/\.tool__row > :is\(th, td\) \{[^}]*padding-inline: 0;/', $css, 'the table cells drop the browser\'s 1px padding');
    }

    public function test_styleguide_area_shows_every_state(): void
    {
        foreach (self::LOCALES as $locale) {
            $html = $this->page("/{$locale}/styleguide/");
            $this->assertStringContainsString('id="sg-tools"', $html);
            foreach (['g_empty', 'g_filled', 'g_cap', 'g_under', 'g_part', 'g_temporary', 'g_errors', 'g_out', 'r_empty', 'r_ok', 'r_short', 'r_after', 'r_above', 'r_errors'] as $state) {
                $this->assertStringContainsString('data-sg="tool-'.$state.'"', $html, "{$locale}: {$state}");
            }
            // The RTL specimens render in Arabic on the pages that are not Arabic; the page language is restored.
            if ($locale !== 'ar') {
                $this->assertStringContainsString('data-sg="tool-g-rtl"', $html);
                $this->assertMatchesRegularExpression('#<div dir="rtl" lang="ar">\s*<div class="tool tool--gratuity" id="sg-g-rtl"#', $html);
                $this->assertStringContainsString(e(d2p('tools.gratuity.out.title', [], 'ar')), $html);
                $this->assertStringContainsString(e(d2p('tools.rent.result.empty', [], $locale)), $html);
            }
            $this->assertSame(1, preg_match_all('/<h1[\s>]/', $html), "{$locale}: one H1");
        }
    }

    /**
     * Design 2.1 Phase 2, D31 / D32 / D34: before a result the figure is "—" under the helper line (never a grey
     * "AED 0.00" or "0%"), the breakdown table (whose dashes read like toggles) and the composition bar are hidden,
     * and each table key carries its part, so the script shows only the keys of parts with a value.
     */
    public function test_result_cards_show_a_dash_until_valid_and_no_bar_or_breakdown_before_values(): void
    {
        foreach (self::LOCALES as $locale) {
            $section = $this->section($this->page("/{$locale}/calculators/"));
            $dash = e(d2p('tools.common.empty_value', [], $locale));
            $this->assertSame('—', d2p('tools.common.empty_value', [], $locale));
            // The helper line comes first, then the label and the dash.
            $this->assertMatchesRegularExpression('#data-state="empty"[^>]*>'.preg_quote(e(d2p('tools.gratuity.result.empty', [], $locale)), '#').'</p>.*?<p class="tool__total-label"[^>]*>[^<]*</p>\s*<p class="tool__total t-tabular"><bdi class="tool__amount" data-out="total">'.$dash.'</bdi>#s', $section, $locale);
            $this->assertMatchesRegularExpression('#data-state="empty"[^>]*>'.preg_quote(e(d2p('tools.rent.result.empty', [], $locale)), '#').'</p>.*?<bdi class="tool__amount" data-out="rate">'.$dash.'</bdi>#s', $section, $locale);
            $this->assertStringNotContainsString('0.00', strip_tags($section), "{$locale}: no zero amount before input");
            $this->assertStringContainsString('<div class="tool__bar" aria-hidden="true" data-bar hidden>', $section);
            $this->assertMatchesRegularExpression('#<table class="tool__table tool-js" data-breakdown hidden#', $section);
            $this->assertMatchesRegularExpression('#<p class="tool__figure-line t-tabular" data-out="increase" hidden#', $section);
            foreach (['gratuity', 'leave', 'notice', 'unpaid'] as $part) {
                $this->assertStringContainsString('class="tool__key tool__key--'.$part.'" aria-hidden="true" data-key="'.$part.'"', $section);
            }
        }

        $css = (string) file_get_contents(resource_path('css/d2/sections/tools.css'));
        $this->assertMatchesRegularExpression('/\.tool__key:not\(\[data-on\]\) \{\s*visibility: hidden;/', $css, 'a key shows only for a part with a value');
        $this->assertStringNotContainsString('.tool[data-state="invalid"] .tool__figure', $css, 'the "—" stays under the error line');
        $this->assertMatchesRegularExpression('/\.tool__row > td \{[^}]*font-variant-numeric: tabular-nums lining-nums;[^}]*text-align: end;/', $css, 'amounts end-aligned in tabular figures');
        $js = (string) file_get_contents(resource_path('js/d2/tools/gratuity-view.js'));
        $this->assertStringNotContainsString('m(ok ? r.total : 0)', $js, 'never a formatted zero before a result');
    }

    /**
     * Design 2.1 Phase 2, D36: every calculator date is <x-d2.ui.date>: the platform date input without JavaScript
     * (ISO value, its name), turned by d2Date into a text field with the language's pattern and a calendar button
     * named after the field; the calendar dialog's markup and labels come from the server in the page language.
     */
    public function test_date_fields_are_the_localised_date_picker(): void
    {
        $placeholders = ['en' => 'DD/MM/YYYY', 'ar' => 'يوم/شهر/سنة', 'ru' => 'ДД.ММ.ГГГГ'];
        foreach (self::LOCALES as $locale) {
            $section = $this->section($this->page("/{$locale}/calculators/"));
            $this->assertSame($placeholders[$locale], d2p('controls.date.placeholder', [], $locale));
            $this->assertSame(4, substr_count($section, '<span class="datefield" x-data="d2Date" data-date data-datepicker data-date-placeholder="'.e($placeholders[$locale]).'">'), "{$locale}: four date fields");
            foreach (['gr-start' => ['gr_start', 'startDate', 'tools.gratuity.fields.start'], 'gr-end' => ['gr_end', 'lastWorkingDay', 'tools.gratuity.fields.end'],
                'rent-expiry' => ['rent_expiry', 'expiryDate', 'tools.rent.fields.expiry'], 'rent-notice' => ['rent_notice', 'noticeReceivedDate', 'tools.rent.fields.notice']] as $id => [$name, $key, $label]) {
                $this->assertMatchesRegularExpression('#<input class="input" id="'.$id.'" data-date-input="data-date-input"[^>]* name="'.$name.'" min="1900-01-01" max="2100-12-31" data-input="'.$key.'" type="date">#', $section, "{$locale} {$id}");
                $open = d2p('controls.date.open_named', ['label' => d2p($label, [], $locale)], $locale);
                $this->assertStringContainsString('<button type="button" class="datefield__open" data-date-open aria-label="'.e($open).'" aria-haspopup="dialog" aria-expanded="false"', $section, "{$locale} {$id}");
            }
            $this->assertSame(4, substr_count($section, '<div class="datepicker" role="dialog" aria-modal="true" data-overlay data-datepicker-dialog'), $locale);
            foreach (['previous', 'next', 'month', 'year'] as $key) {
                $this->assertStringContainsString('aria-label="'.e(d2p('controls.date.'.$key, [], $locale)).'"', $section, "{$locale} {$key}");
            }
            $this->assertStringContainsString('<table class="datepicker__grid" role="grid">', $section);
            $this->assertStringNotContainsString('dd/mm/yyyy', $section);
        }

        // The keys exist in every language (and are translated).
        foreach (['placeholder', 'open', 'open_named', 'previous', 'next', 'month', 'year'] as $key) {
            $en = d2p('controls.date.'.$key, [], 'en');
            foreach (['ar', 'ru'] as $locale) {
                $value = d2p('controls.date.'.$key, [], $locale);
                $this->assertNotSame('proposed.d2.controls.date.'.$key, $value, "{$locale}: {$key}");
                $this->assertNotSame($en, $value, "{$locale}: {$key} is translated");
            }
        }

        // No JavaScript: the field is the platform date input and the calendar button is not shown.
        $css = (string) file_get_contents(resource_path('css/d2/components/date.css'));
        $this->assertMatchesRegularExpression('/html:not\(\.js\) \.datefield__open \{\s*display: none;/', $css);
        $this->assertStringContainsString('@import "./components/date.css";', (string) file_get_contents(resource_path('css/d2/app.css')));
        $this->assertDoesNotMatchRegularExpression('/box-shadow:(?!\s*var\(--shadow-overlay\))/', $css, 'only the overlay shadow, on the calendar');
    }

    /**
     * Brief §5.1: the calculators page is linked from home section 09, the Employment Law page (S06, the gratuity
     * estimator) and the Property and Tenancy page (S05, the rent increase check), with proposed labels, and only
     * while pending copy is shown.
     */
    public function test_the_page_is_linked_from_section_09_s06_and_s05_while_pending(): void
    {
        $services = config('summit.services');
        foreach (self::LOCALES as $locale) {
            $page = \App\Design\D2\Calculators::url($locale);
            $home = $this->page("/{$locale}/");
            $start = strpos($home, 'id="consultation-form"');
            $this->assertNotFalse($start);
            $contact = substr($home, $start, (int) strpos($home, '</section>', $start) - $start);
            $this->assertStringContainsString('<p class="tool-link" data-calculators-link="page" data-copy-status="proposed">', $contact, "{$locale}: section 09");
            $this->assertStringContainsString('<a class="link" href="'.$page.'">'.e(d2p('tools.links.page', [], $locale)).'</a>', $contact, $locale);
            $this->assertSame(1, substr_count($home, 'data-calculators-link'), "{$locale}: one link on the home page");

            foreach (['S06' => 'gratuity', 'S05' => 'rent'] as $code => $tool) {
                $html = $this->page("/{$locale}/practice-areas/{$services[$code]}/");
                $this->assertSame(1, substr_count($html, 'data-calculators-link'), "{$locale} {$code}");
                $this->assertStringContainsString('<a class="link" href="'.$page.'#tool-'.$tool.'">'.e(d2p('tools.links.'.$tool, [], $locale)).'</a>', $html, "{$locale} {$code}");
                $this->assertSame($tool, \App\Design\D2\Calculators::forService($code));
            }
            foreach (['S01', 'S04', 'S08'] as $code) {
                $this->assertStringNotContainsString('data-calculators-link', $this->page("/{$locale}/practice-areas/{$services[$code]}/"), "{$locale} {$code}: no calculator");
            }
        }

        config(['design.d2.show_pending' => false]);
        $this->assertStringNotContainsString('data-calculators-link', $this->page('/en/'));
        $this->assertStringNotContainsString('data-calculators-link', $this->page('/en/practice-areas/labor-law/'));
        $this->assertStringNotContainsString('/calculators/', $this->page('/en/practice-areas/real-estate-rental-disputes/'));
    }
}
