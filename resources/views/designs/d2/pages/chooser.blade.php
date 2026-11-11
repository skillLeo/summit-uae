{{--
    Design 2 root language chooser (owner: system-pages). SiteController@root renders "pages.chooser" at "/"
    when the visitor never chose a language (with a remembered choice "/" is a 302 to that language, server
    side). Replaces Design 1's chooser by name and keeps its behaviour exactly: a neutral page (no header,
    footer or tab bar in any one language), noindex, the hreflang alternates of the controller's $seo, one
    link per language to its home page with lang, hreflang, dir and data-lang-switch, and nothing else;
    resources/js/d2/modules/lang-switch.js writes the summit_lang cookie (12 months) on the click, as Design
    1's chrome.js does, so the next visit to "/" goes straight to that language. Never redirects by
    Accept-Language or IP.

    Copy: approved only. Brand name (ui brand.name / brand.logo_alt), "Choose language" in each language
    (ui a11y.choose_language), each language named in its own script (ui lang.xx in xx, the approved
    switcher labels of brief §3.7) with the approved home H1 of that language as its line.
    Display order Arabic, English, Russian (Design 1 review LANG-02). No flags, no emoji.
    Design 2.1: the H1 is the official traced lockup (shield and wordmark, resources/brand/lockup.svg, in the
    logo's own gold), never the mark in a black tile (brief §3.8); no middle dots between the three prompts or
    in the document title (D02); no invented language codes on the cards (D12) and no arrows (D03); a white page
    (no centred light, D07).

    Head: the same Design 2 head as d2::layouts.app (fonts inlined, the view-transition opt-in, the boot
    script, the Design 2 bundle), so the choice crossfades or slides into the chosen language
    (resources/js/d2/modules/view-transitions.js, resources/css/d2/base/motion.css).
    Styles: resources/css/d2/pages/chooser.css.
--}}
@php
    use App\Design\D2\FontFaces;
    use App\Design\D2\Fonts;
    use App\Support\SiteUrl;

    /** @var array $seo */
    $order = array_values(array_intersect(['ar', 'en', 'ru'], array_keys(config('summit.locales'))));
    $entries = ['resources/css/d2/app.css', 'resources/js/d2/app.js'];

    // Official traced lockup (resources/brand/lockup.svg, fill="currentColor"), inlined, never redrawn.
    $brand = function (string $file, string $class): string {
        $svg = trim((string) preg_replace('/<\?xml.*?\?>|<!--.*?-->/s', '', (string) @file_get_contents(resource_path('brand/'.$file))));

        return (string) preg_replace('/<svg\b/', '<svg class="'.$class.'" aria-hidden="true" focusable="false"', $svg, 1);
    };
@endphp
<!DOCTYPE html>
<html lang="en" dir="ltr" class="no-js" data-design="2" data-page="chooser" @if (config('summit.features.pwa')) data-pwa @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ ui('brand.name', 'en') }} | {{ ui('brand.name', 'ar') }}</title>
    <meta name="robots" content="noindex, follow">
    @foreach ($seo['alternates'] ?? [] as $hreflang => $href)
        <link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $href }}">
    @endforeach
    <meta property="csp-nonce" nonce="{{ Vite::cspNonce() }}">
    {{-- As d2::layouts.app: the browser / app bar colour is the manifest's theme_color (Phase E). --}}
    <meta name="theme-color" content="#0B0B0C">
    <meta name="color-scheme" content="light">
    <meta name="format-detection" content="telephone=no">
    <link rel="icon" href="/favicon.ico" sizes="48x48">
    <link rel="icon" href="/icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @foreach (Fonts::preload('en') as $font)
        <link rel="preload" href="{{ $font }}" as="font" type="font/woff2" crossorigin>
    @endforeach
    <style nonce="{{ Vite::cspNonce() }}">{!! FontFaces::css() !!}</style>
    {{-- Cross-document view transitions: the same inline opt-in as d2::layouts.app (an external stylesheet
         arrives too late for it). --}}
    <style nonce="{{ Vite::cspNonce() }}">@view-transition{navigation:auto}@media (prefers-reduced-motion:reduce){@view-transition{navigation:none}}</style>
    @include('d2::shell.boot')
    {{ d2_vite($entries) }}
</head>
<body class="d2 page-chooser">
    <main class="chooser" id="main-content" tabindex="-1">
        <div class="chooser__inner">
            <h1 class="chooser__brand">
                {!! $brand('lockup.svg', 'chooser__lockup') !!}
                <span class="sr-only">{{ ui('brand.logo_alt', 'en') }}</span>
            </h1>

            <p class="chooser__prompt">
                @foreach ($order as $code)
                    <span lang="{{ $code }}" dir="{{ locale_dir($code) }}">{{ ui('a11y.choose_language', $code) }}</span>
                @endforeach
            </p>

            <ul class="chooser__list" role="list">
                @foreach ($order as $code)
                    <li class="chooser__item">
                        <x-d2.ui.card as="a" :href="SiteUrl::home($code)" padding="lg" class="chooser-card"
                            lang="{{ $code }}" hreflang="{{ $code }}" dir="{{ locale_dir($code) }}" data-lang-switch="{{ $code }}">
                            <span class="chooser-card__body">
                                <span class="chooser-card__name">{{ ui('lang.'.$code, $code) }}</span>
                                <span class="chooser-card__line">{{ content()->page('P01', $code)['h1'] ?? '' }}</span>
                            </span>
                        </x-d2.ui.card>
                    </li>
                @endforeach
            </ul>
        </div>
    </main>
</body>
</html>
