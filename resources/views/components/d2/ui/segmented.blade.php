@props([
    'name',                  // radio group name (unique on the page)
    'options' => [],         // [[value, label], …] or [value => label, …]; a third item true disables that option
    'value' => null,         // checked value (default: the first enabled option)
    'label' => '',           // group name (the fieldset legend; visually hidden unless label-visible)
    'labelVisible' => false,
    'size' => 48,            // 48 (40px options) | 40 (32px options); the hit area stays ≥ 44px
    'full' => false,         // stretch to the container, options share the width equally
    'id' => null,            // id prefix for the radios (default: seg-{name})
])
{{--
    <x-d2.ui.segmented name="audience" :options="[['individuals', 'Individuals'], …]" value="individuals" label="…" />
    Radio-group semantics with native radios (visually hidden; the labels are the segments), so arrow keys,
    form posts and no-JS all work natively. With JS (Alpine d2Segmented) a white pill slides under the checked
    segment on the ui spring; without JS the checked segment itself is white.
    Events: the radios' native `change` bubbles (event.target.value); d2Segmented also dispatches a bubbling
    CustomEvent `d2-change` with detail {name, value}. Listen with x-on:change or x-on:d2-change.
    Styleguide-only modifiers on the root: .is-hover (unchecked segments), .is-focus (checked segment).
--}}
@php
    $opts = [];
    foreach ($options as $key => $option) {
        if (is_array($option)) {
            $opts[] = [(string) ($option[0] ?? $option['value'] ?? ''), (string) ($option[1] ?? $option['label'] ?? ''), (bool) ($option[2] ?? $option['disabled'] ?? false)];
        } else {
            $opts[] = [(string) $key, (string) $option, false];
        }
    }
    $value = $value !== null ? (string) $value : null;
    $values = array_column(array_filter($opts, fn ($o) => ! $o[2]), 0);
    if ($value === null || ! in_array($value, $values, true)) {
        $value = $values[0] ?? null;
    }
    $prefix = $id ?? 'seg-'.\Illuminate\Support\Str::slug($name);
    $size = (int) $size === 40 ? 40 : 48;
@endphp
<fieldset {{ $attributes->class(['segmented', 'segmented--'.$size, 'segmented--full' => $full]) }} @if ($id) id="{{ $id }}" @endif
    x-data="d2Segmented" x-on:change="sync" data-name="{{ $name }}">
    <legend @class(['segmented__legend', 'sr-only' => ! $labelVisible])>{{ $label }}</legend>
    <div class="segmented__scroller" x-ref="scroller">
        <div class="segmented__track" x-ref="track">
            <span class="segmented__pill" aria-hidden="true"></span>
            @foreach ($opts as $i => [$optValue, $optLabel, $optDisabled])
                <input class="segmented__input" type="radio" id="{{ $prefix }}-{{ $i }}" name="{{ $name }}" value="{{ $optValue }}"
                    @checked($optValue === $value) @disabled($optDisabled)>
                <label class="segmented__option" for="{{ $prefix }}-{{ $i }}">{{ $optLabel }}</label>
            @endforeach
        </div>
    </div>
</fieldset>
