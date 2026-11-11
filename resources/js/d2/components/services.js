/**
 * d2Services: the home practice areas (resources/views/designs/d2/sections/home/services.blade.php,
 * Design 2.1 D21).
 *
 * The server renders every card (no-JS: all practice areas in the equal grid). Here:
 * - the audience chips ([data-svc-audience], aria-pressed) filter the cards by data-svc-audiences
 *   (config/audience_map.php → hero); non-matching cards get [hidden]. The grid is equal (one, two or three
 *   columns, services.css), so the remaining cards simply close up in DOM order: no span planning;
 * - the move is a FLIP on transform with the ui spring from tokens.css (Web Animations), cards that join fade
 *   and settle in; under reduced motion everything is instant. A second click mid-move retargets from where
 *   the cards are;
 * - the result is announced politely through the layout's live region (lib/announce.js) with the
 *   server-rendered, pluralised text of the chip (data-svc-result). Focus stays on the chip;
 * - the root gets .is-ready: only then are the chips visible (their row is reserved from the first paint).
 */
import { announce } from '../lib/announce.js';
import { reducedMotion } from '../lib/prefs.js';
import { matches, offset } from './services-layout.js';

const HOUSE_EASE = 'cubic-bezier(0.22, 1, 0.36, 1)'; // --ease-out
const ENTER_MS = 240; // --dur-enter
const ENTER = [{ opacity: 0, transform: 'scale(0.96)' }, { opacity: 1, transform: 'none' }];

/** The ui spring (--spring-ui, --spring-ui-dur) as Web Animations timing. */
function springTiming() {
    const css = getComputedStyle(document.documentElement);
    const easing = css.getPropertyValue('--spring-ui').trim();
    const duration = parseFloat(css.getPropertyValue('--spring-ui-dur')) || 530;
    return { easing: easing || HOUSE_EASE, duration };
}

function play(el, frames, options) {
    try {
        return el.animate(frames, options);
    } catch {
        // linear() easing unknown to this engine: the same move on the house curve.
        return el.animate(frames, { ...options, easing: HOUSE_EASE });
    }
}

export default (Alpine) => {
    Alpine.data('d2Services', () => {
        // DOM handles and running animations live in the closure, outside Alpine's reactive state.
        let grid = null;
        let cards = [];
        let chips = [];
        let current = 'all';
        let running = [];

        function flip(before) {
            const rtl = getComputedStyle(grid).direction === 'rtl';
            const { easing, duration } = springTiming();
            for (const el of cards) {
                if (el.hidden) continue;
                const after = el.getBoundingClientRect();
                const was = before.get(el);
                if (!was) {
                    running.push(play(el, ENTER, { duration: ENTER_MS, easing: HOUSE_EASE }));
                    continue;
                }
                const d = offset(was, after, rtl);
                if (Math.abs(d.x) < 1 && Math.abs(d.y) < 1 && !d.resized) continue;
                const from = { transform: `translate(${d.x}px, ${d.y}px)` };
                const to = { transform: 'none' };
                if (d.resized) {
                    from.opacity = 0.6;
                    to.opacity = 1;
                }
                running.push(play(el, [from, to], { duration, easing }));
            }
        }

        return {
            init() {
                const root = this.$root;
                grid = root.querySelector('[data-svc-grid]');
                if (!grid) return;
                cards = Array.from(grid.querySelectorAll('[data-svc]'));
                chips = Array.from(root.querySelectorAll('[data-svc-audience]'));
                // The server may render a filter already applied (styleguide snapshot): start from its pressed chip.
                current = chips.find((c) => c.getAttribute('aria-pressed') === 'true')?.dataset.svcAudience || 'all';
                // The chips (reserved but invisible under .js, services.css) work now: show them.
                root.classList.add('is-ready');
            },

            destroy() {
                running.forEach((animation) => animation.cancel());
                running = [];
            },

            /** x-on:click on the chip group (delegated). */
            pick(event) {
                const chip = event.target.closest('[data-svc-audience]');
                if (!chip || !this.$root.contains(chip) || chip.getAttribute('aria-disabled') === 'true') return;
                const audience = chip.dataset.svcAudience;
                if (!grid || audience === current) return;
                current = audience;
                chips.forEach((c) => c.setAttribute('aria-pressed', c === chip ? 'true' : 'false'));

                const animate = !reducedMotion() && typeof grid.animate === 'function';
                // Measured before cancelling a running move, so a quick second click retargets smoothly.
                const before = animate ? new Map(cards.filter((el) => !el.hidden).map((el) => [el, el.getBoundingClientRect()])) : null;
                running.forEach((animation) => animation.cancel());
                running = [];

                cards.forEach((card) => {
                    card.hidden = !matches(card.dataset.svcAudiences, audience);
                });
                if (before) flip(before);
                announce(chip.dataset.svcResult || '');
            },
        };
    });
};
