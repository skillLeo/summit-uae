@props([
    'links',                // App\Design\D2\Shell::languageLinks($pageKey): [code, href, label, current, dir]
    'idPrefix' => 'd2',
    'open' => false,        // styleguide: render the open state statically (no Alpine)
])
{{--
    Language menu (brief §3.7, fix D12; behaviour in resources/js/d2/components/langmenu.js, the language links
    themselves in resources/js/d2/modules/lang-switch.js: the #hash is kept and the summit_lang cookie written).
    - The trigger shows the current language's approved label (EN "English", AR "العربية", RU "Русский") and a
      chevron: no codes, no dots, no flags. Its accessible name is the approved "Choose language" followed by
      that label (WCAG 2.5.3, label in name).
    - The menu lists the three approved labels of the page's own language (01_Homepage DOCX, H-L008 to H-L010,
      in its order: EN "Arabic", "English", "Russian"; AR "العربية", "الإنجليزية", "الروسية"; RU "Арабский",
      "Английский", "Русский"), read from the approved content (ui('lang.xx') in the page language). Each link
      carries hreflang (the language of the page it opens) and lang (the language of its label, the page's).
      Shell::languageLinks() gives the translated page, or each language's home page on an error page.
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
    $order = array_values(array_intersect(['ar', 'en', 'ru'], $byCode->keys()->all()));
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
        @endif><span class="sr-only">{{ ui('a11y.choose_language') }} </span><span class="langmenu__current">{{ ui('lang.'.$currentCode) }}</span><x-d2.ui.icon name="chevron-down" size="16" class="langmenu__chev" /></a>

    <div class="popover langmenu__panel" id="{{ $panelId }}" data-overlay
        @unless ($static) hidden x-ref="panel" x-bind:hidden="closed" @endunless>
        <ul class="popover__list" @unless ($static) x-on:keydown="onListKey" @endunless>
            @foreach ($order as $code)
                @php $link = $byCode[$code]; @endphp
                <li>
                    <a class="popover__option langmenu__option" href="{{ $link['href'] }}" hreflang="{{ $code }}" lang="{{ $locale }}"
                        data-lang-switch="{{ $code }}" @if ($link['current']) aria-current="true" @endif>{{ ui('lang.'.$code) }}</a>
                </li>
            @endforeach
        </ul>
    </div>
</div>
