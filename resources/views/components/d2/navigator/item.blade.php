@props([
    'ref',                    // situation reference, e.g. S05-2 (service code + 1-based index)
    'situation',              // the approved situation text (P02 "This service may be relevant if:")
    'checked' => false,
    'prefix' => 'navigator',  // id prefix (styleguide specimens use their own)
    'name' => 'navigator',    // radio group name = the no-JavaScript query parameter (?navigator=S05-2)
    'state' => null,          // styleguide only: hover | focus (forced look)
])
{{--
    One row of the Matter Navigator list: the Phase A radio (18 px ring, inner dot on the snappy spring,
    gold on night). The whole row is the target (the label is stretched over it in navigator.css).
--}}
@php
    $forced = \Illuminate\Support\Arr::toCssClasses(['is-hover' => $state === 'hover', 'is-focus' => $state === 'focus']);
@endphp
<div {{ $attributes->class(['nav-item', 'is-hover' => $state === 'hover', 'is-focus' => $state === 'focus']) }} data-nav-item="{{ $ref }}">
    <x-d2.ui.radio :name="$name" :value="$ref" :id="$prefix.'-'.$ref" :checked="$checked"
        :label="\App\Design\D2\Bidi::isolate($situation)" wrap-class="nav-item__radio" :class="$forced ?: null" />
</div>
