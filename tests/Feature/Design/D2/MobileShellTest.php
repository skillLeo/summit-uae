<?php

namespace Tests\Feature\Design\D2;

use App\Design\D2\Hours;
use App\Design\D2\Prefill;
use App\Support\SiteUrl;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Design 2 Phase E, the mobile app shell (owner: mobile-shell): the tab bar, the global sheets, the carousel
 * (<x-d2.ui.carousel>), the sticky consultation bar (<x-d2.shell.sticky-cta>) and the styleguide's mobile
 * section, in all three languages, against the approved imported content. Behaviour (scroll, drag, Back,
 * keyboard) is covered by the Vitest helpers and the Playwright QA run (storage/app/d2/mobile-shell).
 */
final class MobileShellTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['en', 'ar', 'ru'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    private function d2(string $path): string
    {
        return $this->withUnencryptedCookies(['summit_design' => '2'])->get($path)->assertOk()->getContent();
    }

    private function fragment(string $html, string $pattern, string $what): string
    {
        $this->assertSame(1, preg_match($pattern, $html, $m), "{$what} renders");

        return $m[0];
    }

    private function tabbar(string $html): string
    {
        return $this->fragment($html, '/<nav class="tabbar"[^>]*data-tabbar[^>]*>.*?<\/nav>/s', 'the tab bar');
    }

    private function text(string $html): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)));
    }

    /** Brief 2.1 §6 "Mobile": the four approved navigation labels of the homepage header, in its order. */
    public function test_the_tab_bar_has_the_four_approved_navigation_items_in_every_language(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $html = $this->d2("/{$locale}/");
            $bar = $this->tabbar($html);

            $this->assertStringContainsString('aria-label="'.e(proposed('nav.quick')).'"', $bar, $locale);
            // Approved labels (ui(), never shortened, no proposed short label): Home, Practice Areas, Legal
            // Resources, Contact Us. The Design 2.0 Services, Request, Tools and Menu cells are gone.
            $this->assertSame(
                [ui('nav.home'), ui('nav.practice_areas'), ui('nav.resources'), ui('nav.contact')],
                array_map(fn ($label) => $this->text($label), $this->labels($bar)),
                "{$locale}: Home, Practice Areas, Legal Resources, Contact Us in this order",
            );
            $this->assertSame(4, substr_count($bar, '<li class="tabbar__cell">'), "{$locale}: four cells");
            preg_match_all('/<a class="tabbar__item" href="([^"]+)" data-tabbar-item="([a-z]+)"\s*(data-sheet-open="([a-z]+)")?\s*(aria-current="(page|true)")?\s*>/', $bar, $items, PREG_SET_ORDER);
            $this->assertSame(['home', 'practice', 'resources', 'contact'], array_column($items, 2), $locale);
            $this->assertSame([SiteUrl::home(), SiteUrl::page('P02'), SiteUrl::page('P03'), SiteUrl::contact()], array_column($items, 1), $locale);
            // Practice Areas opens the services sheet (a link to Practice Areas without JavaScript); the others are links.
            $this->assertSame(['', 'services', '', ''], array_map(fn ($item) => $item[4] ?? '', $items), $locale);
            // Home is the current page: only its cell is marked, with the 2 px mark element under its label.
            $this->assertSame(['page', '', '', ''], array_map(fn ($item) => $item[6] ?? '', $items), $locale);
            $this->assertSame(1, substr_count($bar, '<span class="tabbar__mark" aria-hidden="true"></span>'), "{$locale}: one mark");
            foreach (['mobile.tab.', 'data-sheet-open="menu"', 'tabbar__item--request', \App\Design\D2\Calculators::url(), '#d2-footer-nav', '@proposed'] as $gone) {
                $this->assertStringNotContainsString($gone, $bar, "{$locale}: {$gone}");
            }
            // No icon at all (brief §3.6 rule 5, D04; the design lint's rule 13): every cell is its label alone.
            $this->assertStringNotContainsString('<svg', $bar, "{$locale}: no icon in the bar");
            $this->assertStringNotContainsString('data-icon=', $bar, $locale);
            // The sheet opener only announces a popup once lib/sheet.js can open it (a plain link without JS).
            $this->assertDoesNotMatchRegularExpression('/data-sheet-open="services"[^>]*aria-haspopup/', $bar, $locale);
            $this->assertStringNotContainsString('style="', $bar, $locale);
            // The header's menu button still opens the menu sheet (the bar has no Menu cell any more).
            $this->assertMatchesRegularExpression('/class="[^"]*d2-header__menu[^"]*"[^>]*data-sheet-open="menu"/', $html, $locale);
        }
    }

    /** Line breaks set by the component between the approved words (never by the font: no re-wrap, no layout shift). */
    public function test_long_tab_labels_are_set_on_two_lines_between_their_words(): void
    {
        $expect = [
            'en' => [['Home'], ['Practice', 'Areas'], ['Legal', 'Resources'], ['Contact Us']],
            'ar' => [['الرئيسية'], ['مناطق', 'الممارسة'], ['المعرفة', 'القانونية'], ['تواصل معنا']],
            'ru' => [['Главная'], ['Направления', 'практики'], ['Правовая', 'информация'], ['Связаться', 'с нами']],
        ];
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $lines = array_map(function (string $label) {
                preg_match_all('/<span class="tabbar__line">([^<]*)<\/span>/', $label, $m);

                return $m[1];
            }, $this->labels($this->tabbar($this->d2("/{$locale}/"))));
            $this->assertSame($expect[$locale], $lines, $locale);
            // The lines put together are the approved label, word for word.
            $this->assertSame([ui('nav.home'), ui('nav.practice_areas'), ui('nav.resources'), ui('nav.contact')], array_map(fn ($l) => implode(' ', $l), $lines), $locale);
        }
    }

    /** "Current marks the matching item only": Practice Areas on service pages, nothing on P04-P06, P08 and the legal pages. */
    public function test_the_tab_bar_marks_only_the_matching_item_as_current(): void
    {
        $expect = [
            'practice-areas/' => ['practice' => 'page'],
            'practice-areas/labor-law/' => ['practice' => 'true'],
            'resources/' => ['resources' => 'page'],
            'case-studies/' => [],
            'faq/' => [],
            'about/' => [],
            'legal-disclaimer/' => [],
            'privacy-policy/' => [],
            'terms-and-conditions/' => [],
        ];
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            foreach ($expect as $path => $current) {
                $bar = $this->tabbar($this->d2("/{$locale}/{$path}"));
                preg_match_all('/data-tabbar-item="([a-z]+)"[^>]*?aria-current="(page|true)"/', $bar, $m, PREG_SET_ORDER);
                $this->assertSame($current, array_column($m, 2, 1), "{$locale} /{$path}");
                $this->assertSame(count($current), substr_count($bar, 'class="tabbar__mark"'), "{$locale} /{$path}: the mark follows aria-current");
            }
        }
    }

    /** Brief 2.1 §5.2, §6: the floating WhatsApp button above the bar (WA01, the official mark, the approved name). */
    public function test_the_floating_whatsapp_button_opens_the_approved_draft(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $html = $this->d2("/{$locale}/faq/");
            // It starts away (modules/tabbar.js shows it once it has looked at the page; no-JS CSS shows it).
            $fab = $this->fragment($html, '/<a class="btn btn--secondary btn--48 icon-btn wa-fab is-away"[^>]*>.*?<\/a>/s', "{$locale} floating WhatsApp button");

            $this->assertStringContainsString('href="'.e(setting('whatsapp_url')).'" target="_blank" rel="noopener noreferrer"', $fab, $locale);
            $this->assertStringContainsString('aria-label="'.e(ui('cta.whatsapp')).' '.e(d2p('controls.new_tab')).'"', $fab, "{$locale}: the approved name");
            $this->assertStringContainsString('data-wa-fab', $fab);
            // The official mark component (Meta's file, or its marked placeholder): lint rule 14's data-brand.
            $this->assertStringContainsString('data-brand="whatsapp"', $fab, $locale);
            $this->assertStringContainsString('class="btn__icon wa-fab__icon" aria-hidden="true"', $fab);
            // Outside the navigation landmark, right after it.
            $this->assertMatchesRegularExpression('/<\/nav>\s*(?:\{\{--.*?--\}\}\s*)?<a class="btn btn--secondary btn--48 icon-btn wa-fab is-away"/s', $html, $locale);
            $this->assertStringNotContainsString('style="', $fab);
        }

        // A static specimen draws it in place, inert; :fab="false" leaves it out.
        $static = Blade::render('<x-d2.shell.tabbar current="home" :static="true" />');
        $this->assertMatchesRegularExpression('/<a class="btn btn--secondary btn--48 icon-btn wa-fab wa-fab--static"[^>]*inert aria-hidden="true" tabindex="-1"/s', $static);
        $this->assertStringNotContainsString('data-wa-fab', $static);
        $this->assertStringNotContainsString('wa-fab', Blade::render('<x-d2.shell.tabbar current="home" :static="true" :fab="false" />'));
    }

    /** @return list<string> the tab labels in order */
    private function labels(string $bar): array
    {
        preg_match_all('/<span class="tabbar__label"[^>]*>(.*?)<\/span>\s*<\/a>/s', $bar, $m);

        return $m[1];
    }

    public function test_the_global_sheets_are_labelled_draggable_dialogs(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $html = $this->d2("/{$locale}/");
            foreach (['menu' => d2p('mobile.sheet.menu'), 'lang' => ui('a11y.choose_language'), 'services' => ui('nav.practice_areas')] as $id => $title) {
                // An overlay (data-overlay: the one overlay shadow and radius 16, exempt in the QA lint).
                $sheet = $this->fragment($html, '/<div class="sheet sheet--'.$id.'" data-overlay="data-overlay" data-sheet="'.$id.'" hidden>.*?<div class="sheet__body">/s', "{$locale} {$id} sheet");
                $this->assertStringContainsString('<div class="sheet__backdrop" data-sheet-close aria-hidden="true"></div>', $sheet);
                $this->assertMatchesRegularExpression('/id="sheet-'.$id.'" role="dialog" aria-modal="true" aria-labelledby="sheet-'.$id.'-title" tabindex="-1"/', $sheet);
                $this->assertMatchesRegularExpression('/<div class="sheet__head"\s+data-sheet-drag\s*>/', $sheet);
                $this->assertStringContainsString('<span class="sheet__handle" aria-hidden="true"></span>', $sheet);
                $this->assertMatchesRegularExpression('/id="sheet-'.$id.'-title"[^>]*>'.preg_quote(e($title), '/').'<\/h2>/', $sheet);
            }
            // Every service of the services sheet links to its page (the approved titles).
            foreach (content()->services() as $service) {
                $this->assertStringContainsString('href="'.SiteUrl::service($service['code']).'"', $html, $locale);
            }
        }
    }

    /** Design 2.1 (brief §3.6 rules 3 and 5, §3.7, D03, D04, D12): text-only menu rows, approved labels, no live status. */
    public function test_the_menu_sheet_is_text_only_with_the_approved_labels(): void
    {
        $approved = ['en' => ['Arabic', 'English', 'Russian'], 'ar' => ['العربية', 'الإنجليزية', 'الروسية'], 'ru' => ['Арабский', 'Английский', 'Русский']];
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $html = $this->d2("/{$locale}/faq/");
            $sheet = $this->fragment($html, '/<div class="sheet sheet--menu".*?(?=<div class="sheet sheet--lang")/s', "{$locale} menu sheet");

            // The seven approved navigation labels, Home first, as plain text rows: no chevron, check mark or icon.
            $nav = $this->fragment($sheet, '/<ul class="menu-sheet__nav" role="list">.*?<\/ul>/s', 'the navigation list');
            preg_match_all('/<a class="menu-sheet__link" href="([^"]+)"\s*(?:aria-current="(page|true)")?\s*>(.*?)<\/a>/s', $nav, $links, PREG_SET_ORDER);
            $this->assertSame(
                [ui('nav.home'), ui('nav.practice_areas'), ui('nav.resources'), ui('nav.case_studies'), ui('nav.faq'), ui('nav.about'), ui('nav.contact')],
                array_map(fn ($link) => $this->text($link[3]), $links),
                $locale,
            );
            $this->assertSame(SiteUrl::page('P05'), $links[4][1]);
            $this->assertSame('page', $links[4][2], "{$locale}: the FAQ page is current");
            $this->assertStringNotContainsString('<svg', $nav, "{$locale}: no icon in a menu item");

            // The language: one row (the approved "Choose language" and the current language with a chevron) that
            // opens the language sheet; without JavaScript a link to the footer's language list. No segmented group.
            $row = $this->fragment($sheet, '/<a class="menu-sheet__langrow" href="#d2-footer-languages" data-sheet-open="lang">.*?<\/a>/s', "{$locale} language row");
            $this->assertStringContainsString(e(ui('a11y.choose_language')), $row);
            $this->assertStringContainsString(e(ui('lang.'.$locale)), $row, "{$locale}: the current language");
            $this->assertStringContainsString('data-icon="chevron-down"', $row);
            $this->assertStringNotContainsString('menu-sheet__langs', $sheet, "{$locale}: no segmented language group");
            $this->assertStringNotContainsString('data-lang-switch', $sheet, "{$locale}: the languages are in their own sheet");

            // Contact rows keep their functional icons (phone, the WhatsApp mark, e-mail) and the WA01 link.
            foreach (['data-icon="phone"', 'data-brand="whatsapp"', 'data-icon="mail"'] as $mark) {
                $this->assertStringContainsString($mark, $sheet, "{$locale}: {$mark}");
            }
            $this->assertStringContainsString('href="'.e(setting('whatsapp_url')).'" target="_blank" rel="noopener noreferrer"', $sheet);
            // The homepage file's hours sentence; the live status stays in the contact block (brief §3.7).
            $this->assertStringContainsString('<p class="menu-sheet__hours">'.e(Hours::sentence('menu')).'</p>', $sheet);
            foreach (['data-hours-status', 'data-icon="clock"', 'data-icon="chevron"', 'data-icon="check"', 'data-icon="external"', ' · '] as $banned) {
                $this->assertStringNotContainsString($banned, $sheet, "{$locale}: {$banned}");
            }
            // The QA cookie is not set here, but the switcher is off by default: no design switch in the sheet.
            $this->assertStringNotContainsString('data-d2-design-switch', $sheet, $locale);

            // The language sheet (brief 2.1 §6: a sheet): the approved labels in the page language (D12), hreflang
            // naming the target, lang the label's own language, the page language current. Text rows only.
            $langSheet = $this->fragment($html, '/<div class="sheet sheet--lang".*?(?=<div class="sheet sheet--services")/s', "{$locale} language sheet");
            preg_match_all('/<a class="lang-sheet__option" href="([^"]+)" hreflang="([a-z]+)" lang="'.$locale.'"\s+data-lang-switch="\2"\s*(aria-current="true")?\s*>(.*?)<\/a>/s', $langSheet, $languages, PREG_SET_ORDER);
            $this->assertSame(['ar', 'en', 'ru'], array_column($languages, 2), $locale);
            $this->assertSame($approved[$locale], array_map(fn ($link) => $this->text($link[4]), $languages), $locale);
            $this->assertSame([$locale], array_column(array_filter($languages, fn ($link) => $link[3] !== ''), 2), "{$locale}: the page language is current");
            foreach ($languages as $link) {
                $this->assertStringStartsWith(SiteUrl::abs('/'.$link[2].'/faq/'), $link[1], "{$locale}: the translated page");
            }
            $this->assertStringNotContainsString('<svg', $this->fragment($langSheet, '/<ul class="lang-sheet__list" role="list">.*?<\/ul>/s', 'the language list'), "{$locale}: no icon in a language row");
        }
    }

    /** Brief 2.1 §6: on phones the design switch sits in the menu sheet, with the floating switch's rules. */
    public function test_the_menu_sheet_holds_the_design_switch_only_when_the_floating_one_would_show(): void
    {
        config(['design.switcher' => true]);
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $html = $this->withUnencryptedCookies(['summit_design' => '2', 'summit_qa' => '0'])->get("/{$locale}/faq/?q=cheque")->assertOk()->getContent();
            $switch = $this->fragment($html, '/<nav class="menu-sheet__group menu-sheet__design" aria-labelledby="sheet-menu-design" data-d2-design-switch>.*?<\/nav>/s', "{$locale} design switch");
            $this->assertStringContainsString('>'.e(__('switcher.label')).'</h3>', $switch);
            preg_match_all('/<a class="menu-sheet__design-option" href="([^"]+)" data-d2-design="(\d)"\s+aria-keyshortcuts="Alt\+\2"\s*(aria-current="true")?\s*>(.*?)<\/a>/s', $switch, $options, PREG_SET_ORDER);
            $this->assertSame(['1', '2'], array_column($options, 2), $locale);
            // Same path and query, ?design=N (the #anchor is added at click time by modules/sheets.js).
            $this->assertSame(["/{$locale}/faq/?q=cheque&amp;design=1", "/{$locale}/faq/?q=cheque&amp;design=2"], array_column($options, 1), $locale);
            $this->assertSame(['', 'aria-current="true"'], array_column($options, 3), "{$locale}: Design 2 is current");
            $this->assertSame([__('switcher.option', ['number' => 1]), __('switcher.option', ['number' => 2])], array_map(fn ($o) => $this->text($o[4]), $options));
            // The floating switch is still injected (tabbar.css hides it below 768 px on Design 2 pages only).
            $this->assertStringContainsString('data-design-switch', $html);

            // The QA cookie hides both, as it always hid the floating one.
            $qa = $this->withUnencryptedCookies(['summit_design' => '2', 'summit_qa' => '1'])->get("/{$locale}/faq/")->assertOk()->getContent();
            $this->assertStringNotContainsString('data-d2-design-switch', $qa, $locale);
            $this->assertStringNotContainsString('data-design-switch', $qa, $locale);
        }

        config(['design.switcher' => false]);
        $this->assertStringNotContainsString('data-d2-design-switch', $this->withUnencryptedCookies(['summit_qa' => '0'])->get('/en/faq/')->assertOk()->getContent());
        // The floating switch's own hook is never reused in the sheet (switch.js takes the first [data-design-switch]).
        $this->assertStringNotContainsString('data-design-switch', (string) file_get_contents(resource_path('views/components/d2/shell/sheets.blade.php')));
        // Design 2's CSS hides the floating switch on phones where the tab bar is; Design 1 never loads it.
        $this->assertMatchesRegularExpression('/@media \(width < 768px\) \{\s*body:has\(> \.tabbar:not\(\.tabbar--static\)\) > \.sds \{\s*display: none !important;/', (string) file_get_contents(resource_path('css/d2/components/tabbar.css')));
    }

    /** Design 2.1 (D03, D04, D09): every full approved summary, no object icon, chevron or arrow. */
    public function test_the_services_sheet_shows_every_full_approved_summary_without_icons(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $html = $this->d2("/{$locale}/practice-areas/labor-law/");
            $list = $this->fragment($html, '/<ul class="services-sheet__list" role="list">.*?<\/ul>/s', "{$locale} services list");

            preg_match_all('/<a class="services-sheet__item" href="([^"]+)"\s*(aria-current="page")?\s*>\s*<span class="services-sheet__title">(.*?)<\/span>\s*(?:<span class="services-sheet__summary">(.*?)<\/span>\s*)?<\/a>/s', $list, $items, PREG_SET_ORDER);
            $services = array_values(content()->services());
            $this->assertCount(count($services), $items, $locale);
            foreach ($services as $i => $service) {
                $this->assertSame(SiteUrl::service($service['code']), $items[$i][1]);
                $this->assertSame($this->text($service['title']), $this->text($items[$i][3]));
                $summary = (string) ($service['hub_summary'] ?? $service['home_summary'] ?? '');
                $this->assertSame($this->text($summary), $this->text($items[$i][4] ?? ''), "{$locale} {$service['code']}: the full approved summary");
                $this->assertSame($service['code'] === 'S06', $items[$i][2] !== '', "{$locale}: only the page's service is current");
            }
            $this->assertStringNotContainsString('<svg', $list, "{$locale}: no object icon or chevron");

            // "View All Practice Areas": the approved label, a secondary button without an arrow.
            $all = $this->fragment($html, '/<a [^>]*services-sheet__all[^>]*>.*?<\/a>/s', 'the all-areas button');
            $this->assertStringContainsString(e(ui('cta.view_all_practice_areas')), $all);
            $this->assertStringContainsString('btn--secondary', $all);
            $this->assertStringNotContainsString('<svg', $all);
        }
    }

    /** Design 2.1 (brief §3.4, §3.5, §3.6 rules 5 and 6, D04, D06): solid white bars, text-only cells, no truncation. */
    public function test_the_mobile_shell_styles_follow_the_design_system(): void
    {
        foreach (['tabbar', 'sticky-cta'] as $file) {
            $css = (string) file_get_contents(resource_path("css/d2/components/{$file}.css"));
            foreach (['backdrop-filter', '--glass', '--metal', 'box-shadow', '-gradient(', 'line-clamp', 'text-overflow', 'has-metal-in-view', '--font-mono'] as $banned) {
                $this->assertStringNotContainsString($banned, $css, "{$file}.css: {$banned}");
            }
        }
        $tabbar = (string) file_get_contents(resource_path('css/d2/components/tabbar.css'));
        $this->assertMatchesRegularExpression('/\.tabbar:not\(\.tabbar--static\) \{[^}]*background: var\(--canvas\);/s', $tabbar, 'a solid white bar');
        // No raised Request circle (D04; a 50 % radius is off §3.4): nothing styles one, the hidden bar slides out by
        // its own height, and no rule takes the Request cell's focus ring away (it is a text cell like the others).
        $this->assertStringNotContainsString('tabbar__fab', $tabbar, 'no Request circle');
        $this->assertStringNotContainsString('--tab-fab', $tabbar, 'no Request circle');
        $this->assertMatchesRegularExpression('/\.tabbar\.is-hidden \{\s*transform: translate3d\(0, 100%, 0\);\s*\}/', $tabbar, 'the hidden bar slides out by its own height');
        $this->assertDoesNotMatchRegularExpression('/\.tabbar__item--request[^{]*\{[^}]*outline: none/', $tabbar, 'the Request cell keeps the focus ring');
        // The styleguide's safe-area specimen pads with a spacing step (§3.4, lint rule 10), not a raw 34 px.
        $this->assertMatchesRegularExpression('/\.tabbar--static\.tabbar--safe \{\s*--safe-bottom: var\(--s-8\);\s*padding-block-end: var\(--safe-bottom\);/', $tabbar, 'the safe-area specimen on the spacing scale');
        $this->assertMatchesRegularExpression('/\.tabbar__item \{[^}]*color: var\(--ink-2\);\s*font-size: var\(--fs-ui\);\s*font-weight: var\(--fw-medium\);\s*line-height: var\(--lh-ui\);/s', $tabbar, 'labels in the UI label role, in the navigation colour');
        // The current page: ink at 600 and a 2 px ink mark under its label (as the header's navigation row), never
        // colour alone. A real element drawn as a border (forced colours paint backgrounds as Canvas), named for the
        // page change so it slides from the old item to the new one (base/motion.css), on phones only.
        $this->assertMatchesRegularExpression('/\.tabbar__mark \{[^}]*border-block-start: calc\(2 \* var\(--line-w\)\) solid var\(--ink\);/s', $tabbar, 'the current-page mark');
        $this->assertDoesNotMatchRegularExpression('/\.tabbar__mark \{[^}]*background:/s', $tabbar, 'not a background (erased by forced colours)');
        $this->assertMatchesRegularExpression('/\.tabbar__item \{.*?&\[aria-current\] \{\s*color: var\(--ink\);\s*font-weight: var\(--fw-semibold\);/s', $tabbar, 'the current label');
        $this->assertMatchesRegularExpression('/\.tabbar:not\(\.tabbar--static\) \.tabbar__mark \{\s*view-transition-name: d2-tab-mark;/', $tabbar, 'the sliding mark');
        // Four equal cells; a word is never cut (cells never go below their longest word; two balanced lines).
        $this->assertMatchesRegularExpression('/\.tabbar__list \{[^}]*display: grid;\s*grid-template-columns: repeat\(4, minmax\(min-content, 1fr\)\);/s', $tabbar, 'four equal columns set by the list (no shift while the page arrives)');
        $this->assertMatchesRegularExpression('/\.tabbar__line \{\s*display: block;\s*white-space: nowrap;/', $tabbar, 'lines set by the component, never re-wrapped by the font');
        $this->assertStringNotContainsString('--gold', $tabbar, 'never gold');
        $sticky = (string) file_get_contents(resource_path('css/d2/components/sticky-cta.css'));
        $this->assertMatchesRegularExpression('/\.js \.sticky-cta:not\(\.sticky-cta--static\) \{[^}]*background: var\(--canvas\);/s', $sticky, 'a solid white bar');
        // Both 1 px top lines are pseudo-element borders, so forced colours keep the bars' edges.
        $this->assertMatchesRegularExpression('/\.tabbar::before \{[^}]*border-block-start: var\(--line-w\) solid var\(--line\);/s', $tabbar, 'the tab bar top line');
        $this->assertMatchesRegularExpression('/\.sticky-cta::before \{[^}]*border-block-start: var\(--line-w\) solid var\(--line\);/s', $sticky, 'the sticky bar top line');
        // One bottom bar on phones (brief 2.1 §6): never drawn where the tab bar exists, so the two never stack.
        $this->assertMatchesRegularExpression('/body:has\(> \.tabbar:not\(\.tabbar--static\)\) > \.sticky-cta \{\s*display: none;/', $sticky, 'no sticky bar with the tab bar');
        $this->assertStringNotContainsString('body:has(> .tabbar.is-hidden) > .sticky-cta', $sticky, 'no dock with the tab bar any more');
    }

    public function test_the_carousel_renders_a_progressive_rail(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $html = Blade::render(
                '<x-d2.ui.carousel class="cases__rail" labelledby="cases-title" until="768" list-class="cases__grid"><li>A</li><li>B</li></x-d2.ui.carousel>',
            );

            $this->assertMatchesRegularExpression('/<div class="carousel carousel--until-768 cases__rail" x-data="d2Carousel" data-carousel\s+data-until="768"\s+data-labelledby="cases-title"\s+data-roledescription="'.preg_quote(e(d2p('mobile.carousel.roledescription')), '/').'"/', $html, $locale);
            $this->assertStringContainsString('<ul class="carousel__track cases__grid" role="list" data-carousel-track>', $html);
            // The dots are drawn by JavaScript from this template; the group stays hidden until then.
            $this->assertStringContainsString('aria-label="'.e(d2p('mobile.carousel.dots')).'"', $html);
            $this->assertStringContainsString('data-dot-label="'.e(d2p('mobile.carousel.dot')).'" hidden', $html);
            $this->assertStringContainsString(':n', d2p('mobile.carousel.dot'));
            $this->assertStringContainsString(':total', d2p('mobile.carousel.dot'));
            // Carousel semantics are added by JavaScript only while it is a carousel (a plain list on desktop).
            $root = $this->fragment($html, '/<div class="carousel [^>]*>/', 'the carousel root');
            $this->assertDoesNotMatchRegularExpression('/\s(role|aria-label|aria-labelledby|aria-roledescription)=/', $root);
            $this->assertStringNotContainsString('style="', $html);
        }

        // The caller's own hooks land on the <ul>; class, role, style and the track hook stay the component's.
        $html = Blade::render('<x-d2.ui.carousel label="X" until="768" list-class="svc__grid" :list-attributes="$attrs"><li>A</li></x-d2.ui.carousel>', ['attrs' => ['data-svc-grid' => true, 'x-ref' => 'list', 'style' => 'color:red', 'role' => 'menu']]);
        $this->assertMatchesRegularExpression('/<ul class="carousel__track svc__grid" data-svc-grid="data-svc-grid" x-ref="list" role="list" data-carousel-track>/', $html);
        $this->assertStringNotContainsString('style="', $html);
        $this->assertStringNotContainsString('role="menu"', $html);

        // Unknown breakpoints are ignored (always a carousel); no dots on request.
        $html = Blade::render('<x-d2.ui.carousel label="X" until="999" :dots="false"><li>A</li></x-d2.ui.carousel>');
        $this->assertStringContainsString('<div class="carousel" x-data="d2Carousel" data-carousel', $html);
        $this->assertStringContainsString('data-label="X"', $html);
        $this->assertStringNotContainsString('data-until', $html);
        $this->assertStringNotContainsString('carousel__dots', $html);
        $this->assertStringContainsString('<ul class="carousel__track" role="list" data-carousel-track>', $html);
    }

    public function test_the_sticky_bar_carries_the_page_prefill_and_renders_at_body_level(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $html = Blade::render(
                '<x-d2.shell.sticky-cta target="#service-hero" until="#service-cta" :prefill="$prefill" /><main></main>@stack(\'sheets\')',
                ['prefill' => ['service' => 'S06', 'situation' => ['S06-1'], 'unknown' => 'x', 'tool' => '']],
            );

            // Pushed to the layout's "sheets" stack: printed where the stack is (body level), not in place.
            $this->assertMatchesRegularExpression('/<main><\/main>\s*<div class="sticky-cta" data-sticky-cta/', $html, $locale);
            $this->assertStringContainsString('data-target="#service-hero"', $html);
            $this->assertStringContainsString('data-until="#service-cta"', $html);
            // Only known prefill codes travel (arrays, unknown keys and empty values are dropped).
            $this->assertStringContainsString('data-prefill="'.e('{"service":"S06"}').'"', $html);
            $this->assertStringContainsString('href="'.e(Prefill::url(['service' => 'S06'], $locale)).'"', $html);
            $this->assertStringContainsString('data-sticky-cta-link', $html);
            // The Ink button, full width (gold stays the hero primary's; brief §3.5, D06).
            $this->assertMatchesRegularExpression('/<a class="btn btn--ink btn--\d+ btn--full sticky-cta__button"/', $html, $locale);
            $this->assertSame(1, substr_count($html, e(ui('cta.request_consultation'))), "{$locale}: the approved CTA label");
            $this->assertStringNotContainsString('style="', $html);
        }

        // The styleguide specimen is drawn in place and inert.
        $static = Blade::render('<x-d2.shell.sticky-cta :static="true" />');
        $this->assertStringContainsString('<div class="sticky-cta sticky-cta--static" inert aria-hidden="true">', $static);
        $this->assertStringNotContainsString('data-sticky-cta', $static);
        $this->assertStringContainsString('href="'.e(SiteUrl::consultation()).'"', $static);
    }

    public function test_the_styleguide_shows_every_mobile_specimen_and_a_live_bar_outside_the_content(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $html = $this->d2("/{$locale}/styleguide/");
            $section = $this->fragment($html, '/<section class="sg-section" id="sg-mobile">.*?<\/section>\s*(?=<section class="sg-section"|<\/div>)/s', "{$locale} mobile section");

            foreach (['class="tabbar tabbar--static"', 'class="tabbar tabbar--static tabbar--safe"', 'wa-fab wa-fab--static', 'class="sticky-cta sticky-cta--static"', 'data-sheet-open="lang"', 'class="carousel sg-carousel"', 'class="carousel carousel--until-768 carousel-demo__bleed"', 'id="sg-mobile-cta-start"', 'id="sg-mobile-cta-end"', 'src="/d2/icons/icon-192.png"', 'src="/d2/icons/maskable-192.png"'] as $needle) {
                $this->assertStringContainsString($needle, $section, "{$locale}: {$needle}");
            }
            // The four tab bar specimens (home, practice, no current cell, safe area) are text only, as the live bar;
            // one floating WhatsApp button specimen.
            preg_match_all('/<nav class="tabbar tabbar--static[^"]*"[^>]*>.*?<\/nav>/s', $section, $bars);
            $this->assertCount(4, $bars[0], "{$locale}: the tab bar specimens");
            foreach ($bars[0] as $bar) {
                $this->assertStringNotContainsString('<svg', $bar, "{$locale}: a specimen without icons");
                $this->assertSame(4, substr_count($bar, '<span class="tabbar__label"'), "{$locale}: four labels");
            }
            $this->assertSame(1, substr_count($section, 'wa-fab--static'), "{$locale}: one floating button specimen");
            // The manifest facts come from this language's real manifest file.
            $this->assertStringContainsString(e(ui('brand.name')), $section);
            $this->assertStringContainsString(e(d2p('pwa.short_name')), $section);
            // The live bar is pushed after <main>, observing the section's markers.
            $after = substr($html, (int) strpos($html, '</main>'));
            $this->assertMatchesRegularExpression('/<div class="sticky-cta" data-sticky-cta\s+data-target="#sg-mobile-cta-start"\s+data-until="#sg-mobile-cta-end"/', $after, $locale);
        }
    }

    public function test_every_proposed_shell_string_exists_in_all_three_languages(): void
    {
        $keys = ['mobile.sheet.menu', 'mobile.carousel.roledescription', 'mobile.carousel.dots', 'mobile.carousel.dot', 'pwa.short_name'];
        foreach ($keys as $key) {
            $values = [];
            foreach (self::LOCALES as $locale) {
                $value = d2p($key, [], $locale);
                $this->assertNotSame('proposed.d2.'.$key, $value, "{$locale}: {$key} exists");
                $this->assertNotSame('', trim($value), "{$locale}: {$key} is not empty");
                $values[$locale] = $value;
            }
            // Arabic is always its own text; Russian too, except the Latin-script brand short name.
            $this->assertNotSame($values['en'], $values['ar'], "{$key}: Arabic draft");
            if ($key !== 'pwa.short_name') {
                $this->assertNotSame($values['en'], $values['ru'], "{$key}: Russian draft");
            }
        }
        // Brief 2.1 §6: the tab bar uses the approved navigation labels only; its Design 2.0 proposed labels are retired.
        foreach (self::LOCALES as $locale) {
            foreach (['services', 'request', 'tools', 'menu'] as $tab) {
                $this->assertSame('proposed.d2.mobile.tab.'.$tab, d2p("mobile.tab.{$tab}", [], $locale), "{$locale}: mobile.tab.{$tab} is gone");
            }
        }
    }
}
