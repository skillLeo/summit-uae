{{--
    Design 2 layout (docs/upgrade-plan.md). Same SEO contract as Design 1's layouts/site ($seo from
    App\Support\Seo::make), Design 2 assets only (public/build-d2 via d2_vite()), Design 2 shell.
--}}
@php
    /** @var array $seo */
    $pageKey = $pageKey ?? 'page';
    $ogImage = \App\Support\SiteUrl::canonical('images/og-default.png');
    $entries = array_merge(['resources/css/d2/app.css'], $d2Styles ?? [], ['resources/js/d2/app.js']);
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}" class="no-js" data-design="2" data-page="{{ $pageKey }}" @if (config('summit.features.pwa')) data-pwa @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $seo['title'] }}</title>
    @if ($seo['description'])
        <meta name="description" content="{{ $seo['description'] }}">
    @endif
    @unless ($seo['index'])
        <meta name="robots" content="noindex, follow">
    @endunless
    @if ($seo['canonical'])
        <link rel="canonical" href="{{ $seo['canonical'] }}">
    @endif
    @foreach ($seo['alternates'] as $hreflang => $href)
        <link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $href }}">
    @endforeach
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="d2-consultation" content="{{ \App\Design\D2\Prefill::url() }}">
    <meta property="csp-nonce" nonce="{{ Vite::cspNonce() }}">
    {{-- The browser / app bar colour = the manifests' theme_color (public/d2/manifest-*.webmanifest) = tokens.css --dark, so an installed app never flashes from one colour to the other at launch. Change both together. --}}
    <meta name="theme-color" content="#0B0B0C">
    <meta name="color-scheme" content="light">
    <meta name="format-detection" content="telephone=no">
    <link rel="icon" href="/favicon.ico" sizes="48x48">
    <link rel="icon" href="/icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    {{-- mobile-shell (Phase E): Design 2's static manifest per language (scripts/d2/pwa-icons.mjs); Design 1's /{locale}/manifest.webmanifest route is untouched. --}}
    <link rel="manifest" href="/d2/manifest-{{ $locale }}.webmanifest">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ ui('brand.name') }}">
    <meta property="og:title" content="{{ $seo['title'] }}">
    @if ($seo['description'])
        <meta property="og:description" content="{{ $seo['description'] }}">
    @endif
    @if ($seo['canonical'])
        <meta property="og:url" content="{{ $seo['canonical'] }}">
    @endif
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:locale" content="{{ $seo['og_locale'] }}">
    @foreach ($seo['og_alternates'] as $alt)
        <meta property="og:locale:alternate" content="{{ $alt }}">
    @endforeach
    <meta name="twitter:card" content="summary_large_image">
    {{-- Fonts (brief §3.2; docs/d2.1/phase-1/tokens.md §5): at most two preloads, the page language's 400 and 600 files
         (App\Design\D2\Fonts::PRELOAD; one variable Onest file draws both on English pages). --}}
    @foreach (\App\Design\D2\Fonts::preload($locale) as $font)
        <link rel="preload" href="{{ $font }}" as="font" type="font/woff2" crossorigin>
    @endforeach
    {{-- The @font-face rules (resources/css/d2/base/fonts.css, scripts/fonts/fontsource-d2.mjs) in groups
         (App\Design\D2\FontFaces::groups()): "base" is active from the start; the boot script holds "wide", "docs" and
         "late" until the first paint where the first screen does not need them, so their files load after it, and
         starts the "base" faces the preloads leave out (data-d2-early). Without JavaScript every group is active. --}}
    @php $fontGroups = \App\Design\D2\FontFaces::groups($locale); @endphp
    @php $fontEarly = \App\Design\D2\FontFaces::earlyLoads($locale); @endphp
    <style nonce="{{ Vite::cspNonce() }}" @if ($fontEarly) data-d2-early="{{ json_encode($fontEarly, JSON_UNESCAPED_UNICODE) }}" @endif>{!! $fontGroups['base'] !!}</style>
    @foreach (['wide', 'docs', 'late'] as $fontGroup)
        @if ($fontGroups[$fontGroup] !== '')
            <style nonce="{{ Vite::cspNonce() }}" data-d2-fonts="{{ $fontGroup }}" @if ($fontGroup === 'docs') data-d2-load="{{ json_encode(\App\Design\D2\FontFaces::docLoads($locale), JSON_UNESCAPED_UNICODE) }}" @endif>{!! $fontGroups[$fontGroup] !!}</style>
        @endif
    @endforeach
    {{-- Cross-document view transitions (progressive). The opt-in lives here, in the head: read from the
         external stylesheet it arrives too late for the incoming page, and the outgoing page's transition
         then aborts with an uncaught "ViewTransition opt-in disabled" page error. --}}
    <style nonce="{{ Vite::cspNonce() }}">@view-transition{navigation:auto}@media (prefers-reduced-motion:reduce){@view-transition{navigation:none}}</style>
    @include('d2::shell.boot')
    {{ d2_vite($entries) }}
    @stack('head')
</head>
<body class="d2 page-{{ $pageKey }}">
    <a class="skip-link" href="#main-content">{{ ui('a11y.skip_to_content') }}</a>
    {{-- system-pages: an error page ($pageKey 'error', d2::errors.404) also sends the language links of the header,
         the footer and the menu sheet to each home page when the 404 comes from abort() on a matched route
         (null keeps the shell's own detection: no route = error). --}}
    @php $shellPageKey = $pageKey === 'error' ? 'error' : null; @endphp
    <x-d2.shell.header :current="$navCurrent ?? null" :page-key="$shellPageKey" />
    <main id="main-content" class="d2-main" tabindex="-1">
        @yield('content')
    </main>
    <x-d2.shell.footer :page-key="$shellPageKey" />
    <x-d2.shell.tabbar :current="$navCurrent ?? null" />
    <x-d2.shell.sheets :current="$navCurrent ?? null" :page-key="$shellPageKey" />
    <x-d2.shell.palette />
    @stack('sheets')
    {{-- Not aria-atomic: each toast is its own element, so only the new one is announced. --}}
    <div class="toast-region" data-toast-region aria-live="polite"></div>
    <div class="sr-only" aria-live="polite" data-announcer></div>
</body>
</html>
