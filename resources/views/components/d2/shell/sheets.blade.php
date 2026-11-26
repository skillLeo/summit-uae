@props([
    'current' => null,   // navCurrent: home | practice | resources | cases | faq | about | contact
    'pageKey' => null,   // 'error' on error pages (language links then go to each home page)
])
{{--
    Design 2 global sheets, rendered once by the layout (<x-d2.shell.sheets :current>) at body level.
      menu      the approved navigation (the seven labels, Home first), one row that opens the language sheet (the
                approved "Choose language" and the current language's flag and approved name with a chevron, as
                the header's language trigger), the contact actions and the homepage file's hours sentence (the
                shell is defined by the homepage DOCX; no live status: brief §3.7 keeps it to the contact block).
                Opened by the header's menu button (data-sheet-open="menu").
      lang      the language choice (brief 2.1 §6: a sheet, not a segmented group), as the header's language menu
                (the client's final changes, item 18): [flag] English, [flag] العربية, [flag] Русский, each name
                the approved label of its own language (lang = that language, hreflang = the page it opens), the
                flag decorative (<x-d2.ui.flag>); the current one marked aria-current. Opened from the menu sheet's
                language row; without JavaScript that row is a link to the footer's language list.
      services  the 11 services: the approved title and the full approved Services Overview summary (never cut,
                D09), then "View All Practice Areas". Opened by the tab bar's Practice Areas (data-sheet-open="services").
    Design 2.1 (brief §3.4–3.6): the overlay style of <x-d2.ui.sheet> (radius 16 on the top corners, the one overlay
    shadow; data-overlay marks it for the QA lint), text-only rows (no decorative icons, arrows, icon tiles or middle
    dots; the language row's chevron-down is the disclosure chevron of a list that opens); the functional contact
    icons (phone, the WhatsApp mark, e-mail) stay. Every string is approved copy (ui(), setting(), content()) except
    the menu title (proposed).
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

    // Display order English · Arabic · Russian (the client's final changes, item 18).
    $order = ['en' => 0, 'ar' => 1, 'ru' => 2];
    $languages = Shell::languageLinks($pageKey);
    usort($languages, fn ($a, $b) => ($order[$a['code']] ?? 9) <=> ($order[$b['code']] ?? 9));
    $locale = app()->getLocale();
    $currentLanguage = collect($languages)->firstWhere('current', true)['code'] ?? $locale;

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
            <span class="menu-sheet__langrow-value"><x-d2.ui.flag :code="$currentLanguage" />{{ ui('lang.'.$currentLanguage) }}<x-d2.ui.icon class="menu-sheet__langrow-chev" name="chevron-down" size="20" /></span>
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
</x-d2.ui.sheet>

<x-d2.ui.sheet id="lang" class="sheet--lang" :title="ui('a11y.choose_language')" :close-label="ui('a11y.close_menu')">
    <ul class="lang-sheet__list" role="list">
        @foreach ($languages as $language)
            <li>
                {{-- Item 18: the flag, then the approved name in its own language; hreflang names the target. --}}
                <a class="lang-sheet__option" href="{{ $language['href'] }}" hreflang="{{ $language['code'] }}" lang="{{ $language['code'] }}"
                    data-lang-switch="{{ $language['code'] }}" @if ($language['current']) aria-current="true" @endif><x-d2.ui.flag :code="$language['code']" /><span class="lang-sheet__name" dir="{{ $language['dir'] }}">{{ $language['label'] }}</span></a>
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
