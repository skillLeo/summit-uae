@props([
    'type' => 'text',
    'icon' => null,          // leading icon (search fields)
    'iconEnd' => null,       // trailing decorative icon (date: calendar)
    'size' => 48,            // 48 (--field-h) | 40 (Design 2.0's compact field: drawn 44 px, the minimum target; 48 on touch)
    'invalid' => null,       // error look + aria-invalid (default: the surrounding field's state)
    'valid' => false,        // success look (green edge + check)
    'wrapPlaceholder' => false, // the placeholder wraps inside the field instead of being cut (D09; size 48)
    'wrapClass' => null,     // classes for the wrapper; every other attribute lands on the <input>
])
{{--
    <x-d2.ui.input name="email" type="email" autocomplete="email" />
    Design 2.1 (brief §3.5): white, 1px --field-border (≥ 3:1), radius 6, 48 px, no shadow, the 2px focus ring.
    A leading icon is functional (search). Pass-through attributes go
    to the <input> (id, name, value, placeholder, x-model, x-bind:…, aria-*, data-*). Inside
    <x-d2.ui.field> it is wired to the label, hint and error (see field.blade.php). States: .is-invalid /
    [aria-invalid="true"], .is-valid, :disabled, :read-only. Styleguide-only: .is-hover, .is-focus.
    Phone, e-mail and URL fields: add dir="ltr" (the text stays left-to-right in Arabic).

    Long placeholders (D09, brief §3.6 rule 8: approved copy is never cut):
    <x-d2.ui.input type="search" icon="search" :placeholder="ui('faq.search_placeholder')" wrap-placeholder />
    A native placeholder cannot wrap, so a field narrower than its placeholder (a long approved example on a phone)
    would cut it at the edge. With wrap-placeholder the wrapper draws the field (.control--wrap: edge, radius, fill,
    hover, focus ring) around the borderless input, and a copy of the placeholder (.control__placeholder,
    aria-hidden) is drawn inside it as text that wraps, from the input's own line: the field grows by one line
    height per extra line while it is empty and is 48 px again once a character is typed (CSS :placeholder-shown,
    no script). The placeholder attribute stays the accessible one (not painted while the copy shows). A press
    anywhere in the field puts the caret in it (modules/control-wrap.js). Without :has() support the plain field
    with its native placeholder remains. Size 48 only (ignored for size 40 or without a placeholder).
--}}
@php
    // The <x-d2.ui.field> around this control, if any: Blade renders a component's slot while that
    // component is still on the factory's component stack, so the nearest field is found by walking it.
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
    $fieldError = filled($field['error'] ?? null);
    $fieldInvalid = $fieldError && ($field['invalid'] ?? true);
    $invalid ??= $fieldInvalid;
    $describedBy = array_filter([
        ...explode(' ', (string) $attributes->get('aria-describedby', '')),
        $fieldId && filled($field['hint'] ?? null) ? $fieldId.'-hint' : null,
        $fieldId && $fieldInvalid ? $fieldId.'-error' : null,
    ]);
    $size = (int) $size === 40 ? 40 : 48;
    $placeholder = trim((string) $attributes->get('placeholder', ''));
    $wrap = $wrapPlaceholder && $placeholder !== '' && $size === 48;
    $control = $attributes->except(['aria-describedby'])->merge(array_filter([
        'id' => $fieldId,
        'required' => ! empty($field['required']) ?: null,
        'aria-describedby' => $describedBy ? implode(' ', array_unique($describedBy)) : null,
        'aria-invalid' => $invalid ? 'true' : null,
    ]));
@endphp
{{-- Size 40: the wrapper is a <label> (a click anywhere on it focuses the field); the field itself is 44 px. --}}
<{{ $size === 40 ? 'label' : 'span' }} @class([
    'control',
    'control--40' => $size === 40,
    'control--icon' => $icon,
    'control--end' => $iconEnd,
    'control--'.$type => in_array($type, ['date', 'number', 'search'], true),
    'control--wrap' => $wrap,
    (string) $wrapClass => filled($wrapClass),
]) @if ($attributes->has('dir') && $type !== 'date') dir="{{ $attributes->get('dir') }}" @endif @if ($wrap) data-control-wrap @endif>
    @if ($icon)
        <span class="control__icon" aria-hidden="true"><x-d2.ui.icon :name="$icon" size="20" /></span>
    @endif
    <input {{ $control->class(['input', 'is-invalid' => $invalid, 'is-valid' => $valid && ! $invalid]) }} type="{{ $type }}">
    @if ($wrap)
        <span class="control__placeholder" aria-hidden="true">{{ $placeholder }}</span>
    @endif
    @if ($iconEnd)
        <span class="control__icon control__icon--end" aria-hidden="true"><x-d2.ui.icon :name="$iconEnd" size="20" /></span>
    @else
        <span class="control__status" aria-hidden="true"><x-d2.ui.icon name="check" size="20" /></span>
    @endif
</{{ $size === 40 ? 'label' : 'span' }}>
