@props([
    'label',                    // approved ui('nav.practice_areas')
    'href',                     // SiteUrl::page('P02'): the toggle is this plain link without JavaScript
    'current' => null,          // aria-current value for the toggle ("page" on P02, "true" on a service page)
    'currentService' => null,   // service code of the current page (previewed first, marked aria-current)
    'idPrefix' => 'd2',
    'open' => false,            // styleguide: render the open state statically (no Alpine)
    'preview' => null,          // styleguide: service code to preview
])
{{--
    Practice Areas mega menu (brief D48, §3.5, §3.6; behaviour in resources/js/d2/components/megamenu.js).
    A white overlay panel (data-overlay: radius 16, the one overlay shadow) as wide as the container, hung under
    the navigation row over a 6 % ink scrim (header.css), so nothing of the page shows beside or through it.
    Text only (no icons, no tiles, no arrows, no capitals), in three columns sized so that the whole panel fits a
    1024 x 768 or 1366 x 768 window under the 136 px header in every language, with no inner scrolling:
      1. the eleven approved service titles (the list is named by the approved "Practice Areas", the toggle's own
         label right above it), in a column wide enough for most titles to sit on one line, then "View All
         Practice Areas".
      2. + 3. the preview of the hovered or focused service: its approved title across both columns; under it its
         full homepage summary (never clamped) with the Ink button "Explore This Service" directly below, and beside
         them its approved documents heading with the first four documents of its checklist.
    The service pages carry their related guides; the menu has no guides column (it did not fit a 768 px window).
    Where a window is shorter still, the panel's body scrolls with a fade at its lower edge (megamenu.js marks it).
    The x-data root is the <li> holding the toggle AND the panel, so x-on:click.outside never fires for the click
    that opens it (Design 1 review A11Y-01 / LANG-01 / SEC-01). Keyboard, Esc and focus return: megamenu.js.
--}}
@php
    use App\Design\D2\Bidi;
    use App\Support\SiteUrl;

    // Arabic copy: Latin runs (USDT, TxID …) are isolated in <bdi dir="ltr"> by Bidi::isolate().
    // The practice areas of the CMS menu "mega" (S01–S11 in the approved order).
    $services = \App\Design\D2\Shell::practiceAreas('mega');
    $panelId = $idPrefix.'-megamenu';
    $codes = array_column($services, 'code');
    $first = $preview && in_array($preview, $codes, true) ? $preview : ($currentService ?: ($codes[0] ?? null));
    $static = (bool) $open;
@endphp
<li {{ $attributes->class(['d2-nav__item', 'd2-nav__item--mega', 'is-open' => $static]) }}
    data-mega-default="{{ $first }}"
    @unless ($static)
        x-data="d2Megamenu"
        x-on:pointerenter="onEnter"
        x-on:pointerleave="onLeave"
        x-on:click.outside="onOutside"
        x-on:focusout="onFocusOut"
        x-on:keydown.escape.window="onEscape"
    @endunless>
    <a class="d2-nav__link d2-nav__toggle" href="{{ $href }}"
        @if ($current) aria-current="{{ $current }}" @endif
        @if ($static)
            role="button" aria-expanded="true" aria-controls="{{ $panelId }}"
        @else
            x-ref="toggle"
            x-bind:role="toggleRole"
            x-bind:aria-expanded="expanded"
            x-bind:aria-controls="panelId"
            x-on:click="onToggleClick"
            x-on:keydown="onToggleKey"
        @endif>{{ $label }}<x-d2.ui.icon name="chevron-down" size="16" class="d2-nav__chev" /></a>

    <div class="megamenu" id="{{ $panelId }}" data-overlay
        @unless ($static) hidden x-ref="panel" x-bind:hidden="closed" @endunless>
        <div class="megamenu__body" @unless ($static) x-ref="body" x-on:scroll="onBodyScroll" @endunless>
            <div class="megamenu__col megamenu__col--list">
                <ul class="megamenu__list" aria-label="{{ $label }}"
                    @unless ($static) x-on:keydown="onListKey" x-on:focusin="onListFocus" x-on:pointerover="onListPointer" @endunless>
                    @foreach ($services as $service)
                        <li>
                            <a @class(['megamenu__item', 'is-active' => $service['code'] === $first])
                                href="{{ SiteUrl::service($service['code']) }}"
                                data-mega-item data-code="{{ $service['code'] }}"
                                @if ($service['code'] === $currentService) aria-current="page" @endif>{{ Bidi::isolate($service['title'] ?? null) }}</a>
                        </li>
                    @endforeach
                </ul>
                <a class="megamenu__all link" href="{{ $href }}">{{ ui('cta.view_all_practice_areas') }}</a>
            </div>

            @foreach ($services as $service)
                @php $code = $service['code']; @endphp
                <div class="megamenu__preview" data-mega-preview="{{ $code }}" @if ($code !== $first) hidden @endif>
                    <p class="megamenu__card-title">{{ Bidi::isolate($service['title'] ?? null) }}</p>
                    <div class="megamenu__col megamenu__col--card">
                        <p class="megamenu__summary">{{ Bidi::isolate($service['home_summary'] ?? null) }}</p>
                        <x-d2.ui.button variant="ink" size="44" :href="SiteUrl::service($code)" class="megamenu__explore">{{ ui('cta.explore_service') }}</x-d2.ui.button>
                    </div>
                    @if (! empty($service['documents']))
                        <div class="megamenu__col megamenu__col--docs">
                            <p class="megamenu__docs-title" id="{{ $panelId }}-{{ $code }}-docs">{{ $service['sections']['documents']['heading'] ?? '' }}</p>
                            <ul class="megamenu__docs" aria-labelledby="{{ $panelId }}-{{ $code }}-docs">
                                @foreach (array_slice($service['documents'], 0, 4) as $document)
                                    <li>{{ Bidi::isolate($document) }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</li>
