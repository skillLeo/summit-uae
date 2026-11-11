/**
 * d2Tools: the calculators section (resources/views/designs/d2/pages/calculators.blade.php).
 *
 * The [Gratuity estimator | Rent increase check] choice is <x-d2.ui.tabs> (D35: underline tabs; d2Tabs shows the
 * chosen panel, handles the keys and the deep links #tool-gratuity / #tool-rent, and writes the address when the
 * visitor switches). This component adds:
 * - the side a tool enters from: every switch (tap, arrow keys, swipe, deep link) sets data-enter="end" (the next
 *   tool, from the inline end) or "start" on the section as the panel appears, and the panel's @starting-style
 *   slides it in from that side (sections/tools.css; RTL mirrors it);
 * - phones (< 768 px): the swipe between the two tools (lazy chunk tools/swipe.js, loaded when the section comes
 *   near), spec §8 "Gestures: swipe between Tools tabs".
 */
import { whenNear } from '../tools/lazy.js';

/** "end" when the tool at index `to` comes after `from` in the switch's order, "start" before it, else null. */
export function enterSide(from, to) {
    if (from < 0 || to < 0 || from === to) return null;
    return to > from ? 'end' : 'start';
}

export default (Alpine) => {
    Alpine.data('d2Tools', () => ({
        init() {
            const root = this.$root;
            const order = Array.from(root.querySelectorAll('[data-tools-switch] [data-tab]'), (tab) => tab.dataset.tab);
            let current = '';

            // d2Tabs announces every change (the first one too, with the panel it opened on).
            root.addEventListener('d2:tab', (event) => {
                const next = event.detail?.id ?? '';
                const side = enterSide(order.indexOf(current), order.indexOf(next));
                current = next;
                if (side) root.dataset.enter = side;
                else delete root.dataset.enter;
            });

            // Phones: swipe between the tools (loaded once, when the section is near; a later rotation to a phone
            // width arms it too).
            const phone = window.matchMedia('(width < 768px)');
            let armed = false;
            const arm = () => {
                if (armed || !phone.matches || order.length < 2) return;
                armed = true;
                whenNear(root, () => import('../tools/swipe.js'));
            };
            arm();
            phone.addEventListener('change', arm);
        },
    }));
};
