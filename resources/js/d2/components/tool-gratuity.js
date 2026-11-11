/**
 * d2ToolGratuity: <x-d2.tool.gratuity> (resources/views/components/d2/tool/gratuity.blade.php).
 * Thin on purpose: the estimator's page script (resources/js/d2/tools/gratuity-view.js, with the pure
 * formula in gratuity.js) is a lazy chunk, loaded when the tool nears the viewport or is focused. Every
 * listener of the estimate is attached there, so the markup needs no Alpine expressions.
 *
 * Here only the sector's two controls (D35) stay in step at once, before the chunk arrives: the segmented
 * control's radios are the value (data-input="scope"); the select that replaces it on phones and wherever its
 * options do not fit sets the matching radio and fires its change, and a radio change sets the select.
 */
import { whenNear } from '../tools/lazy.js';

export function bindScope(root) {
    const group = root.querySelector('[data-scope-control] [data-input="scope"]');
    const select = root.querySelector('[data-scope-control] [data-scope-select]');
    if (!group || !select) return;
    const checked = () => group.querySelector('input[type="radio"]:checked')?.value ?? '';
    select.value = checked() || select.value;
    select.addEventListener('change', () => {
        const radio = Array.from(group.querySelectorAll('input[type="radio"]')).find((input) => input.value === select.value);
        if (!radio || radio.checked) return;
        radio.checked = true;
        radio.dispatchEvent(new Event('change', { bubbles: true }));
    });
    group.addEventListener('change', () => {
        const value = checked();
        if (value && select.value !== value) select.value = value;
    });
}

export default (Alpine) => {
    Alpine.data('d2ToolGratuity', () => ({
        init() {
            bindScope(this.$root);
            whenNear(this.$root, () => import('../tools/gratuity-view.js'));
        },
    }));
};
