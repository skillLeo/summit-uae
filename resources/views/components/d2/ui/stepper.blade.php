@props([
    'min' => null,
    'max' => null,
    'step' => 1,
    'label' => null,         // what the buttons change, for their names ("Decrease …"); default: the field label
    'size' => 48,            // 48 | 40
    'invalid' => null,
    'valid' => false,
    'wrapClass' => null,     // classes for the stepper box; every other attribute lands on the <input>
])
{{--
    <x-d2.ui.field id="g-leave" label="…"><x-d2.ui.stepper name="unused_leave_days" min="0" max="365" value="0" /></x-d2.ui.field>
    A number input between minus and plus buttons; it fills its column like every field (D33). The input is the
    source of truth: the buttons call its
    native stepUp()/stepDown() (min, max and step apply) and fire `input` + `change`, so x-model and listeners
    see every change; typing works as usual and ArrowUp/ArrowDown step natively. The buttons sit outside the
    tab order (the input already steps from the keyboard), repeat while held, and turn aria-disabled at the
    limits. Without JavaScript the buttons are hidden and the plain number input remains.
--}}
@php
    $field = (function (): array {
        foreach (array_reverse(array_keys($this->componentStack)) as $i) {
            $view = $this->componentStack[$i];
            if (is_string($view) && str_ends_with($view, 'd2.ui.field')) {
                return $this->componentData[$i] ?? [];
            }
        }

        return [];
    })->call($__env);
    $name = $label ?? ($field['label'] ?? null);
    $decrease = filled($name) ? d2p('controls.decrease_named', ['label' => $name]) : d2p('controls.decrease');
    $increase = filled($name) ? d2p('controls.increase_named', ['label' => $name]) : d2p('controls.increase');
    $size = (int) $size === 40 ? 40 : 48;
    // Whole steps get the digits-only keypad on phones; fractional steps also need the decimal key.
    $inputmode = is_numeric($step) && floor((float) $step) !== (float) $step ? 'decimal' : 'numeric';
@endphp
<div @class(['stepper', 'stepper--40' => $size === 40, $wrapClass]) x-data="d2Stepper" x-on:input="sync" x-on:change="sync">
    <button type="button" class="stepper__btn stepper__btn--down" tabindex="-1" aria-label="{{ $decrease }}" @proposed
        data-step="-1" x-on:click="click" x-on:pointerdown="press" x-on:pointerup="release" x-on:pointerleave="release" x-on:pointercancel="release">
        <x-d2.ui.icon name="minus" size="20" />
    </button>
    <x-d2.ui.input type="number" :inputmode="$inputmode" :min="$min" :max="$max" :step="$step" :size="$size"
        :invalid="$invalid" :valid="$valid" wrap-class="stepper__field" class="stepper__input" x-ref="input" {{ $attributes }} />
    <button type="button" class="stepper__btn stepper__btn--up" tabindex="-1" aria-label="{{ $increase }}" @proposed
        data-step="1" x-on:click="click" x-on:pointerdown="press" x-on:pointerup="release" x-on:pointerleave="release" x-on:pointercancel="release">
        <x-d2.ui.icon name="plus" size="20" />
    </button>
</div>
