@props([
    'services' => [],          // App\Content\NavigatorData::services()
    'prefix' => 'navigator',
    'pressed' => '',           // service code of the pressed chip ('' = All)
    'chipStates' => [],        // styleguide only: [code => 'hover' | 'focus']
    'search' => null,          // styleguide only: extra classes for the search input (is-focus)
])
{{--
    Search field (the kit's dark field) and the practice-area chips: one toolbar (one Tab stop, arrow keys move,
    Enter / Space press); the chip labels are the approved short practice-area names of the consultation form
    (form.matter_type.option.Sxx). JavaScript only: hidden without it (.no-js), where the plain GET form lists every
    situation. Design 2.1 D30: from 768 px the chips wrap (every label whole); below 768 px they sit in one sideways
    rail that scrolls by touch, with a faded edge where more chips wait and no button over a label (navigator.css).
    Placeholder (Design 2.1 round 2, D09: a placeholder is never cut): the field's own proposed name,
    navigator.search_label ("Search situations"), so the text a visitor sees is the field's accessible name
    (WCAG 2.5.3) and it stays whole on one line in the narrowest phone field (320 px: 190 px of text room
    between the search glyph and the clear button; EN 130, RU 158, AR 103 px). The longer example line it
    replaces ("Type a word, for example cheque", 248 px) was cut below 390 px and is deleted in EN, AR and RU.
--}}
@php
    $cs = app()->isProduction() ? null : 'proposed';
    $name = d2p('navigator.search_label');
    $chips = [['', d2p('navigator.all'), $cs]];
    foreach ($services as $s) {
        $chips[] = [$s['code'], ui('form.matter_type.option.'.$s['code']), null];
    }
@endphp
<div {{ $attributes->class(['nav-tools']) }}>
    <x-d2.ui.field :id="$prefix.'-search'" :label="$name" label-hidden class="nav-tools__search" :data-copy-status="$cs">
        <x-d2.ui.input type="search" icon="search" :placeholder="$name" autocomplete="off"
            enterkeyhint="search" spellcheck="false" data-nav-search :class="$search" />
    </x-d2.ui.field>
    <div class="nav-chips" data-nav-chips-wrap>
        <div class="nav-chips__rail" data-nav-rail>
            <div class="nav-chips__list" role="toolbar" aria-label="{{ d2p('navigator.chips_label') }}" data-nav-chips @proposed>
                @foreach ($chips as [$code, $label, $status])
                    @php $state = $chipStates[$code] ?? null; @endphp
                    <x-d2.ui.chip variant="night" :pressed="$pressed === $code" data-nav-chip="{{ $code }}"
                        :tabindex="$pressed === $code ? '0' : '-1'" :data-copy-status="$status"
                        :class="\Illuminate\Support\Arr::toCssClasses(['is-hover' => $state === 'hover', 'is-focus' => $state === 'focus']) ?: null">{{ $label }}</x-d2.ui.chip>
                @endforeach
            </div>
        </div>
    </div>
</div>
