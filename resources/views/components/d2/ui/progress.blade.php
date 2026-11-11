@props([
    'value' => 0,
    'max' => 100,
    'label' => null,         // visible label and the accessible name; without it pass aria-label
    'valueText' => null,     // visible value at the end of the label row ("2 of 4"); or use the slot
    'labelHidden' => false,  // keep the label for assistive technology only
    'wrapClass' => null,     // classes for the wrapper; every other attribute lands on the <progress>
])
{{--
    <x-d2.ui.progress :value="2" :max="4" :label="…" :value-text="…" />
    A native <progress> (role progressbar; value and max are announced) drawn as a 4px ink bar on a --line
    track (white on dark). When the value changes the bar grows on the --spring-bar curve. Update it
    from Alpine with x-bind:value on the component (pass-through attribute) and the visible value with an
    x-text element in the slot. The bar fills from the inline start (right to left in Arabic).
--}}
@php
    $max = is_numeric($max) && (float) $max > 0 ? $max + 0 : 100;
    $value = is_numeric($value) ? max(0, min($value + 0, $max)) : 0;
    $shown = filled($valueText) || trim((string) $slot) !== '';
    $base = $attributes->get('id') ?? 'pg-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8));
    $labelId = filled($label) ? $base.'-label' : null;
@endphp
<div @class(['progress', $wrapClass])>
    @if ($labelId || $shown)
        <div @class(['progress__head', 'sr-only' => $labelHidden])>
            @if ($labelId)<span class="progress__label" id="{{ $labelId }}">{{ $label }}</span>@endif
            @if ($shown)<span class="progress__value">@if (filled($valueText)){{ $valueText }}@else{{ $slot }}@endif</span>@endif
        </div>
    @endif
    <progress {{ $attributes->class(['progress__bar']) }} value="{{ $value }}" max="{{ $max }}"
        @if ($labelId) aria-labelledby="{{ $labelId }}" @endif>{{ $value }} / {{ $max }}</progress>
</div>
