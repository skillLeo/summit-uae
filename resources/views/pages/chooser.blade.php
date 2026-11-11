@php
    use App\Support\SiteUrl;
    $locales = array_keys(config('summit.locales'));
@endphp
<!DOCTYPE html>
<html lang="en" dir="ltr" class="no-js">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ ui('brand.name', 'en') }} · {{ ui('brand.name', 'ar') }}</title>
    <meta name="robots" content="noindex, follow">
    @foreach ($seo['alternates'] as $hreflang => $href)
        <link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $href }}">
    @endforeach
    <meta name="theme-color" content="#000000">
    <link rel="icon" href="/favicon.ico" sizes="48x48">
    <link rel="icon" href="/icon.svg" type="image/svg+xml">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body>
    {{-- Neutral language entry. Only a language the visitor picked before is remembered. --}}
    <main class="chooser" id="main-content">
        <h1 class="sr-only">{{ ui('brand.name', 'en') }}</h1>
        <x-brand name="lockup" class="brand__lockup" />
        <p class="chooser__title">
            @foreach ($locales as $code)
                <span lang="{{ $code }}" dir="{{ locale_dir($code) }}">{{ ui('a11y.choose_language', $code) }}</span>
            @endforeach
        </p>
        <ul class="chooser__list">
            @foreach ($locales as $code)
                <li>
                    <a href="{{ SiteUrl::home($code) }}" lang="{{ $code }}" hreflang="{{ $code }}" dir="{{ locale_dir($code) }}" data-lang-switch="{{ $code }}">{{ ui('lang.'.$code, $code) }}</a>
                </li>
            @endforeach
        </ul>
    </main>
</body>
</html>
