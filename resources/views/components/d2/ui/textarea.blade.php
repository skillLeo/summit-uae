@props([
    'value' => null,         // text content (or the slot)
    'rows' => 5,
    'invalid' => null,       // error look + aria-invalid (default: the surrounding field's state)
    'valid' => false,
])
{{--
    <x-d2.ui.textarea name="summary" maxlength="5000" :value="old('summary')" />
    Same look as the input (white, 1px --field-border, radius 6, no shadow, focus ring, .is-invalid / .is-valid),
    grows with its content where the browser supports field-sizing (min 5 lines). Pass-through attributes
    go to the <textarea>; wired to <x-d2.ui.field> like the input.
--}}
@php
    $field = (function (): array {
        foreach (array_reverse(array_keys($this->componentStack)) as $i) {
            $view = $this->componentStack[$i];
            if (is_string($view) && str_ends_with($view, 'd2.ui.field')) {
                $data = $this->componentData[$i] ?? [];

                return ($data['as'] ?? 'div') === 'fieldset' ? [] : $data;
            }
        }

        return [];
    })->call($__env);

    $fieldId = $field['id'] ?? null;
    $fieldInvalid = filled($field['error'] ?? null) && ($field['invalid'] ?? true);
    $invalid ??= $fieldInvalid;
    $describedBy = array_filter([
        ...explode(' ', (string) $attributes->get('aria-describedby', '')),
        $fieldId && filled($field['hint'] ?? null) ? $fieldId.'-hint' : null,
        $fieldId && $fieldInvalid ? $fieldId.'-error' : null,
    ]);
    $control = $attributes->except(['aria-describedby'])->merge(array_filter([
        'id' => $fieldId,
        'required' => ! empty($field['required']) ?: null,
        'aria-describedby' => $describedBy ? implode(' ', array_unique($describedBy)) : null,
        'aria-invalid' => $invalid ? 'true' : null,
    ]));
@endphp
<textarea {{ $control->class(['textarea', 'is-invalid' => $invalid, 'is-valid' => $valid && ! $invalid]) }} rows="{{ (int) $rows }}">@if ($value !== null){{ $value }}@else{!! trim((string) $slot) !!}@endif</textarea>
