@php
    /** @var array $seo */
    $pageKey = $pageKey ?? 'page';
    $preloadFonts = \App\Support\Seo::preloadFonts();
    $ogImage = \App\Support\SiteUrl::canonical('images/og-default.png');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}" class="no-js" data-page="{{ $pageKey }}" @if (config('summit.features.pwa')) data-pwa @endif>
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
    <meta property="csp-nonce" nonce="{{ Vite::cspNonce() }}">
    <meta name="theme-color" content="#000000">
    <meta name="color-scheme" content="light">
    <meta name="format-detection" content="telephone=no">
    <link rel="icon" href="/favicon.ico" sizes="48x48">
    <link rel="icon" href="/icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/{{ $locale }}/manifest.webmanifest">
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
    @foreach ($preloadFonts as $font)
        <link rel="preload" href="{{ $font }}" as="font" type="font/woff2" crossorigin>
    @endforeach
    @include('partials.boot')
    @vite(array_merge(['resources/scss/app.scss'], $extraStyles ?? [], ['resources/js/app.js']))
    @stack('head')
</head>
<body class="has-bottombar page-{{ $pageKey }}">
    <a class="skip-link" href="#main-content">{{ ui('a11y.skip_to_content') }}</a>
    <x-site.lang-suggest />
    <x-site.header :current="$navCurrent ?? null" />
    <main id="main-content" class="site-main" tabindex="-1">
        @yield('content')
    </main>
    <x-site.footer />
    <x-site.bottombar :current="$navCurrent ?? null" />
    <x-site.sheets />
    <div class="sr-only" aria-live="polite" data-announcer></div>
</body>
</html>
