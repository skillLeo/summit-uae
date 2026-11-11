@props([
    'current' => null,      // $navCurrent: home | practice | resources | cases | faq | about | contact
    'pageKey' => null,      // the layout's $pageKey ('error' points the language links at each home page)
    'idPrefix' => 'd2',     // id namespace: the styleguide renders several headers on one page
    'demo' => null,         // styleguide only (static, not sticky, no Alpine): top | docked | tucked | mega | lang | search | collapsed
    'preview' => null,      // styleguide only: the service previewed in the open mega menu (demo="mega")
])
{{--
    Design 2.1 header (brief §3.7, §3.8; fixes D06, D12, D13, D14, D48). Option A (the owner chooses between A and
    the slim-top-row Option B: storage/app/d2/phase-1/header/options/).

    Two rows, one container (the same .container as every section, so the edges line up within 1 px):
      masthead         the official lockup (56 px tall from 1024 px, 48 px on tablets, 40 px on phones) on the start
                       side; search, the language menu (from 768 px, in every language) and the Ink CTA "Request a
                       Legal Consultation" on the end side. 88 px tall at the top of the page (72 from 768 px, 56 on
                       phones: --header-h-top), sticky, and it shrinks to the 64 px compact bar (--header-h) by
                       scrolling: the header sticks at --header-h minus --header-h-top, and the bar inside is sticky
                       too, so the masthead's spare height scrolls away first and the bar then settles (CSS only, no
                       layout shift, no animated height).
      navigation row   from 1024 px, the seven approved labels in the DOCX order (Home first), never shortened or
                       replaced; Practice Areas opens the mega menu. 48 px. It shows while the seven fit on one line:
                       per-language widths for the first paint and without JavaScript (header.css), then
                       modules/header.js checks the real fonts and text size (.is-nav-collapsed). Where they do not
                       fit (below 1024 px; Russian below about 1100 px) the menu button opens the menu sheet.
    Compact (modules/header.js, .is-scrolled once the masthead has reached the compact bar): the shield alone
    replaces the lockup (cross-fade; the brand name stays the link's name: the lockup's alt text). Where the seven
    labels fit in the bar beside the shield and the tools (English and Arabic in a full container), the row moves
    into the bar (.is-docked) and Practice Areas still opens the mega menu; where they do not (Russian, narrower
    windows), the row tucks under the masthead (.is-tucked) and a menu button appears beside the shield, where the
    labels were, so the tools never move. The CTA never changes.
    Solid white with a 1 px line; no glass, no blur, no night variant, no metal: the CTA is always Ink (D06).

    Without JavaScript every control is a plain link: Practice Areas → P02, search → P03 (Legal Resources),
    the language menu → the footer's language list, the menu button → the footer navigation.
--}}
@php
    use App\Design\D2\Shell;
    use App\Support\SiteUrl;

    $locale = app()->getLocale();
    $route = request()->route();
    // Unmatched URLs (the 404 page) have no route: their language links go to each home page (Shell).
    $pageKey ??= $route === null ? 'error' : null;
    $serviceSlug = $route?->parameter('service');
    $currentService = is_string($serviceSlug) ? (array_search($serviceSlug, config('summit.services'), true) ?: null) : null;
    $static = $demo !== null;

    // The seven approved navigation labels, in the DOCX order (01_Homepage: "the next seven labels form the
    // desktop and mobile navigation; keep the same order"). H-L001 … H-L007: the CMS menu "primary".
    $nav = Shell::nav('primary');
    // The call to action (H-L011 "Request a Legal Consultation" → A01): the CMS menu "actions".
    $cta = Shell::nav('actions')[0] ?? null;

    // aria-current: "page" on the page itself, "true" on its sub-pages (a service page under Practice Areas).
    $currentAttr = fn (string $key) => $current === $key ? ($key === 'practice' && $currentService ? 'true' : 'page') : null;

    // The official lockup (scripts/d2/brand-lockup.mjs: the client's gold-on-transparent master, trimmed and
    // scaled, never redrawn or recoloured): @1x/@2x/@3x WebP with the PNG fallback, explicit width and height.
    $lockup = json_decode((string) @file_get_contents(public_path('images/d2/brand/manifest.json')), true);
    $lockupSize = $lockup['files']['lockup@1x.png'] ?? ['width' => 222, 'height' => 56];
    $lockupSrc = fn (string $ext) => collect(['1x', '2x', '3x'])
        ->map(fn ($d) => asset("images/d2/brand/lockup@{$d}.{$ext}").' '.$d)
        ->implode(', ');

    // The mark alone for the compact bar: the official traced emblem Design 2 already uses
    // (resources/brand/emblem.svg, fill="currentColor"), inlined and never redrawn; it draws in the logo's gold.
    $emblem = trim((string) preg_replace('/<\?xml.*?\?>|<!--.*?-->/s', '', (string) @file_get_contents(resource_path('brand/emblem.svg'))));
    $emblem = (string) preg_replace('/<svg\b/', '<svg class="d2-brand__mark" aria-hidden="true" focusable="false"', $emblem, 1);

    $searchUrl = SiteUrl::page('P03');
    // No-JS targets: the site footer (rendered once per page by the layout).
    $footerNav = 'd2-footer-nav';
@endphp
<header {{ $attributes->class([
        'd2-header',
        'd2-header--demo' => $static,
        'is-scrolled' => in_array($demo, ['docked', 'tucked'], true),
        'is-docked' => $demo === 'docked',
        'is-tucked' => $demo === 'tucked',
        'is-nav-collapsed' => $demo === 'collapsed',
    ]) }}
    @unless ($static) data-d2-header @endunless
    data-search-url="{{ $searchUrl }}">
    <div class="d2-header__mast">
        <div class="container d2-header__bar">
            <div class="d2-header__start">
                <a class="d2-brand" href="{{ SiteUrl::home() }}" @if ($current === 'home') aria-current="page" @endif>
                    <picture class="d2-brand__lockup">
                        <source type="image/webp" srcset="{{ $lockupSrc('webp') }}">
                        <img src="{{ asset('images/d2/brand/lockup@1x.png') }}" srcset="{{ $lockupSrc('png') }}"
                            width="{{ $lockupSize['width'] }}" height="{{ $lockupSize['height'] }}"
                            alt="{{ ui('brand.logo_alt') }}" decoding="async">
                    </picture>
                    {!! $emblem !!}
                </a>
                {{-- Compact bar from 1024 px, where the seven labels do not fit in it (.is-tucked): the navigation row
                     has tucked away, so the menu opens here, where the labels were. Its own class: .d2-header__menu
                     is the menu button of the tools, shown wherever the seven labels do not fit their own row. --}}
                <x-d2.ui.icon-button class="d2-header__compact-menu" icon="menu" :label="ui('a11y.open_menu')"
                    size="44" href="#{{ $footerNav }}" data-sheet-open="menu" tip="none" />
            </div>

            <div class="d2-header__tools">
                {{-- D14: no key cap; "/" and Ctrl/Cmd+K open the palette (modules/header.js), the tooltip names "/". --}}
                <a @class(['d2-search', 'is-focus' => $demo === 'search']) href="{{ $searchUrl }}" data-d2-search
                    aria-label="{{ d2p('shell.search_placeholder') }}" aria-keyshortcuts="/ Control+K Meta+K"
                    data-tooltip="{{ d2p('shell.search_tip') }}" data-tooltip-placement="bottom" @proposed>
                    <x-d2.ui.icon name="search" class="d2-search__icon" />
                </a>

                <x-d2.shell.langmenu class="d2-header__lang" :links="Shell::languageLinks($pageKey)" :id-prefix="$idPrefix" :open="$demo === 'lang'" />

                {{-- D06: always the Ink button, at the top of the page and after scrolling; gold is the hero's alone. --}}
                @if ($cta)
                    <x-d2.ui.button variant="ink" size="44" :href="$cta['href']" class="d2-header__cta">{{ $cta['label'] }}</x-d2.ui.button>
                @endif

                <x-d2.ui.icon-button class="d2-header__menu d2-header__menu--end" icon="menu" :label="ui('a11y.open_menu')"
                    size="44" href="#{{ $footerNav }}" data-sheet-open="menu" tip="none" />
            </div>
        </div>
    </div>

    <div class="d2-header__navrow">
        <nav class="container d2-nav" aria-label="{{ proposed('nav.main') }}">
            <ul class="d2-nav__list">
                @foreach ($nav as $item)
                    @if ($item['mega'])
                        <x-d2.shell.megamenu
                            :label="$item['label']"
                            :href="$item['href']"
                            :current="$currentAttr('practice')"
                            :current-service="$currentService"
                            :id-prefix="$idPrefix"
                            :open="$demo === 'mega'"
                            :preview="$preview" />
                    @else
                        <li class="d2-nav__item" data-nav-key="{{ $item['key'] }}">
                            <a class="d2-nav__link" href="{{ $item['href'] }}" @if ($currentAttr($item['key'])) aria-current="{{ $currentAttr($item['key']) }}" @endif>{{ $item['label'] }}</a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>
    </div>
</header>
@unless ($static)
    {{-- D48: the 6 % ink scrim under the open mega menu (header.css shows it with :has(); a click on it closes the
         menu, as any click outside). Outside the header, which contains its fixed descendants. --}}
    <div class="d2-scrim" aria-hidden="true"></div>
@endunless
