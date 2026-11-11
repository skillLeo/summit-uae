@props([
    'label' => null,         // label text; or use the slot
    'hint' => null,          // description under the label (aria-describedby)
    'id' => null,            // input id (default: derived from name + value)
    'wrapClass' => null,     // classes for the row; every other attribute lands on the <input>
])
{{--
    <x-d2.ui.radio name="situation" value="S06-1" :label="$situation" :checked="$picked === 'S06-1'" />
    18px ring (control edge ≥ 3:1); when checked the ring turns ink and the inner dot scales in on the snappy
    spring (shown at once under reduced motion). One <label for> per input (the text; two labels gave some
    screen readers an empty name, axe form-field-multiple-labels): the ring is a <span> whose input carries
    its own 44px hit area (48px on touch), so the ring and the label both select it. Group radios in
    <x-d2.ui.field as="fieldset" id … label …> (legend, hint and the group error). Native radios: arrow keys
    move the choice within the group and never leave it.
    Styleguide-only modifiers on the input: .is-hover, .is-focus.
--}}
@php
    $id ??= $attributes->get('id') ?? 'rd-'.substr(md5($attributes->get('name', '').'|'.$attributes->get('value', '').'|'.($label ?? $slot)), 0, 10);
    $hintId = filled($hint) ? $id.'-hint' : null;
    $describedBy = array_filter([...explode(' ', (string) $attributes->get('aria-describedby', '')), $hintId]);
    $control = $attributes->except(['id', 'aria-describedby'])->merge(array_filter([
        'aria-describedby' => $describedBy ? implode(' ', array_unique($describedBy)) : null,
    ]));
@endphp
<div @class(['radio', $wrapClass])>
    <span class="radio__control">
        <input {{ $control->class(['radio__input']) }} type="radio" id="{{ $id }}">
        <span class="radio__dot" aria-hidden="true"></span>
    </span>
    <div class="radio__text">
        <label class="radio__label" for="{{ $id }}">{{ $label ?? $slot }}</label>
        @if ($hintId)<p class="radio__hint" id="{{ $hintId }}">{{ $hint }}</p>@endif
    </div>
</div>
