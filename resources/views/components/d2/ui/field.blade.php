@props([
    'id',                     // the control's id: label for=…, hint {id}-hint, error {id}-error
    'label',
    'hint' => null,           // help text under the label (aria-describedby)
    'error' => null,          // error message; rendered (hidden unless invalid) when given
    'invalid' => null,        // show the error now (default: whenever an error message is given)
    'required' => false,      // asterisk (decorative) + the control gets `required`
    'optionalLabel' => null,  // visible marker for optional fields: a string, or true for d2p('controls.optional')
    'as' => 'div',            // div (wraps one control) | fieldset (a group of radios/checkboxes; label = legend)
    'labelHidden' => false,   // keep the label for assistive technology only (e.g. a search field)
])
{{--
    <x-d2.ui.field id="cf-name" :label="ui('form.name.label')" :hint="ui('form.name.help')" :error="$errors->first('name')" required>
        <x-d2.ui.input name="name" autocomplete="name" />
    </x-d2.ui.field>

    Design 2.1 (brief §3.5): the label above (UI 15/500), the help text under the label (Small, ink-3), the error
    under the field (danger colour, the error icon and text). Steppers, selects and date pickers fill the column.
    The control inside (<x-d2.ui.input>, select, textarea, date, stepper) is wired automatically while it has
    no id of its own: it gets id = the field id, `required`, aria-describedby = hint id + error id (the error
    id only while the error is shown) and aria-invalid + .is-invalid only while it is shown. Put one control
    in a field (a second one must bring its own id). In a fieldset field the group carries aria-describedby.

    Scripts that validate in the browser: render the approved message with :invalid="false" (the element is
    in the DOM, hidden), then on a failed check unhide [data-field-error], add its id to the control's
    aria-describedby and set aria-invalid="true" + .is-invalid; undo all three when it passes. The field root
    carries data-field="{id}" and .is-invalid while the error shows.
--}}
@php
    $invalid = $invalid ?? filled($error);
    $isGroup = $as === 'fieldset';
    $hintId = filled($hint) ? $id.'-hint' : null;
    $errorId = filled($error) ? $id.'-error' : null;
    $optional = $optionalLabel === true ? d2p('controls.optional') : (filled($optionalLabel) ? $optionalLabel : null);
    $describedBy = implode(' ', array_filter([$hintId, $invalid ? $errorId : null]));
@endphp
@if ($isGroup)
<fieldset {{ $attributes->class(['field', 'field--group', 'is-invalid' => $invalid && $errorId]) }} id="{{ $id }}" data-field="{{ $id }}"
    @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif>
    <legend @class(['field__label', 'sr-only' => $labelHidden])>
        {{ $label }}@if ($required)<span class="field__req" aria-hidden="true">*</span>@endif
        @if ($optional)<span class="field__optional" @if ($optionalLabel === true) @proposed @endif>{{ $optional }}</span>@endif
    </legend>
@else
<div {{ $attributes->class(['field', 'is-invalid' => $invalid && $errorId]) }} data-field="{{ $id }}">
    <div @class(['field__head', 'sr-only' => $labelHidden])>
        <label class="field__label" for="{{ $id }}">{{ $label }}@if ($required)<span class="field__req" aria-hidden="true">*</span>@endif</label>
        @if ($optional)<span class="field__optional" @if ($optionalLabel === true) @proposed @endif>{{ $optional }}</span>@endif
    </div>
@endif
    @if ($hintId)
        <p class="field__hint" id="{{ $hintId }}">{{ $hint }}</p>
    @endif
    {{ $slot }}
    @if ($errorId)
        <p class="field__error" id="{{ $errorId }}" data-field-error @unless ($invalid) hidden @endunless>
            <x-d2.ui.icon name="error" size="16" class="field__error-icon" />
            <span class="field__error-text">{{ $error }}</span>
        </p>
    @endif
</{{ $isGroup ? 'fieldset' : 'div' }}>
