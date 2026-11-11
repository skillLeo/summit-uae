/**
 * Swipe between the calculators on phones (upgrade spec §8 "Gestures: swipe between Tools tabs").
 * Lazy chunk: d2Tools (components/tools.js) imports it below 768 px, when the tools section comes near.
 *
 * The panels area carries `touch-action: pan-y pinch-zoom` on phones (sections/tools.css): the browser
 * keeps vertical scrolling and pinch zoom to itself (a vertical drag is never held up by this script: it
 * ends with pointercancel), and only a horizontal drag reaches us as pointer events. The open tool follows
 * the finger (transform and opacity only, never under reduced motion); past a quarter of the panel width
 * (64 px at least) or on a flick it opens the neighbouring tool in the reading direction (RTL mirrors it),
 * otherwise it slides back. The switch is the same as a tap on the tool's tab (<x-d2.ui.tabs>, D35), so the
 * tab indicator, the shown panel, the address and the direction the new tool enters from (d2Tools) stay in sync.
 * No wrap-around: a drag past the first or last tool only resists. Ignored: mouse pointers, drags that start
 * on a form control, inside anything that scrolls sideways (the gratuity scope control) or within 24 px of
 * a screen edge (the system back gesture lives there).
 *
 * Pure helpers (unit tested in swipe.test.js): axis(), committed(), neighbour(), resist().
 */
import { reducedMotion, scrollBehavior } from '../lib/prefs.js';

export const SLOP = 10; // px of movement before the gesture is classified
export const RATIO = 1.2; // a horizontal drag beats the vertical movement by this factor
export const MIN_DISTANCE = 64; // px: the shortest committed drag
export const SHARE = 0.25; // or this share of the panel width, whichever is larger
export const FLICK = 0.5; // px/ms: a quick flick commits a shorter drag
export const EDGE = 24; // px from either screen edge: left to the browser's back gesture
const RESISTANCE = 0.25; // a drag towards a missing neighbour moves the tool a quarter of the way
const CLICK_GUARD = 400; // ms after a drag in which a click is not a tap on what was under the finger
const IGNORE = 'input, select, textarea, [contenteditable]:not([contenteditable="false"]), [data-swipe-ignore]';

/** The axis of a drag so far: 'x', 'y', or null while it is still inside the slop. */
export function axis(dx, dy, slop = SLOP) {
    const ax = Math.abs(dx);
    const ay = Math.abs(dy);
    if (ax < slop && ay < slop) return null;
    return ax > ay * RATIO ? 'x' : 'y';
}

/** Whether a finished horizontal drag opens the neighbour: far enough, or a flick in the same direction. */
export function committed(dx, width, velocity) {
    const distance = Math.abs(dx);
    const needed = Math.max(MIN_DISTANCE, (Number(width) || 0) * SHARE);
    if (distance >= needed) return true;
    return distance > SLOP * 2 && Math.abs(velocity) >= FLICK && Math.sign(velocity) === Math.sign(dx);
}

/**
 * The value a drag of dx (px, positive = finger to the right) leads to from `current` in `order`, or null at
 * either end. The next tool sits at the inline end: to the right in LTR, so a drag to the left opens it; to
 * the left in RTL, so a drag to the right does.
 */
export function neighbour(order, current, dx, rtl = false) {
    const index = order.indexOf(current);
    if (index === -1 || !dx) return null;
    const forward = rtl ? dx > 0 : dx < 0;
    const next = index + (forward ? 1 : -1);
    return next >= 0 && next < order.length ? order[next] : null;
}

/** The offset the open tool follows: the finger, or a quarter of it towards a missing neighbour. */
export function resist(dx, hasNeighbour) {
    return hasNeighbour ? dx : dx * RESISTANCE;
}

/** Something between the touched element and the panels area scrolls sideways (its own gesture). */
function scrollsSideways(el, stop) {
    for (let node = el; node && node !== stop; node = node.parentElement) {
        if (node.scrollWidth > node.clientWidth + 1) {
            const overflow = getComputedStyle(node).overflowX;
            if (overflow === 'auto' || overflow === 'scroll') return true;
        }
    }
    return false;
}

const INLINE = ['transition', 'translate', 'opacity'];

/**
 * Wire the gesture on the tools section: it holds the tabs ([data-tools-switch] with one [data-tab] per tool,
 * named by its panel's id) and the panels area (.tools__panels > [data-tool-panel]). Returns a teardown.
 */
export function mount(section) {
    const area = section.querySelector('.tools__panels');
    const tabs = Array.from(section.querySelectorAll('[data-tools-switch] [data-tab]'));
    if (!area || tabs.length < 2) return () => {};
    const order = tabs.map((tab) => tab.dataset.tab);
    const phone = window.matchMedia('(width < 768px)');
    const rtl = () => getComputedStyle(section).direction === 'rtl';
    const current = () => tabs.find((tab) => tab.getAttribute('aria-selected') === 'true')?.dataset.tab ?? order[0];
    const panelOf = (value) => document.getElementById(value);

    let gesture = null; // { id, x, y, mode, panel, dx, lastX, lastT, velocity }
    let guardUntil = 0;

    const clear = (panel) => INLINE.forEach((name) => panel?.style.removeProperty(name));

    // The open tool follows the finger (no transition while dragging).
    const follow = (panel, dx) => {
        if (!panel || reducedMotion()) return;
        const width = Math.max(1, area.clientWidth);
        panel.style.setProperty('transition', 'none');
        panel.style.setProperty('translate', `${Math.round(dx)}px 0`);
        panel.style.setProperty('opacity', String(Math.max(0.4, 1 - (Math.abs(dx) / width) * 0.6)));
    };

    // Back into place on the stylesheet's own transition (sections/tools.css: 240 ms, motion allowed only).
    const settle = (panel) => {
        if (!panel) return;
        if (!panel.style.translate) {
            clear(panel);
            return;
        }
        panel.style.removeProperty('transition');
        void panel.offsetWidth; // the drag offset is where the transition starts
        panel.style.removeProperty('translate');
        panel.style.removeProperty('opacity');
    };

    const open = (value) => {
        const tab = tabs.find((t) => t.dataset.tab === value);
        if (!tab) return;
        tab.click(); // d2Tabs: the same switch as a tap on the tab
        // The other tool starts at its top: bring the switch back into view when it was scrolled past.
        const control = section.querySelector('[data-tools-switch]');
        const header = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--header-h')) || 0;
        if (control && control.getBoundingClientRect().top < header) {
            window.requestAnimationFrame(() => control.scrollIntoView({ block: 'start', behavior: scrollBehavior() }));
        }
    };

    function onDown(event) {
        if (gesture || !phone.matches || !event.isPrimary || event.button !== 0) return;
        if (event.pointerType !== 'touch' && event.pointerType !== 'pen') return;
        if (event.clientX < EDGE || event.clientX > window.innerWidth - EDGE) return;
        const target = event.target instanceof Element ? event.target : null;
        if (!target || target.closest(IGNORE) || scrollsSideways(target, area)) return;
        gesture = { id: event.pointerId, x: event.clientX, y: event.clientY, mode: null, panel: panelOf(current()), dx: 0, lastX: event.clientX, lastT: event.timeStamp, velocity: 0 };
    }

    function onMove(event) {
        if (!gesture || event.pointerId !== gesture.id) return;
        const dx = event.clientX - gesture.x;
        if (!gesture.mode) {
            gesture.mode = axis(dx, event.clientY - gesture.y);
            if (!gesture.mode) return;
            if (gesture.mode === 'y') {
                gesture = null; // a vertical drag: the page scrolls, the browser already has it
                return;
            }
            try {
                area.setPointerCapture(event.pointerId);
            } catch {
                /* the pointer is gone: its next event ends the gesture */
            }
        }
        const dt = event.timeStamp - gesture.lastT;
        if (dt > 0) gesture.velocity = (event.clientX - gesture.lastX) / dt;
        gesture.lastX = event.clientX;
        gesture.lastT = event.timeStamp;
        gesture.dx = dx;
        follow(gesture.panel, resist(dx, neighbour(order, current(), dx, rtl()) !== null));
    }

    function onUp(event) {
        if (!gesture || event.pointerId !== gesture.id) return;
        const done = gesture;
        gesture = null;
        if (done.mode !== 'x') return;
        guardUntil = performance.now() + CLICK_GUARD;
        const target = neighbour(order, current(), done.dx, rtl());
        if (target && committed(done.dx, area.clientWidth, done.velocity)) {
            clear(done.panel); // it leaves at once (display: none); the other tool enters from the far side
            open(target);
        } else {
            settle(done.panel);
        }
    }

    function onCancel(event) {
        if (!gesture || event.pointerId !== gesture.id) return;
        // A touch is implicitly captured by the element it started on: taking the capture for the area
        // makes that element lose it, and its lostpointercapture bubbles here. Only the area's own counts.
        if (event.type === 'lostpointercapture' && event.target !== area) return;
        if (gesture.mode === 'x') settle(gesture.panel);
        gesture = null;
    }

    // A click right after a drag is not a tap on whatever was under the finger.
    function onClick(event) {
        if (performance.now() >= guardUntil) return;
        guardUntil = 0;
        event.preventDefault();
        event.stopPropagation();
    }

    const onMedia = () => {
        if (gesture?.panel) clear(gesture.panel);
        gesture = null;
    };

    area.addEventListener('pointerdown', onDown);
    area.addEventListener('pointermove', onMove);
    area.addEventListener('pointerup', onUp);
    area.addEventListener('pointercancel', onCancel);
    area.addEventListener('lostpointercapture', onCancel);
    area.addEventListener('click', onClick, true);
    phone.addEventListener('change', onMedia);

    return () => {
        onMedia();
        area.removeEventListener('pointerdown', onDown);
        area.removeEventListener('pointermove', onMove);
        area.removeEventListener('pointerup', onUp);
        area.removeEventListener('pointercancel', onCancel);
        area.removeEventListener('lostpointercapture', onCancel);
        area.removeEventListener('click', onClick, true);
        phone.removeEventListener('change', onMedia);
    };
}
