/**
 * d2Stepper: the minus / plus buttons of <x-d2.ui.stepper> (resources/views/components/d2/ui/stepper.blade.php).
 *
 * The number input stays the source of truth. A button calls the input's native stepDown() / stepUp(), so
 * min, max and step behave exactly as they do for the arrow keys, then fires `input` and `change` (bubbling)
 * so x-model and the calculators see the new value. Held buttons repeat. At a limit the button turns
 * aria-disabled (it is outside the tab order, so focus is never lost).
 *
 * Pointer input steps on pointerdown (and repeats while held); keyboard or assistive-technology activation
 * arrives as a click with detail 0 and steps once. A pointer click (detail ≥ 1) is ignored because its
 * pointerdown already stepped.
 */
const HOLD_DELAY = 400; // ms before a held button starts repeating
const HOLD_RATE = 70; // ms between repeats

export default (Alpine) => {
    Alpine.data('d2Stepper', () => {
        let delay = 0;
        let repeat = 0;
        const stop = () => {
            clearTimeout(delay);
            clearInterval(repeat);
            delay = 0;
            repeat = 0;
        };

        return {
            init() {
                this.sync();
            },

            destroy() {
                stop();
            },

            input() {
                return this.$refs.input || this.$root.querySelector('input');
            },

            /** One step in the button's direction; true when the value changed. */
            nudge(button) {
                const input = this.input();
                if (!input || input.disabled || input.readOnly || button.getAttribute('aria-disabled') === 'true') return false;
                const before = input.value;
                try {
                    if (Number(button.dataset.step) < 0) input.stepDown();
                    else input.stepUp();
                } catch {
                    return false;
                }
                if (input.value === before) return false;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
                return true;
            },

            /** x-on:pointerdown: step now, repeat while held. Keeps focus where it is (the input, if typing). */
            press(event) {
                if (event.button !== 0) return;
                event.preventDefault();
                const button = event.currentTarget;
                stop();
                if (!this.nudge(button)) return;
                delay = setTimeout(() => {
                    repeat = setInterval(() => {
                        if (!this.nudge(button)) stop();
                    }, HOLD_RATE);
                }, HOLD_DELAY);
            },

            /** x-on:pointerup / pointerleave / pointercancel */
            release() {
                stop();
            },

            /** x-on:click: only keyboard / assistive-technology activation (a pointer already stepped). */
            click(event) {
                if (event.detail > 0) return;
                this.nudge(event.currentTarget);
            },

            /** x-on:input / x-on:change (typing, stepping): update the buttons at the limits. */
            sync() {
                const input = this.input();
                if (!input) return;
                const value = input.valueAsNumber;
                const min = input.min !== '' ? Number(input.min) : -Infinity;
                const max = input.max !== '' ? Number(input.max) : Infinity;
                const locked = input.disabled || input.readOnly;
                for (const button of this.$root.querySelectorAll('.stepper__btn')) {
                    const up = Number(button.dataset.step) > 0;
                    const atLimit = !Number.isNaN(value) && (up ? value >= max : value <= min);
                    button.setAttribute('aria-disabled', locked || atLimit ? 'true' : 'false');
                }
            },
        };
    });
};
