@props([
    'label' => null,         // label text; or use the slot (it may hold a link, e.g. the consent text)
    'hint' => null,          // description under the label (aria-describedby)
    'error' => null,         // error message; rendered (hidden unless invalid) when given
    'invalid' => null,       // show the error now (default: whenever an error message is given)
    'id' => null,            // input id (default: derived from name + value)
    'wrapClass' => null,     // classes for the row; every other attribute lands on the <input>
])
{{--
    <x-d2.ui.checkbox name="docs[]" value="contract" :label="$document" :checked="$done" />
    20px box (control edge ≥ 3:1), ink fill when checked and the tick draws in (static under reduced motion).
    One <label for> per input (the text; a second label around the box gave some screen readers an empty
    name): the box is a <span> whose input carries its own 44px hit area (48px on touch), so the box and the
    label both toggle it; the hint stays outside the label, so it is a description, not part of the name.
    Pass-through attributes go to the <input> (name, value, checked, required, x-model …). Indeterminate: set
    input.indeterminate in JS.
    Styleguide-only modifiers on the input: .is-hover, .is-focus.
--}}
@php
    $id ??= $attributes->get('id') ?? 'cb-'.substr(md5($attributes->get('name', '').'|'.$attributes->get('value', '').'|'.($label ?? $slot)), 0, 10);
    $invalid ??= filled($error);
    $hintId = filled($hint) ? $id.'-hint' : null;
    $errorId = filled($error) ? $id.'-error' : null;
    $describedBy = array_filter([
        ...explode(' ', (string) $attributes->get('aria-describedby', '')),
        $hintId,
        $invalid ? $errorId : null,
    ]);
    $control = $attributes->except(['id', 'aria-describedby'])->merge(array_filter([
        'aria-describedby' => $describedBy ? implode(' ', array_unique($describedBy)) : null,
        'aria-invalid' => $invalid && $errorId ? 'true' : null,
    ]));
@endphp
<div @class(['check', 'is-invalid' => $invalid && $errorId, $wrapClass])>
    <span class="check__control">
        <input {{ $control->class(['check__input', 'is-invalid' => $invalid && $errorId]) }} type="checkbox" id="{{ $id }}">
        <svg class="check__tick" viewBox="0 0 20 20" width="20" height="20" fill="none" aria-hidden="true" focusable="false">
            <path class="check__tick-path" d="M5.75 10.25 8.6 13.1 14.25 7.25" pathLength="1" />
            <path class="check__dash" d="M6 10h8" />
        </svg>
    </span>
    <div class="check__text">
        <label class="check__label" for="{{ $id }}">{{ $label ?? $slot }}</label>
        @if ($hintId)<p class="check__hint" id="{{ $hintId }}">{{ $hint }}</p>@endif
        @if ($errorId)
            <p class="field__error" id="{{ $errorId }}" data-field-error @unless ($invalid) hidden @endunless>
                <x-d2.ui.icon name="error" size="16" class="field__error-icon" />
                <span class="field__error-text">{{ $error }}</span>
            </p>
        @endif
    </div>
</div>
