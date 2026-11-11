@props([
    'current' => null,   // navCurrent: home | practice | resources | cases | faq | about | contact
    'pageKey' => null,   // 'error' on error pages (language links then go to each home page)
])
{{--
    Design 2 global sheets, rendered once by the layout (<x-d2.shell.sheets :current>) at body level.
      menu      the approved navigation (the seven labels, Home first), one row that opens the language sheet (the
                approved "Choose language" and the current language's approved label with a chevron, as the
                header's language trigger), the contact actions and the homepage file's hours sentence (the shell
                is defined by the homepage DOCX; no live status: brief §3.7 keeps it to the contact block); on phones
                with the design switcher on (and no QA cookie), the "Design 1 | Design 2" switch (brief 2.1 §6:
                never over the bottom bar). Opened by the header's menu button (data-sheet-open="menu").
      lang      the language choice (brief 2.1 §6: a sheet, not a segmented group): the three approved labels in the
                page language (D12), each with hreflang (its target) and lang (its own language, the page's); the
                current one marked aria-current. Opened from the menu sheet's language row; without JavaScript that
                row is a link to the footer's language list.
      services  the 11 services: the approved title and the full approved Services Overview summary (never cut,
                D09), then "View All Practice Areas". Opened by the tab bar's Practice Areas (data-sheet-open="services").
    Design 2.1 (brief §3.4–3.6): the overlay style of <x-d2.ui.sheet> (radius 16 on the top corners, the one overlay
    shadow; data-overlay marks it for the QA lint), text-only rows (no decorative icons, arrows, icon tiles or middle
    dots; the language row's chevron-down is the disclosure chevron of a list that opens); the functional contact
    icons (phone, the WhatsApp mark, e-mail) stay. Every string is approved copy (ui(), setting(), content()) except
    the menu title (proposed) and the design switch's internal labels (lang/*/switcher.php, never in production).
    Behaviour: resources/js/d2/lib/sheet.js (modules/sheets.js wires it; opening the language sheet from the menu
    sheet replaces it, and Escape or Back returns focus to the menu button); the sheet itself:
    resources/css/d2/components/sheet.css; the content of these sheets: resources/css/d2/components/tabbar.css.
--}}
@php
    use App\Design\D2\Bidi;
    use App\Design\D2\Hours;
    use App\Design\D2\Shell;
    use App\Support\SiteUrl;

    // An unmatched URL (404) has no route: its language links must go to each home page (review C1/C7).
    $pageKey ??= request()->route() ? null : 'error';
    $path = rtrim(request()->getPathInfo(), '/');
    $samePage = fn (string $href) => rtrim((string) parse_url($href, PHP_URL_PATH), '/') === $path
        && ! parse_url($href, PHP_URL_FRAGMENT);

    // The approved navigation (H-L001 … H-L007): the CMS menu "primary", as in the header.
    $nav = Shell::nav('primary');

    // Display order Arabic · English · Russian (the DOCX language switcher order).
    $order = ['ar' => 0, 'en' => 1, 'ru' => 2];
    $languages = Shell::languageLinks($pageKey);
    usort($languages, fn ($a, $b) => ($order[$a['code']] ?? 9) <=> ($order[$b['code']] ?? 9));
    $locale = app()->getLocale();
    $currentLanguage = collect($languages)->firstWhere('current', true)['code'] ?? $locale;

    // The "Design 1 | Design 2" switch (null unless SUMMIT_DESIGN_SWITCHER is on and the request is not a QA one).
    $designSwitch = Shell::designSwitch();

    $contact = content()->page('P07')['content'] ?? [];
    $final = $contact['final'] ?? [];
    $details = $contact['details'] ?? [];
    // The homepage file's own hours line, as in the footer (App\Design\D2\Hours::SENTENCES).
    $hours = Hours::sentence('menu');

    $serviceSlug = request()->route()?->parameter('service');
    $currentService = is_string($serviceSlug) ? array_search($serviceSlug, config('summit.services'), true) : false;

    // Arabic copy: Latin runs (USDT, VARA …) are isolated in <bdi dir="ltr"> by App\Design\D2\Bidi
    // (Design 1 review C4 / RTL-02). Markup only: the approved text itself is printed unchanged and escaped.
@endphp

<x-d2.ui.sheet id="menu" class="sheet--menu" :title="d2p('mobile.sheet.menu')" :title-proposed="true" :close-label="ui('a11y.close_menu')">
    <nav class="menu-sheet__group" aria-labelledby="sheet-menu-title">
        <ul class="menu-sheet__nav" role="list">
            @foreach ($nav as $item)
                @php
                    $isCurrent = $current === $item['key'];
                    $ariaCurrent = $isCurrent ? ($samePage($item['href']) ? 'page' : 'true') : null;
                @endphp
                <li>
                    <a class="menu-sheet__link" href="{{ $item['href'] }}" @if ($ariaCurrent) aria-current="{{ $ariaCurrent }}" @endif>{{ $item['label'] }}</a>
                </li>
            @endforeach
        </ul>
    </nav>

    {{-- The language: one row that opens the language sheet (no JavaScript: the footer's language list). --}}
    <div class="menu-sheet__group menu-sheet__lang">
        <a class="menu-sheet__langrow" href="#d2-footer-languages" data-sheet-open="lang">
            <span class="menu-sheet__langrow-label">{{ ui('a11y.choose_language') }}</span>
            <span class="menu-sheet__langrow-value">{{ ui('lang.'.$currentLanguage) }}<x-d2.ui.icon class="menu-sheet__langrow-chev" name="chevron-down" size="20" /></span>
        </a>
    </div>

    <section class="menu-sheet__group" aria-labelledby="sheet-menu-contact">
        <h3 class="menu-sheet__title" id="sheet-menu-contact">{{ $details['heading'] ?? ui('nav.contact') }}</h3>
        <ul class="menu-sheet__contacts" role="list">
            <li>
                <a class="menu-sheet__contact" href="{{ setting('phone_href') }}">
                    <x-d2.ui.icon class="menu-sheet__contact-icon" name="phone" size="20" />
                    <span class="menu-sheet__contact-text">
                        <span class="menu-sheet__contact-label">{{ $final['call_label'] ?? ui('nav.contact') }}</span>
                        <bdi class="menu-sheet__contact-value" dir="ltr">{{ setting('phone_display') }}</bdi>
                    </span>
                </a>
            </li>
            <li>
                <a class="menu-sheet__contact" href="{{ setting('whatsapp_url') }}" target="_blank" rel="noopener noreferrer">
                    <x-d2.ui.icon class="menu-sheet__contact-icon" name="whatsapp" size="20" />
                    <span class="menu-sheet__contact-text">
                        <span class="menu-sheet__contact-label">{{ ui('cta.whatsapp') }}</span>
                        <bdi class="menu-sheet__contact-value" dir="ltr">{{ setting('phone_display') }}</bdi>
                    </span>
                    <span class="sr-only" @proposed>{{ proposed('external.new_tab') }}</span>
                </a>
            </li>
            <li>
                <a class="menu-sheet__contact" href="{{ setting('email_href') }}">
                    <x-d2.ui.icon class="menu-sheet__contact-icon" name="mail" size="20" />
                    <span class="menu-sheet__contact-text">
                        <span class="menu-sheet__contact-label">{{ $final['email_label'] ?? ui('nav.contact') }}</span>
                        <bdi class="menu-sheet__contact-value" dir="ltr">{{ setting('email') }}</bdi>
                    </span>
                </a>
            </li>
        </ul>
        @if ($hours !== '')
            <p class="menu-sheet__hours">{{ $hours }}</p>
        @endif
    </section>

    @if ($designSwitch)
        {{-- Internal demo tool (SUMMIT_DESIGN_SWITCHER): phones only (tabbar.css); the floating switch is hidden there. --}}
        <nav class="menu-sheet__group menu-sheet__design" aria-labelledby="sheet-menu-design" data-d2-design-switch>
            <h3 class="menu-sheet__title" id="sheet-menu-design">{{ $designSwitch['label'] }}</h3>
            <ul class="menu-sheet__designs" role="list">
                @foreach ($designSwitch['options'] as $option)
                    <li>
                        <a class="menu-sheet__design-option" href="{{ $option['href'] }}" data-d2-design="{{ $option['design'] }}"
                            aria-keyshortcuts="{{ $option['shortcut'] }}" @if ($option['current']) aria-current="true" @endif>{{ $option['label'] }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif
</x-d2.ui.sheet>

<x-d2.ui.sheet id="lang" class="sheet--lang" :title="ui('a11y.choose_language')" :close-label="ui('a11y.close_menu')">
    <ul class="lang-sheet__list" role="list">
        @foreach ($languages as $language)
            <li>
                {{-- D12: the approved label in the page language ("Arabic" on English pages); hreflang names the target. --}}
                <a class="lang-sheet__option" href="{{ $language['href'] }}" hreflang="{{ $language['code'] }}" lang="{{ $locale }}"
                    data-lang-switch="{{ $language['code'] }}" @if ($language['current']) aria-current="true" @endif>{{ ui('lang.'.$language['code']) }}</a>
            </li>
        @endforeach
    </ul>
</x-d2.ui.sheet>

<x-d2.ui.sheet id="services" class="sheet--services" :title="ui('nav.practice_areas')" :close-label="ui('a11y.close_menu')">
    <ul class="services-sheet__list" role="list">
        @foreach (Shell::practiceAreas('mega') as $service)
            @php
                $code = $service['code'];
                $summary = $service['hub_summary'] ?? $service['home_summary'] ?? null;
            @endphp
            <li>
                {{-- A whole-row link: its name is its visible content (title, then the full summary), WCAG 2.5.3. --}}
                <a class="services-sheet__item" href="{{ SiteUrl::service($code) }}" @if ($currentService === $code) aria-current="page" @endif>
                    <span class="services-sheet__title">{{ Bidi::isolate($service['title'] ?? null) }}</span>
                    @if ($summary)
                        <span class="services-sheet__summary">{{ Bidi::isolate($summary) }}</span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
    <x-d2.ui.button class="services-sheet__all" variant="secondary" :full="true" :href="SiteUrl::page('P02')">{{ ui('cta.view_all_practice_areas') }}</x-d2.ui.button>
</x-d2.ui.sheet>
