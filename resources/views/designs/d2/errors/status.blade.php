{{--
    Design 2 error pages 403 · 419 · 429 · 500 · 503 (owner: error-pages), shared by errors/{status}.blade.php.
    Laravel's exception handler renders errors::{status}; in Design 2 that namespace starts with this folder
    (App\Design\DesignManager::apply lists resources/views/designs/d2 first in config('view.paths'), which
    the handler maps to {path}/errors), so each file here replaces the stock page and Design 1 keeps it.
    The 404 is separate (d2::errors.404 with the full shell, rendered by bootstrap/app.php).

    Robust by construction: the error may be the database itself, so nothing here touches the database,
    the cache, content(), ui() or setting(), and the page uses no layout, shell or app.js. Copy:
      - proposed (lang files): the H1, the message and "Go back" (d2p('errors.*')), "Try again"
        (d2p('offline.retry'), already proposed for the offline page);
      - approved: the committed import output database/content/*.json (the very records ContentSeeder
        loads), read from disk: brand name and logo name, skip link, "Back to Home" and the "Looking for
        specific information?" links (U01, as on the 404), the P07 contact heading and labels, the copyright
        line. A missing or unreadable file leaves that element out;
      - the contact constants (project rules, = setting('phone_display') / setting('email')).
    Assets: the Design 2 stylesheet (a missing build still leaves a readable page: the inline fallback below),
    the inlined @font-face rules, the boot script. 419's "Go back" reloads the previous page (a fresh form
    token) with a small nonce'd script; without JavaScript it goes to "./" (for the consultation form, the
    page that holds it).
    Language: the URL's first segment (en, ar, ru; else the default), whichever middleware got to run.
    Never shows the exception, the address or the query. noindex. Styles: resources/css/d2/pages/error.css.
    Design 2.1: the brand bar shows the official traced lockup (resources/brand/lockup.svg: shield and
    wordmark, in the logo's own gold; never the mark in a black tile, never the name set in a font: brief
    §3.8, D13); the status code is a plain label; the illustration is the 404's folder without the metal seal
    and its status glyph (D06); the buttons and cards carry no home, refresh or arrow glyphs (D03, D04); the
    copyright year is the current Asia/Dubai year (brief §5.2), as in the shell's footer.
--}}
@php
    use App\Design\D2\FontFaces;
    use App\Design\D2\Fonts;
    use App\Support\SiteUrl;
    use Illuminate\Support\Facades\Vite;

    $status = (int) $status;
    $locales = (array) config('summit.locales');
    $segment = (string) request()->segment(1);
    $locale = array_key_exists($segment, $locales) ? $segment : (string) config('summit.default_locale', 'en');
    $dir = (string) ($locales[$locale]['dir'] ?? 'ltr');
    app()->setLocale($locale);

    // Per status: the actions in order (the first is the primary button), and the second row: the contact
    // details for 500 and 503 (the site may not work, the phone and e-mail do), the approved U01 "Looking for
    // specific information?" links (as on the 404) for the others.
    $page = [
        403 => ['actions' => ['home'], 'contact' => false],
        419 => ['actions' => ['back', 'home'], 'contact' => false],
        429 => ['actions' => ['retry', 'home'], 'contact' => false],
        500 => ['actions' => ['retry', 'home'], 'contact' => true],
        503 => ['actions' => ['retry'], 'contact' => true],
    ][$status] ?? ($status >= 500
        // The 5xx / 4xx catch-alls (errors/5xx, errors/4xx, 401, 402): 502, 504, 400, 405, 413, 422 …
        ? ['actions' => ['retry', 'home'], 'contact' => true]
        : ['actions' => ['back', 'home'], 'contact' => false]);

    // Statuses without their own copy use their family's text (errors.title_4xx / errors.title_5xx).
    $copy = \Illuminate\Support\Facades\Lang::has('proposed.d2.errors.title_'.$status) ? (string) $status : ($status >= 500 ? '5xx' : '4xx');

    // Approved copy without the database: the committed import snapshot, {key: {en, ar, ru}}.
    $snapshot = function (string $file): array {
        try {
            $path = database_path('content/'.$file.'.json');
            $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        } catch (\Throwable) {
            $data = null;
        }

        return is_array($data) ? $data : [];
    };
    $ui = $snapshot('ui_strings');
    $records = $snapshot('pages');
    $str = fn (mixed $value): string => is_string($value) ? trim($value) : '';
    $approved = fn (string $key): string => $str($ui[$key][$locale] ?? null);

    $brand = $approved('brand.name') ?: (string) config('app.name');
    $logoAlt = $approved('brand.logo_alt') ?: $brand;
    $skip = $approved('a11y.skip_to_content');
    // The copyright line with the current Asia/Dubai year (brief §5.2: the year is dynamic), as the footer does.
    $copyright = (string) preg_replace('/\b\d{4}\b/u', (string) now('Asia/Dubai')->year, $approved('footer.copyright'), 1);
    $homeLabel = $str($records['U01']['actions'][$locale]['home_label'] ?? null) ?: $approved('nav.home');
    $details = $records['P07']['content'][$locale]['details'] ?? null;
    $details = is_array($details) ? $details : [];
    $contactTitle = $str($details['heading'] ?? null);
    $moreTitle = $str($records['U01']['more_heading'][$locale] ?? null);
    $links = array_values(array_filter(
        is_array($records['U01']['links'][$locale] ?? null) ? $records['U01']['links'][$locale] : [],
        fn ($link) => is_array($link) && $str($link['label'] ?? null) !== '' && $str($link['target'] ?? null) !== '',
    ));

    // Contact constants (project rules): never from the database here.
    $channels = [
        ['icon' => 'phone', 'label' => $str($details['telephone_label'] ?? null), 'value' => '+971 52 466 6191', 'href' => 'tel:+971524666191'],
        ['icon' => 'mail', 'label' => $str($details['email_label'] ?? null), 'value' => 'info@summitlegaluae.com', 'href' => 'mailto:info@summitlegaluae.com'],
    ];

    $title = d2p('errors.title_'.$copy);
    $text = d2p('errors.text_'.$copy);
    $home = SiteUrl::home($locale);

    // The actions: href, label, whether the label is proposed copy. "./" = the address in the location bar
    // without its query (every page URL ends with a slash), the page's own folder for a form post.
    $actions = array_values(array_filter(array_map(fn (string $action) => match ($action) {
        'home' => $homeLabel !== '' ? ['href' => $home, 'label' => $homeLabel, 'proposed' => false, 'back' => false] : null,
        'retry' => ['href' => './', 'label' => d2p('offline.retry'), 'proposed' => true, 'back' => false],
        'back' => ['href' => './', 'label' => d2p('errors.back'), 'proposed' => true, 'back' => true],
        default => null,
    }, $page['actions'])));

    $nonce = Vite::cspNonce();
    try {
        $assets = d2_vite(['resources/css/d2/app.css']);
    } catch (\Throwable) {
        $assets = '';
    }

    // Official traced artwork (resources/brand/*.svg, fill="currentColor"), inlined, never redrawn: the lockup in
    // the brand bar, the emblem on the folder of the illustration.
    $brandSvg = function (string $file, string $class): string {
        $svg = trim((string) preg_replace('/<\?xml.*?\?>|<!--.*?-->/s', '', (string) @file_get_contents(resource_path('brand/'.$file))));

        return (string) preg_replace('/<svg\b/', '<svg class="'.$class.'" aria-hidden="true" focusable="false"', $svg, 1);
    };
    $emblem = fn (string $class): string => $brandSvg('emblem.svg', $class);
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}" class="no-js" data-design="2" data-page="error" data-status="{{ $status }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $brand !== '' ? $title.' | '.$brand : $title }}</title>
    <meta name="robots" content="noindex, follow">
    <meta name="theme-color" content="#0B0B0C">
    <meta name="color-scheme" content="light">
    <meta name="format-detection" content="telephone=no">
    <link rel="icon" href="/favicon.ico" sizes="48x48">
    <link rel="icon" href="/icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @foreach (Fonts::preload($locale) as $font)
        <link rel="preload" href="{{ $font }}" as="font" type="font/woff2" crossorigin>
    @endforeach
    <style nonce="{{ $nonce }}">{!! FontFaces::css() !!}</style>
    {{-- Without the Design 2 stylesheet (a broken build) the page stays readable: these rules apply only when
         the tokens are missing (@container not style(--d2-tokens: 1), as on the offline page). --}}
    <style nonce="{{ $nonce }}">@container not style(--d2-tokens: 1){body{max-inline-size:44rem;margin-inline:auto;padding:1rem 1.5rem 3rem;font-family:system-ui,sans-serif;line-height:1.6}svg:not([width]){inline-size:2.5rem;block-size:auto}.err__art{display:none}.skip-link{position:absolute;inline-size:1px;block-size:1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap}ul{padding-inline-start:0;list-style:none}a{color:inherit}}</style>
    @include('d2::shell.boot')
    {{ $assets }}
</head>
<body class="d2 err-page">
    @if ($skip !== '')
        <a class="skip-link" href="#main-content">{{ $skip }}</a>
    @endif
    <header class="err-bar">
        <div class="container err-bar__inner">
            <a class="err-brand" href="{{ $home }}" aria-label="{{ $logoAlt }}">{!! $brandSvg('lockup.svg', 'err-brand__lockup') !!}</a>
        </div>
    </header>

    <main id="main-content" class="err" tabindex="-1">
        <section class="err__section" aria-labelledby="err-title">
            <div class="container err__grid">
                <div class="err__copy">
                    <p class="err__code"><bdi dir="ltr">{{ $status }}</bdi></p>
                    <h1 class="err__title" id="err-title" @proposed>{{ $title }}</h1>
                    <p class="t-body-l err__text" @proposed>{{ $text }}</p>

                    @if ($actions !== [])
                        <div class="err__actions">
                            @foreach ($actions as $i => $action)
                                <x-d2.ui.button :href="$action['href']" :variant="$i === 0 ? 'ink' : 'secondary'" size="48" :data-error-back="$action['back'] ? '' : null">
                                    @if ($action['proposed'])
                                        <span @proposed>{{ $action['label'] }}</span>
                                    @else
                                        {{ $action['label'] }}
                                    @endif
                                </x-d2.ui.button>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Decorative: the 404's dark folder with the official emblem and two filed sheets (no seal). --}}
                <div class="err__art err-art" aria-hidden="true" data-illustration>
                    <div class="err-art__stage">
                        <div class="nf-art__stage err-art__file">
                            <span class="nf-art__back"></span>
                            <span class="nf-art__sheet nf-art__sheet--1"></span>
                            <span class="nf-art__sheet nf-art__sheet--2"></span>
                            <span class="nf-art__front">{!! $emblem('nf-art__emblem') !!}</span>
                        </div>
                    </div>
                </div>

                @if ($page['contact'])
                    <div class="err__next">
                        @if ($contactTitle !== '')
                            <h2 class="err__next-title" id="err-next-title">{{ $contactTitle }}</h2>
                        @endif
                        <ul class="err-channels" role="list" @if ($contactTitle !== '') aria-labelledby="err-next-title" @endif>
                            @foreach ($channels as $channel)
                                <li>
                                    <x-d2.ui.card as="a" :href="$channel['href']" padding="md" class="err-channel">
                                        <span class="err-channel__row">
                                            <x-d2.ui.icon :name="$channel['icon']" size="20" class="err-channel__icon" />
                                            <span class="err-channel__text">
                                                @if ($channel['label'] !== '')
                                                    <span class="err-channel__label">{{ $channel['label'] }}</span>
                                                @endif
                                                {{-- A narrow phone may break the address after "@" (<wbr>), never inside a word. --}}
                                                <bdi class="err-channel__value" dir="ltr">{!! str_replace('@', '@<wbr>', e($channel['value'])) !!}</bdi>
                                            </span>
                                        </span>
                                    </x-d2.ui.card>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @elseif ($links !== [])
                    <div class="err__next">
                        @if ($moreTitle !== '')
                            <h2 class="err__next-title" id="err-next-title">{{ $moreTitle }}</h2>
                        @endif
                        {{-- The 404's link rows (the kit's .link-row, pages/inner.css; .nf-links, error.css). --}}
                        <ul class="nf-links" role="list" @if ($moreTitle !== '') aria-labelledby="err-next-title" @endif>
                            @foreach ($links as $link)
                                <li>
                                    <a class="link-row nf-link" href="{{ target_url((string) $link['target'], $locale) }}">
                                        <span class="link-row__label">{{ $link['label'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </section>
    </main>

    @if ($copyright !== '')
        <footer class="err-foot">
            <div class="container">
                <p class="err-foot__text">{{ $copyright }}</p>
            </div>
        </footer>
    @endif

    @if (in_array('back', $page['actions'], true))
        {{-- 419: back to the previous page of this site, loaded afresh (its form gets a new token), replacing
             this entry in the history; otherwise (no same-site referrer, modified click) the link's "./". --}}
        <script nonce="{{ $nonce }}">
        document.addEventListener('click', function (e) {
            var link = e.target instanceof Element ? e.target.closest('a[data-error-back]') : null;
            if (!link || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
            try {
                var from = new URL(document.referrer);
                if (from.origin === location.origin && from.href !== location.href) {
                    e.preventDefault();
                    location.replace(from.href);
                }
            } catch (err) {}
        });
        </script>
    @endif
</body>
</html>
