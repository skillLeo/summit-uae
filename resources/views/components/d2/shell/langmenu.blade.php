@props([
    'links',                // App\Design\D2\Shell::languageLinks($pageKey): [code, href, label, current, dir]
    'idPrefix' => 'd2',
    'open' => false,        // styleguide: render the open state statically (no Alpine)
])
{{--
    Language menu (brief §3.7, fix D12; the client's final changes, item 18, and the owner's decision of 2026-11-25;
    behaviour in resources/js/d2/components/langmenu.js, the language links themselves in
    resources/js/d2/modules/lang-switch.js: the #hash is kept and the summit_lang cookie written).
    - One compact dropdown. The trigger shows the current language's flag and its approved name (EN "English", AR
      "العربية", RU "Русский") and a chevron. Its accessible name is the approved "Choose language" followed by
      that name (WCAG 2.5.3, label in name).
    - The menu lists the three languages as the client's item 18 gives them: [flag] English, [flag] العربية,
      [flag] Русский, in that order, on every page. Each name is the approved label of its own language (the
      01_Homepage DOCX of that language, H-L008 to H-L010: ui('lang.xx', 'xx')), so each link carries lang (the
      language of its name) and hreflang (the language of the page it opens); the Arabic name runs right to left.
      Shell::languageLinks() gives the same page in each language, or each home page on an error page.
    - Flags (<x-d2.ui.flag>): United Kingdom, United Arab Emirates, Russia; SVGs served from this site; decorative
      (alt="", aria-hidden): the name always shows and names the link. In RTL the flag stays before the name (at
      the start side).
    - The panel is the shared overlay surface (.popover, components/popover.css; data-overlay: radius 16, the
      overlay shadow); the current language is marked aria-current and set in ink (no tick: no icon in a menu
      item, brief §3.6 rule 5).
    The x-data root contains the trigger and the panel (click.outside, Design 1 review A11Y-01 / SEC-01). Without
    JavaScript the trigger is a link to the footer's language list.
--}}
@php
    $panelId = $idPrefix.'-langmenu';
    $locale = app()->getLocale();
    $byCode = collect($links)->keyBy('code');
    $currentCode = $byCode->firstWhere('current', true)['code'] ?? $locale;
    // The client's item 18: English, Arabic, Russian.
    $order = array_values(array_intersect(['en', 'ar', 'ru'], $byCode->keys()->all()));
    $static = (bool) $open;
    $footerLangs = 'd2-footer-languages';   // no-JS target: the site footer's language list
@endphp
<div {{ $attributes->class(['langmenu', 'is-open' => $static]) }}
    @unless ($static)
        x-data="d2Langmenu"
        x-on:click.outside="onOutside"
        x-on:focusout="onFocusOut"
        x-on:keydown.escape.window="onEscape"
    @endunless>
    <a class="langmenu__trigger" href="#{{ $footerLangs }}"
        @if ($static)
            role="button" aria-expanded="true" aria-controls="{{ $panelId }}"
        @else
            x-ref="toggle"
            x-bind:role="toggleRole"
            x-bind:aria-expanded="expanded"
            x-bind:aria-controls="panelId"
            x-on:click="onToggleClick"
            x-on:keydown="onToggleKey"
        @endif><span class="sr-only">{{ ui('a11y.choose_language') }} </span><x-d2.ui.flag :code="$currentCode" /><span class="langmenu__current">{{ ui('lang.'.$currentCode) }}</span><x-d2.ui.icon name="chevron-down" size="16" class="langmenu__chev" /></a>

    <div class="popover langmenu__panel" id="{{ $panelId }}" data-overlay
        @unless ($static) hidden x-ref="panel" x-bind:hidden="closed" @endunless>
        <ul class="popover__list" @unless ($static) x-on:keydown="onListKey" @endunless>
            @foreach ($order as $code)
                @php $link = $byCode[$code]; @endphp
                <li>
                    <a class="popover__option langmenu__option" href="{{ $link['href'] }}" hreflang="{{ $code }}" lang="{{ $code }}"
                        data-lang-switch="{{ $code }}" @if ($link['current']) aria-current="true" @endif><x-d2.ui.flag :code="$code" /><span class="langmenu__name" dir="{{ $link['dir'] }}">{{ $link['label'] }}</span></a>
                </li>
            @endforeach
        </ul>
    </div>
</div>
