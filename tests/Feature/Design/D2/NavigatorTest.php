<?php

namespace Tests\Feature\Design\D2;

use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Design 2 Matter Navigator (home #navigator, upgrade spec §12 C5): same data and logic as Design 1
 * (44 approved situations, ?navigator=S05-2 without JavaScript, summit.navigator.v1, #navigator=S05-2),
 * night workbench markup, proposed copy in all three languages. Design 2.1 Phase 1: no mono kicker or group
 * numbers (D01), no decorative icons or arrows (D03, D04), Ink Request buttons (D06).
 */
final class NavigatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    private function home(string $locale = 'en', string $query = ''): string
    {
        return $this->withUnencryptedCookies(['summit_design' => '2'])
            ->get("/{$locale}/".$query)
            ->assertOk()
            ->getContent();
    }

    /** The section markup only (the hero tool card elsewhere on the page also lists the situations). */
    private function section(string $html): string
    {
        // The dark band under the contract's names (tokens.md §6: .section--dark with .on-dark; the Design 2.0
        // .section--night / .on-night are aliases only).
        $start = strpos($html, '<section class="section section--dark on-dark navigator"');
        $this->assertNotFalse($start, 'the navigator section renders on the dark band');
        $end = strpos($html, '</section>', strpos($html, 'data-navigator-config', $start));

        return substr($html, $start, $end - $start);
    }

    /** @return array<string, mixed> */
    private function config(string $section): array
    {
        $this->assertSame(1, preg_match('#<script type="application/json" data-navigator-config>(.*?)</script>#s', $section, $m));

        return json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_night_workbench_lists_the_44_approved_situations_in_every_language(): void
    {
        foreach (['en', 'ar', 'ru'] as $locale) {
            $section = $this->section($this->home($locale));
            $this->assertStringContainsString('id="navigator"', $section);
            $this->assertStringContainsString('aria-labelledby="navigator-title"', $section);
            $this->assertStringContainsString('x-data="d2Navigator"', $section);
            $this->assertSame(44, preg_match_all('/name="navigator" value="S\d{2}-[1-9]"/', $section), $locale);
            $this->assertSame(11, preg_match_all('/data-nav-group="S\d{2}"/', $section), $locale);
            $this->assertSame(1, preg_match_all('/<h2[\s>]/', $section), "{$locale}: one H2");
            $this->assertStringNotContainsString(' checked', substr($section, 0, (int) strpos($section, 'data-nav-file')), "{$locale}: nothing chosen");
            // Nothing chosen: the empty card is shown, every file waits in a template, the actions hide.
            $this->assertSame(11, substr_count($section, '<template data-nav-file-template='), $locale);
            $this->assertMatchesRegularExpression('/class="nav-file__empty" data-nav-file-empty\s*>/', $section);
            $this->assertMatchesRegularExpression('/data-nav-file-actions[^>]*hidden/', $section);
        }
    }

    public function test_the_approved_copy_comes_from_navigator_data(): void
    {
        $section = $this->section($this->home('en', '?navigator=S05-2'));
        foreach (['Property and Tenancy Disputes', 'Documents That Help Us Get Started', 'Useful First Steps', 'Mistakes to Avoid',
            'The sale or tenancy agreement and its schedules', 'Guide to Delayed Property Handover', 'Explore This Service',
            'Read the Guide', 'Request a Legal Consultation', 'This service may be relevant if:', 'Debts and Cheques'] as $approved) {
            $this->assertStringContainsString(e($approved), $section, $approved);
        }
        // The Design 1 proposed heading by default; the approved alternative behind the same switch.
        $this->assertStringContainsString('Where Do You Stand?', $section);
        config(['summit.features.navigator_heading' => 'approved']);
        $this->assertStringContainsString('Questions We Help You Answer', $this->section($this->home('en')));
    }

    public function test_without_javascript_the_get_form_renders_the_chosen_file(): void
    {
        $section = $this->section($this->home('en', '?navigator=S05-2'));
        $base = rtrim(url('/'), '/');
        $this->assertStringContainsString('action="'.$base.'/en/#navigator"', $section);
        $this->assertMatchesRegularExpression('/value="S05-2" checked="checked" type="radio"/', $section);
        // S05 is live DOM (not a template) with the situation quote; the other ten stay in templates.
        $this->assertSame(10, substr_count($section, '<template data-nav-file-template='));
        $this->assertStringNotContainsString('data-nav-file-template="S05"', $section);
        $this->assertMatchesRegularExpression('/<article class="nav-file__service" id="navigator-file-S05" data-file="S05" aria-labelledby="navigator-file-S05-title"\s*>/', $section);
        $this->assertStringContainsString('<q data-nav-quote>After purchase, you discovered a property defect that was not mentioned in the contract.</q>', $section);
        $this->assertMatchesRegularExpression('/class="nav-file__empty" data-nav-file-empty\s+hidden/', $section);
        // Request without JavaScript: the consultation form with the prefill query (Prefill::url()).
        $this->assertStringContainsString('href="'.$base.'/en/?service=S05&amp;situation=S05-2#consultation-form"', $section);
        $this->assertStringContainsString('data-nav-submit', $section);
        $this->assertStringContainsString('Show the starting file', $section);
    }

    public function test_unknown_situations_are_ignored(): void
    {
        foreach (['?navigator=S12-1', '?navigator=S05-9', '?navigator=%3Cscript%3E', '?navigator='] as $query) {
            $section = $this->section($this->home('en', $query));
            $this->assertSame(11, substr_count($section, '<template data-nav-file-template='), $query);
            $this->assertStringNotContainsString('checked="checked"', $section, $query);
            $this->assertStringNotContainsString('<script>', $section, $query);
        }
    }

    public function test_the_browser_config_keeps_design_one_storage_and_ships_every_plural_form(): void
    {
        $en = $this->config($this->section($this->home('en')));
        $this->assertSame('summit.navigator.v1', $en['storageKey']);
        $this->assertSame(rtrim(url('/'), '/').'/en/#consultation-form', $en['request']);
        $this->assertSame('en-GB', $en['dateLocale']);
        $this->assertCount(45, $en['matches']);
        $this->assertSame('1 situation', $en['matches'][1]);
        $this->assertSame('4 situations', $en['matches'][4]);
        $this->assertSame("\u{2068}2\u{2069} of \u{2068}4\u{2069} documents ready", $en['progress'][4][2]);
        $this->assertSame('Link copied', $en['labels']['linkCopied']);
        $this->assertSame('Unable to copy the link. You can copy it from the address bar.', $en['labels']['copyFailed']);
        $this->assertSame('Starting file: :service', $en['labels']['updated']);

        $ru = $this->config($this->section($this->home('ru')));
        $this->assertSame(['1 ситуация', '2 ситуации', '5 ситуаций', '21 ситуация'], [$ru['matches'][1], $ru['matches'][2], $ru['matches'][5], $ru['matches'][21]]);

        $ar = $this->config($this->section($this->home('ar')));
        $this->assertSame(['لا توجد حالات', 'حالة واحدة', 'حالتان', '3 حالات', '11 حالة'], [$ar['matches'][0], $ar['matches'][1], $ar['matches'][2], $ar['matches'][3], $ar['matches'][11]]);
        $this->assertStringContainsString('مستندات جاهزة', $ar['progress'][4][2]);
        $this->assertSame('ar-AE', $ar['dateLocale']);
    }

    public function test_arabic_isolates_latin_runs_and_numbers(): void
    {
        $section = $this->section($this->home('ar', '?navigator=S03-1'));
        $this->assertStringContainsString('<bdi dir="ltr">TxID</bdi>', $section);
        // The progress line: the numbers are <bdi>, the sentence keeps the page direction.
        $this->assertMatchesRegularExpression('#<span class="progress__label"[^>]*><bdi>0</bdi> من <bdi>4</bdi> مستندات جاهزة</span>#u', $section);
    }

    public function test_the_mobile_sheet_is_pushed_to_body_level(): void
    {
        $html = $this->home('en', '?navigator=S06-1');
        $main = strpos($html, '</main>');
        $sheet = strpos($html, 'data-sheet="navigator"');
        $this->assertNotFalse($sheet);
        $this->assertGreaterThan($main, $sheet, 'the sheet renders after </main> (@push sheets)');
        $this->assertStringContainsString('id="sheet-navigator-title"', $html);
        $this->assertStringContainsString('data-nav-sheet-slot', $html);
        $href = preg_quote(rtrim(url('/'), '/').'/en/?service=S06&amp;situation=S06-1#consultation-form', '~');
        $this->assertMatchesRegularExpression('~data-nav-sheet-actions>\s*<a class="btn btn--ink btn--48 btn--full" href="'.$href.'"~', $html);
    }

    /** Design 2.1 (D01, D03, D04, D06): nothing decorative in the workbench or the starting file; the functional marks stay. */
    public function test_no_eyebrows_numbers_decorative_icons_arrows_or_gold(): void
    {
        foreach (['en', 'ar', 'ru'] as $locale) {
            $section = $this->section($this->home($locale, '?navigator=S05-2'));
            foreach (['nav-file__kicker', 't-eyebrow', 'nav-group__num', 'nav-file__icon', 'nav-empty__icon', 'nav-link__arrow', 'nav-file__empty-icon',
                'data-metal-anchor', 'btn--signature', 'btn--gold'] as $gone) {
                $this->assertStringNotContainsString($gone, $section, "{$locale}: no {$gone}");
            }
            preg_match_all('/data-icon="([^"]+)"/', $section, $icons);
            // search field, disclosure chevrons, and the check and cross marks of the useful first steps and the
            // mistakes to avoid (brief §3.6 rule 5); the chip rail has no previous / next buttons since D30
            $this->assertSame([], array_values(array_diff(array_unique($icons[1]), ['search', 'chevron-down', 'check', 'cross'])), "{$locale}: functional icons only");
            $this->assertSame(4, preg_match_all('/data-icon="check"/', $this->between($section, 'id="navigator-file-S05"', '</article>')), "{$locale}: one check per useful first step");
            $this->assertMatchesRegularExpression('/<button class="btn btn--secondary btn--48"[^>]*data-nav-action="print"[^>]*>.*?'.preg_quote(e(__('proposed.d2.navigator.print', [], $locale)), '/').'/s', $section, "{$locale}: Print is a labelled button");
            $this->assertMatchesRegularExpression('/<button class="btn btn--secondary btn--48"[^>]*data-nav-action="copy"[^>]*>.*?'.preg_quote(e(__('proposed.d2.navigator.copy_link', [], $locale)), '/').'/s', $section, "{$locale}: Copy link is a labelled button");
            // The group headings are the approved service titles only (no numbers before them).
            $this->assertDoesNotMatchRegularExpression('/<p class="nav-group__label" id="[^"]+">\s*<span[^>]*>\d{2}<\/span>/', $section);
        }
        $css = (string) file_get_contents(resource_path('css/d2/sections/navigator.css'));
        foreach (['--font-mono', '--font-doc', 'font-style: italic', 'text-transform', '--shadow-4', '--ring-pin', '--gold', '.d2-header'] as $gone) {
            $this->assertStringNotContainsString($gone, $css, "navigator.css: no {$gone}");
        }
        // Alignment (brief §7.1 rule 12, §3.4): link cards side by side keep their titles on one line (content from the
        // top), and Clear ticks, the kit's text button without side padding, ends on the card's content edge.
        $link = substr($css, (int) strpos($css, '  .nav-link {'), 300);
        $this->assertStringContainsString('align-content: start;', substr($link, 0, (int) strpos($link, '& .card__title')));
        $this->assertStringNotContainsString('.nav-file__clear {', $css, 'no negative margin on Clear ticks');
    }

    /**
     * Design 2.1 D29 and D30: the list scrolls with the page (no scroll box, no scroller hook), the starting file is
     * sticky 96 px from the top (the header plus 32 px; navigator/sticky.js moves --nav-card-top for a taller file),
     * the practice-area chips wrap from 768 px and scroll sideways only on phones, with faded edges and no button over
     * a label; the group headings are the approved titles in the 16/600 role.
     */
    public function test_the_list_scrolls_with_the_page_the_card_is_sticky_and_no_chip_is_cut(): void
    {
        foreach (['en', 'ar', 'ru'] as $locale) {
            $section = $this->section($this->home($locale));
            foreach (['data-nav-scroller', 'data-nav-scroll=', 'nav-chips__arrow', 'carousel-prev', 'carousel-next'] as $gone) {
                $this->assertStringNotContainsString($gone, $section, "{$locale}: no {$gone}");
            }
            $this->assertMatchesRegularExpression('/<div class="nav-chips" data-nav-chips-wrap>\s*<div class="nav-chips__rail" data-nav-rail>\s*<div class="nav-chips__list" role="toolbar"/', $section, "{$locale}: the chips toolbar sits alone in its rail");
            $this->assertSame(12, preg_match_all('/data-nav-chip="/', $section), "{$locale}: All plus the 11 practice areas");
        }

        $css = (string) file_get_contents(resource_path('css/d2/sections/navigator.css'));
        $desktop = substr($css, (int) strpos($css, '/* ================================================================ desktop workbench */'));
        $desktop = substr($desktop, 0, (int) strpos($desktop, '/* ================================================================ "Your starting file" card */'));
        $this->assertStringNotContainsString('overflow-y: auto', $desktop, 'D29: no scroll box around the list');
        $this->assertStringNotContainsString('max-block-size', $desktop, 'D29: the list column is as tall as the list');
        $this->assertMatchesRegularExpression('/position: sticky;\s*inset-block-start: var\(--nav-card-top, calc\(var\(--header-h\) \+ var\(--s-8\)\)\);/', $desktop, 'D29: the card is sticky at 64 + 32 = 96 px');
        $this->assertMatchesRegularExpression('/\.nav-group__label \{[^}]*font-size: var\(--fs-minor\);[^}]*font-weight: var\(--fw-semibold\);/s', $css, 'D29: group headings in the 16/600 role');

        $chips = substr($css, (int) strpos($css, '  .nav-chips__list {'));
        $chips = substr($chips, 0, (int) strpos($chips, '/* ================================================================ list */'));
        $this->assertMatchesRegularExpression('/\.nav-chips__list \{\s*display: flex;\s*flex-wrap: wrap;/', $chips, 'D30: the chips wrap by default');
        $phone = substr($chips, (int) strpos($chips, '@media (width < 768px)'));
        $this->assertStringContainsString('overflow-x: auto;', $phone, 'D30: the rail scrolls only below 768 px');
        $this->assertStringContainsString('flex-wrap: nowrap;', $phone);
        $this->assertStringContainsString('mask-image: var(--mask-fade-both);', $phone, 'D30: faded edges');
        $this->assertStringContainsString('scroll-snap-align: start;', $phone);
    }

    private function between(string $html, string $from, string $to): string
    {
        $start = strpos($html, $from);
        $this->assertNotFalse($start, "found {$from}");
        $end = strpos($html, $to, $start + strlen($from));
        $this->assertNotFalse($end, "found {$to} after {$from}");

        return substr($html, $start, $end - $start + strlen($to));
    }

    public function test_proposed_copy_is_marked_and_exists_in_three_languages(): void
    {
        $section = $this->section($this->home('en'));
        $this->assertGreaterThan(10, substr_count($section, 'data-copy-status="proposed"'));

        $files = [];
        foreach (['en', 'ar', 'ru'] as $locale) {
            $files[$locale] = require lang_path("{$locale}/proposed-d2/navigator.php");
        }
        $this->assertSame(array_keys($files['en']), array_keys($files['ar']));
        $this->assertSame(array_keys($files['en']), array_keys($files['ru']));
        foreach ($files['en'] as $key => $english) {
            $this->assertNotSame($english, $files['ar'][$key], "ar {$key} is translated");
            $this->assertNotSame($english, $files['ru'][$key], "ru {$key} is translated");
            $this->assertDoesNotMatchRegularExpression('/[A-Za-z]{3,}/', preg_replace('/:[a-z]+/', '', $files['ar'][$key]), "ar {$key} has no English");
        }
        // Plurals carry every form: English 2, Russian 3, Arabic 6.
        foreach (['matches', 'progress'] as $key) {
            $this->assertCount(2, explode('|', $files['en'][$key]), "en {$key}");
            $this->assertCount(3, explode('|', $files['ru'][$key]), "ru {$key}");
            $this->assertCount(6, explode('|', $files['ar'][$key]), "ar {$key}");
        }
    }

    /**
     * Design 2.1 round 2 (D09, brief §3.6 rule 8: never cut a placeholder). The search field shows its own name
     * ("Search situations") as its placeholder: the visible text is the accessible name (WCAG 2.5.3) and it stays
     * whole in the narrowest phone field (320 px: 190 px of text room; EN 130, RU 158, AR 103 px measured). The longer
     * example line ("Type a word, for example cheque", cut below 390 px) is deleted in all three languages.
     */
    public function test_the_search_placeholder_is_the_field_name_and_stays_whole(): void
    {
        foreach (['en', 'ar', 'ru'] as $locale) {
            $section = $this->section($this->home($locale));
            $name = __('proposed.d2.navigator.search_label', [], $locale);
            $this->assertNotSame('proposed.d2.navigator.search_label', $name, $locale);
            $this->assertStringContainsString('<label class="field__label" for="navigator-search">'.e($name).'</label>', $section, "{$locale}: the hidden label names the field");
            $this->assertMatchesRegularExpression('#<input class="input" id="navigator-search" placeholder="'.preg_quote(e($name), '#').'"[^>]*data-nav-search#', $section, "{$locale}: the placeholder is the field's name");
            $this->assertSame(1, substr_count($section, 'placeholder="'), "{$locale}: one placeholder in the workbench");
            $this->assertLessThanOrEqual(18, mb_strlen($name), "{$locale}: a short line (the 320 px phone field holds about 22 characters)");
            $this->assertArrayNotHasKey('search_placeholder', require lang_path("{$locale}/proposed-d2/navigator.php"), "{$locale}: the cut example line is deleted");
        }
    }

    public function test_no_banned_claims_and_no_design_one_markup(): void
    {
        foreach (['en', 'ar', 'ru'] as $locale) {
            $section = mb_strtolower($this->section($this->home($locale, '?navigator=S01-1')));
            foreach (['24/7', 'free consultation', 'law firm', 'years of experience', 'award', 'success rate', 'rated'] as $banned) {
                $this->assertStringNotContainsString($banned, $section, "{$locale}: {$banned}");
            }
            $this->assertStringNotContainsString('x-data="navigator"', $section, 'Design 1 component');
            $this->assertStringNotContainsString('style="', $section, 'CSP: no style attributes');
            $this->assertStringNotContainsString('<x-', $section);
        }
    }

    public function test_styleguide_lists_the_navigator_area(): void
    {
        $html = $this->withUnencryptedCookies(['summit_design' => '2'])->get('/en/styleguide/')->assertOk()->getContent();
        $this->assertStringContainsString('id="sg-navigator"', $html);
        $this->assertStringContainsString('href="#sg-navigator"', $html);
        $this->assertStringNotContainsString('id="navigator"', $html, 'specimens never reuse the section ids');

        // Round 2 (D09): the three search specimens carry the live placeholder, and they are as wide as the live field.
        // Below 768 px the Navigator's night frames are the live band (edge to edge, the container's side padding; in
        // the styleguide's layer, where .sg-night is drawn); the chip-state tools take the list column from 1280 px only.
        foreach (['sg-nav-b', 'sg-nav-c', 'sg-nav-t'] as $prefix) {
            $this->assertMatchesRegularExpression('#<input class="input[^"]*" id="'.$prefix.'-search" placeholder="Search situations"#', $html, $prefix);
        }
        $this->assertSame(3, substr_count($html, 'class="sg-night on-night nav-specimen"'), 'the three night frames of the area are Navigator specimens');
        $css = (string) file_get_contents(resource_path('css/d2/sections/navigator.css'));
        $this->assertMatchesRegularExpression('/@layer pages \{\s*@media \(width < 768px\) \{\s*\.sg-night\.nav-specimen \{\s*margin-inline: calc\(-1 \* var\(--pad-x\)\);\s*padding-inline: var\(--pad-x\);\s*border-radius: 0;\s*\}/', $css);
        $this->assertMatchesRegularExpression('/@media \(width >= 1280px\) \{\s*\.nav-specimen__tools \{\s*max-inline-size: var\(--nav-col\);/', $css);
    }
}
