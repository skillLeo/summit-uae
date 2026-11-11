/**
 * Accordion height spring where CSS cannot do it (brief 2.1 §6 "Micro-interactions": accordion height spring, at
 * most 250 ms). resources/css/d2/components/accordion.css springs <x-d2.ui.accordion-item> open and closed with
 * interpolate-size and ::details-content (Chrome, Edge); Safari and Firefox lack interpolate-size, so there the
 * item would snap. This module gives them the same spring with the Web Animations API: the <details> height runs
 * from its closed height to its open height (or back) on the --spring-ui curve in --spring-ui-dur (240 ms), its
 * content clipped meanwhile. Closing waits for the animation before removing [open], so the answer stays readable
 * while it folds away. A click during an animation turns it around from where it is.
 *
 * Does nothing where interpolate-size works (CSS is in charge), under prefers-reduced-motion (the final state at
 * once), for modified clicks, and for anything but an accordion item's own summary. Keyboard activation of the
 * summary (Enter, Space) is a click, so it springs too. Deep links (modules/accordion-hash.js) open items directly.
 *
 * Pure helper (unit tested in accordion-spring.test.js): springFrames().
 */
import { reducedMotion } from '../lib/prefs.js';

const ITEM = '.acc__item';

/** Keyframes and the [open] state to keep for a height animation from `from` px to `to` px. */
export function springFrames(from, to, opening) {
    const start = Math.max(0, Number(from) || 0);
    const end = Math.max(0, Number(to) || 0);
    return { keyframes: [{ blockSize: `${start}px` }, { blockSize: `${end}px` }], keepOpen: Boolean(opening) };
}

function tokens() {
    const style = getComputedStyle(document.documentElement);
    const duration = parseFloat(style.getPropertyValue('--spring-ui-dur')) || 240;
    const easing = style.getPropertyValue('--spring-ui').trim() || 'ease-out';
    return { duration, easing };
}

/** The height of the item with only its summary showing (summary plus the item's own padding and border). */
function closedHeight(details, summary) {
    const style = getComputedStyle(details);
    const chrome = ['paddingBlockStart', 'paddingBlockEnd', 'borderBlockStartWidth', 'borderBlockEndWidth']
        .reduce((sum, key) => sum + (parseFloat(style[key]) || 0), 0);
    return summary.getBoundingClientRect().height + chrome;
}

function run(details, summary) {
    const running = details._d2Spring;
    const current = details.getBoundingClientRect().height;
    if (running) {
        running.cancel();
        details._d2Spring = null;
    }
    // Opening when it is closed, or when it is closing right now.
    const opening = !details.open || Boolean(running && !running._d2Opening);
    if (opening) details.open = true;
    const target = opening ? details.scrollHeight : closedHeight(details, summary);
    const { keyframes } = springFrames(current, target, opening);
    const { duration, easing } = tokens();

    details.style.overflow = 'clip';
    // The spring overshoots a little: never below the summary row (CSS clamps ::details-content at 0 the same way).
    details.style.minBlockSize = `${Math.round(closedHeight(details, summary))}px`;
    let animation;
    try {
        animation = details.animate(keyframes, { duration, easing });
    } catch {
        animation = details.animate(keyframes, { duration, easing: 'ease-out' }); // no linear() support
    }
    animation._d2Opening = opening;
    details._d2Spring = animation;
    const done = () => {
        if (details._d2Spring !== animation) return;
        details._d2Spring = null;
        details.style.removeProperty('overflow');
        details.style.removeProperty('min-block-size');
        if (!opening) details.open = false;
    };
    animation.addEventListener('finish', done);
}

export default function accordionSpring() {
    if (typeof CSS !== 'undefined' && CSS.supports?.('interpolate-size', 'allow-keywords')) return;
    if (typeof Element === 'undefined' || typeof Element.prototype.animate !== 'function') return;

    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const summary = event.target instanceof Element ? event.target.closest('summary') : null;
        const details = summary ? summary.parentElement : null;
        if (!details || details.tagName !== 'DETAILS' || !details.matches(ITEM) || details.firstElementChild !== summary) return;
        // A control inside the summary keeps its own click.
        const control = event.target.closest('a[href], button, input, select, textarea, label');
        if (control && control !== summary && summary.contains(control)) return;
        if (reducedMotion()) return;
        event.preventDefault();
        run(details, summary);
    });
}
