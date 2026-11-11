@props([
    'options' => [],         // [[value, label], …] | [value => label, …] | groups (see below); or <option>s in the slot
    'value' => null,         // selected value
    'placeholder' => null,   // first option with an empty value ("Choose a language")
    'size' => 48,            // 48 | 40
    'invalid' => null,       // error look + aria-invalid (default: the surrounding field's state)
    'valid' => false,
    'wrapClass' => null,     // classes for the wrapper; every other attribute lands on the <select>
])
{{--
    <x-d2.ui.select name="preferred_language" :placeholder="ui('form.preferred_language.placeholder')"
        :options="[['en', ui('lang.en')], ['ar', ui('lang.ar')], ['ru', ui('lang.ru')]]" />
    Groups (<optgroup>): ['Group label' => [[value, label], …], …] or [['label' => …, 'options' => […]], …].
    An option given as [value, label, true] is disabled. Native <select> (the platform picker on phones),
    styled like the input, with a chevron at the inline end. Wired to <x-d2.ui.field> like the input.
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

    // Normalise every accepted shape to [['group' => ?string, 'options' => [[value, label, disabled], …]], …].
    $option = fn ($key, $item) => is_array($item)
        ? [(string) ($item[0] ?? $item['value'] ?? ''), (string) ($item[1] ?? $item['label'] ?? ''), (bool) ($item[2] ?? $item['disabled'] ?? false)]
        : [(string) $key, (string) $item, false];
    $groups = [];
    $loose = [];
    foreach ($options as $key => $item) {
        if (is_array($item) && isset($item['options']) && is_array($item['options'])) {
            $groups[] = ['group' => (string) ($item['label'] ?? ''), 'options' => array_map($option, array_keys($item['options']), $item['options'])];
        } elseif (is_string($key) && is_array($item) && array_is_list($item) && isset($item[0]) && is_array($item[0])) {
            $groups[] = ['group' => $key, 'options' => array_map($option, array_keys($item), $item)];
        } else {
            $loose[] = $option($key, $item);
        }
    }
    if ($loose !== []) {
        array_unshift($groups, ['group' => null, 'options' => $loose]);
    }
    $selected = $value !== null ? (string) $value : null;
    $size = (int) $size === 40 ? 40 : 48;
@endphp
<span @class(['control', 'control--select', 'control--40' => $size === 40, $wrapClass])>
    <select {{ $control->class(['select', 'is-invalid' => $invalid, 'is-valid' => $valid && ! $invalid]) }}>
        @if ($placeholder !== null)
            <option value="" @selected($selected === null || $selected === '')>{{ $placeholder }}</option>
        @endif
        @foreach ($groups as $group)
            @if ($group['group'] !== null)<optgroup label="{{ $group['group'] }}">@endif
            @foreach ($group['options'] as [$optValue, $optLabel, $optDisabled])
                <option value="{{ $optValue }}" @selected($selected !== null && $optValue === $selected) @disabled($optDisabled)>{{ $optLabel }}</option>
            @endforeach
            @if ($group['group'] !== null)</optgroup>@endif
        @endforeach
        {{ $slot }}
    </select>
    <span class="control__icon control__icon--end" aria-hidden="true"><x-d2.ui.icon name="chevron-down" size="20" /></span>
</span>
