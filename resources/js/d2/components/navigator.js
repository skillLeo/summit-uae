/**
 * d2Navigator: the Matter Navigator on the home page (resources/views/designs/d2/sections/home/navigator.blade.php).
 *
 * A thin Alpine shell: the controller (resources/js/d2/navigator/controller.js) is a lazy chunk, loaded
 * when the section comes within a screen and a half of the viewport, on the first focus or pointer press
 * inside it, or at once when the page opens on a starting file (#navigator=S05-2, or ?navigator=S05-2
 * from the no-JavaScript form). Until then the server-rendered list and card work as plain HTML.
 * Eager, so they work before the controller arrives (final pass, home-mobile):
 *   - the service rows below 1280 px: a press on a row's disclosure ([data-nav-toggle]) opens or closes its
 *     situations (navigator/groups.js); closing a row whose label was stuck under the header brings the row
 *     back into view;
 *   - the slot order: without JavaScript a chosen file is rendered before the list (a phone lands on it); here
 *     the slot goes back after the form, where the two-column layout and the focus order expect it (the slot is
 *     the placed second column from 1280 px and hidden below, so nothing moves on screen).
 * No expressions in the markup: x-data="d2Navigator" is the only Alpine attribute.
 */
import { nextOpen, setGroupOpen } from '../navigator/groups.js';

const DEEP_LINK = /^#navigator=S(?:0[1-9]|1[01])-[1-9]$/;

export default (Alpine) => {
    Alpine.data('d2Navigator', () => {
        let controller = null;
        let observer = null;
        let started = false;
        let destroyed = false;
        let onHash = null;
        let onToggle = null;
        let root = null;

        return {
            init() {
                root = this.$root;

                const form = root.querySelector('[data-nav-form]');
                const slot = root.querySelector('[data-nav-slot]');
                if (form && slot && slot.nextElementSibling === form) form.after(slot);

                onToggle = (event) => {
                    const button = event.target instanceof Element ? event.target.closest('[data-nav-toggle]') : null;
                    const group = button?.closest('[data-nav-group]');
                    if (!group || !root.contains(group)) return;
                    const open = nextOpen(group);
                    setGroupOpen(group, open);
                    if (open) return;
                    const label = button.closest('.nav-group__label');
                    const header = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--header-h')) || 56;
                    if (label && label.getBoundingClientRect().top < header) label.scrollIntoView({ block: 'start', behavior: 'auto' });
                };
                root.addEventListener('click', onToggle);

                const start = () => {
                    if (started || destroyed) return;
                    started = true;
                    observer?.disconnect();
                    if (onHash) window.removeEventListener('hashchange', onHash);
                    import('../navigator/controller.js')
                        .then(({ mount }) => {
                            if (!destroyed) controller = mount(root);
                        })
                        .catch((error) => console.error(error));
                };

                const urgent = DEEP_LINK.test(window.location.hash) || root.querySelector('input[name="navigator"]:checked');
                if (urgent || !('IntersectionObserver' in window)) {
                    start();
                    return;
                }
                observer = new IntersectionObserver((entries) => {
                    if (entries.some((entry) => entry.isIntersecting)) start();
                }, { rootMargin: '150% 0px' });
                observer.observe(root);
                root.addEventListener('focusin', start, { once: true });
                root.addEventListener('pointerdown', start, { once: true });
                onHash = () => {
                    if (DEEP_LINK.test(window.location.hash)) start();
                };
                window.addEventListener('hashchange', onHash);
            },

            destroy() {
                destroyed = true;
                observer?.disconnect();
                if (onHash) window.removeEventListener('hashchange', onHash);
                if (onToggle) root?.removeEventListener('click', onToggle);
                controller?.destroy();
            },
        };
    });
};
