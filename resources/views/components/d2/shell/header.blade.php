@props([
    'current' => null,      // $navCurrent: home | practice | resources | cases | faq | about | contact
    'pageKey' => null,      // the layout's $pageKey ('error' points the language links at each home page)
    'idPrefix' => 'd2',     // id namespace: the styleguide renders several headers on one page
    'demo' => null,         // styleguide only (static, not sticky, no Alpine): top | docked | tucked | mega | lang | collapsed
    'preview' => null,      // styleguide only: the service previewed in the open mega menu (demo="mega")
])
{{--
    The header (brief §3.7, §3.8, D06, D13; the client's final changes, item 1, 2026-11-25): black, and it holds only
    the official lockup (the logo and the company name), the main navigation and the language menu. No button, no
    search, no WhatsApp, no subtitle under the name.

      masthead         the official gold lockup on black (no tile; 56 px tall from 1024 px, 48 px on tablets, 40 px on
                       phones) on the start side, the language menu and (where the seven labels do not show) the menu
                       button on the end side. 88 px at the top of the page from 1024 px (72 on tablets, 56 on phones),
                       sticky, shrinking to the 72 px compact bar from 1024 px (64 on tablets, 56 on phones) by
                       scrolling: no animated height, no layout shift. From 1024 px the lockup eases from 56 to 48 px
                       (a scale, 200 ms; at once with reduced motion). The lockup always shows in full.
      navigation       from 1024 px, the seven approved labels in the DOCX order (Home first), never shortened or
                       replaced; Practice Areas opens the mega menu. modules/header.js measures them with the real
                       fonts: where they fit beside the lockup and the language menu (English and Arabic on wide
                       windows) they sit in the masthead's one row, at its end (.is-inline); where they do not
                       (Russian, narrower windows) they take their own 48 px black row under the masthead, and once
                       scrolled they move into the compact bar if they fit there (.is-inline) or tuck away behind the
                       menu button (.is-tucked).
    Colours (header.css): #0B0B0C, a 1 px #27272A line, labels #E4E4E7, the current page and hover #FFFFFF with the
    current page's 2 px gold bar, muted controls #A1A1AA, the light gold focus ring.

    Without JavaScript every control is a plain link: Practice Areas → P02, the language menu → the footer's language
    list, the menu button → the footer navigation. "/" and Ctrl/Cmd+K still open the search palette (header.js; the
    header keeps data-search-url as the no-palette target).
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
    // aria-current: "page" on the page itself, "true" on its sub-pages (a service page under Practice Areas).
    $currentAttr = fn (string $key) => $current === $key ? ($key === 'practice' && $currentService ? 'true' : 'page') : null;

    // The official lockup (scripts/d2/brand-lockup.mjs: the client's gold-on-transparent master, trimmed and
    // scaled, never redrawn or recoloured): @1x/@2x/@3x WebP with the PNG fallback, explicit width and height.
    $lockup = json_decode((string) @file_get_contents(public_path('images/d2/brand/manifest.json')), true);
    $lockupSize = $lockup['files']['lockup@1x.png'] ?? ['width' => 222, 'height' => 56];
    $lockupSrc = fn (string $ext) => collect(['1x', '2x', '3x'])
        ->map(fn ($d) => asset("images/d2/brand/lockup@{$d}.{$ext}").' '.$d)
        ->implode(', ');

    $searchUrl = SiteUrl::page('P03');
    // No-JS targets: the site footer (rendered once per page by the layout).
    $footerNav = 'd2-footer-nav';
@endphp
<header {{ $attributes->class([
        'd2-header',
        'd2-header--demo' => $static,
        'is-scrolled' => in_array($demo, ['docked', 'tucked'], true),
        'is-inline' => $demo === 'docked',
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
                </a>
            </div>

            <div class="d2-header__tools">
                <x-d2.shell.langmenu class="d2-header__lang" :links="Shell::languageLinks($pageKey)" :id-prefix="$idPrefix" :open="$demo === 'lang'" />

                <x-d2.ui.icon-button class="d2-header__menu d2-header__menu--end" icon="menu" variant="night" :label="ui('a11y.open_menu')"
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
