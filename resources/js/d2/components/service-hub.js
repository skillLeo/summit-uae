/**
 * d2ServiceHub: the audience filter of the P02 practice-areas hub
 * (resources/views/designs/d2/inner/practice-areas.blade.php, upgrade spec §12 D).
 *
 * Without JavaScript the chips are links (?audience=business#services) and the server hides the other cards.
 * Here a plain click filters in place instead:
 * - the chosen chip gets aria-current="true" (the pressed ink look), the others lose it; focus stays on it;
 * - cards whose data-audiences lack the audience get [hidden]; the cards that appear fade and settle in
 *   (transform + opacity on the house curve, 240 ms); under reduced motion everything is instant;
 * - the URL follows with history.replaceState (no history entries), so a reload or a shared link shows the
 *   same filter (the server validates ?audience= the same way: audienceFromSearch);
 * - the server-rendered, pluralised result of the chip (data-pa-result) is announced politely.
 * Modified clicks (new tab, new window) keep the link's own behaviour.
 * Phones: each card's "This service may be relevant if:" disclosure ([data-pa-fit-toggle], aria-expanded) opens
 * and closes its situations in place (.is-open on [data-pa-fit]); the CSS folds them from the first paint
 * (html.js), so mounting moves nothing.
 */
import { announce } from '../lib/announce.js';
import { reducedMotion } from '../lib/prefs.js';
import { audienceFromSearch, matchesAudience, searchForAudience } from './service-filter.js';

const EASE = 'cubic-bezier(0.22, 1, 0.36, 1)'; // --ease-out
const ENTER_MS = 240; // --dur-enter

const modified = (event) => event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;

export default (Alpine) => {
    Alpine.data('d2ServiceHub', () => {
        // DOM handles and running animations stay outside Alpine's reactive state.
        let cards = [];
        let chips = [];
        let current = 'all';
        let running = [];
        let offFit = null;

        function press(chip) {
            chips.forEach((c) => {
                if (c === chip) c.setAttribute('aria-current', 'true');
                else c.removeAttribute('aria-current');
            });
        }

        function apply(audience, { animate = true } = {}) {
            running.forEach((animation) => animation.cancel());
            running = [];
            const appearing = [];
            cards.forEach((card) => {
                const show = matchesAudience(card.dataset.audiences, audience);
                if (show && card.hidden) appearing.push(card);
                card.hidden = !show;
            });
            if (!animate || reducedMotion()) return;
            appearing.forEach((card, i) => {
                if (typeof card.animate !== 'function') return;
                running.push(
                    card.animate(
                        [
                            { opacity: 0, transform: 'translateY(8px)' },
                            { opacity: 1, transform: 'none' },
                        ],
                        { duration: ENTER_MS, delay: Math.min(i, 6) * 30, easing: EASE, fill: 'backwards' },
                    ),
                );
            });
        }

        return {
            init() {
                const root = this.$root;
                cards = Array.from(root.querySelectorAll('[data-pa-grid] > [data-service]'));
                chips = Array.from(root.querySelectorAll('[data-pa-audience]'));
                // The server rendered the filter of the URL (?audience=…); trust the URL over a stale chip.
                const fromUrl = audienceFromSearch(window.location.search);
                const chip = chips.find((c) => c.dataset.paAudience === fromUrl) || chips[0];
                current = chip ? chip.dataset.paAudience : 'all';
                if (chip) press(chip);
                apply(current, { animate: false });

                // Phone disclosures of the situations: one delegated listener for the eleven cards.
                const onFit = (event) => {
                    const toggle = event.target instanceof Element ? event.target.closest('[data-pa-fit-toggle]') : null;
                    const fit = toggle?.closest('[data-pa-fit]');
                    if (!fit || !root.contains(fit)) return;
                    const open = !fit.classList.contains('is-open');
                    fit.classList.toggle('is-open', open);
                    toggle.setAttribute('aria-expanded', String(open));
                };
                root.addEventListener('click', onFit);
                offFit = () => root.removeEventListener('click', onFit);
            },

            destroy() {
                running.forEach((animation) => animation.cancel());
                running = [];
                offFit?.();
                offFit = null;
            },

            /** x-on:click on the chip group (delegated). */
            pick(event) {
                const chip = event.target.closest('[data-pa-audience]');
                if (!chip || !this.$root.contains(chip) || modified(event)) return;
                event.preventDefault();
                const audience = chip.dataset.paAudience || 'all';
                if (audience === current) return;
                current = audience;
                press(chip);
                apply(audience);
                try {
                    const { pathname, search } = window.location;
                    window.history.replaceState(window.history.state, '', `${pathname}${searchForAudience(search, audience)}#services`);
                } catch {
                    /* history unavailable (sandboxed frame): the filter still works */
                }
                announce(chip.dataset.paResult || '');
            },
        };
    });
};
