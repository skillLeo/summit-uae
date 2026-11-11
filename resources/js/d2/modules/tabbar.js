/**
 * Mobile tab bar (< 768 px; resources/views/components/d2/shell/tabbar.blade.php, brief 2.1 §6 "Mobile"):
 * hides on scroll down once the page is scrolled past 24 px, shows again on scroll up (transform, 240 ms,
 * tabbar.css). It is never hidden while a sheet or modal is open, while keyboard focus is inside the bar,
 * while a text field has focus with a hardware keyboard, under prefers-reduced-motion (the bar simply stays)
 * or at the very end of the page (the body padding reserved for it is then covered by the bar, never left as
 * an empty strip). While the on-screen keyboard is open it steps out of the way instead (it would otherwise
 * ride on top of the keyboard in some browsers).
 *
 * The floating WhatsApp button ([data-wa-fab], rendered with the bar) follows the bar through CSS
 * (`body:has(> .tabbar.is-hidden)`) and steps aside (.is-away) while another WhatsApp control is on screen
 * (the hero's, the contact block's: one WhatsApp action in view at a time), while the site footer or the
 * consultation form is on screen (FAB_ZONES: it would float over their controls and last lines), while a sheet or
 * dialog is open and while a text field has focus (the on-screen keyboard).
 *
 * The four approved labels wrap to two lines where needed (tabbar.css); should they still not fit (a larger text
 * size, another font), the bar takes .is-tight (the Caption size) rather than cut a word.
 *
 * Pure helpers (unit tested in tabbar.test.js): scrollStep(), typing(), keyboardOpen(), fabAway(),
 * isWhatsAppLink(), overflowing().
 */
import { reducedMotion } from '../lib/prefs.js';

export const REVEAL_ZONE = 24; // px from the top: always shown
export const STEP = 6; // px of scroll in one direction before the bar reacts (ignores jitter)
const KEYBOARD_SHARE = 0.75; // a visual viewport shorter than 75 % of the window: an on-screen keyboard is up

/** Input types that bring up an on-screen keyboard or picker (not checkboxes, radios, buttons, sliders …). */
const KEYBOARD_INPUTS = /^(text|search|email|tel|url|number|password|date|datetime-local|month|time|week)$/;

/** The element takes typed input (text fields, text areas, selects, editable content). */
export function typing(el) {
    if (!el || typeof el !== 'object') return false;
    if (el.isContentEditable || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT') return true;
    return el.tagName === 'INPUT' && KEYBOARD_INPUTS.test(String(el.type || 'text').toLowerCase());
}

/** An on-screen keyboard is open: the visual viewport shrank without a pinch zoom. */
export function keyboardOpen(win = globalThis.window) {
    const viewport = win?.visualViewport;
    if (!viewport || !win.innerHeight) return false;
    if (Math.abs((viewport.scale || 1) - 1) > 0.01) return false;
    return viewport.height < win.innerHeight * KEYBOARD_SHARE;
}

/**
 * One scroll sample → the bar's next state. `state` { hidden, lastY }; `input` { y, end, pinned } where y
 * is the scroll position, end the largest scroll position and pinned forces the bar to stay.
 */
export function scrollStep(state, { y, end, pinned }) {
    if (pinned || y <= REVEAL_ZONE || y >= end - 2) return { hidden: false, lastY: y };
    const delta = y - state.lastY;
    if (Math.abs(delta) < STEP) return state;
    return { hidden: delta > 0, lastY: y };
}

/** The floating WhatsApp button steps aside while an overlay is open, a field is typed in or another WhatsApp control shows. */
export function fabAway({ overlays = 0, editing = false, others = 0 } = {}) {
    return overlays > 0 || !!editing || others > 0;
}

/** A link that opens WhatsApp (wa.me or api.whatsapp.com, the WA01 draft): the controls the button gives way to. */
export function isWhatsAppLink(href) {
    if (typeof href !== 'string' || !href) return false;
    try {
        const url = new URL(href, 'https://example.invalid/');
        return url.hostname === 'wa.me' || url.hostname === 'api.whatsapp.com';
    } catch {
        return false;
    }
}

/** What the floating WhatsApp button gives way to while it is on screen (above the bar). */
export const FAB_ZONES = [
    // The site footer: its own WhatsApp control, and the last lines of the page sit where the button floats.
    'body > .d2-footer',
    // The consultation form: its fields, selects and Next / Submit buttons run to the inline end.
    '[data-wizard-form]',
];

/**
 * Watches the page's other WhatsApp links (not the button, not inside a sheet, dialog or the footer: the overlays
 * cover the button anyway and the footer is a zone of its own) and the zones above, and reports how many are on
 * screen above the bar. `onChange(count)`; false when there is nothing to watch.
 */
function watchFabZones(fab, onChange) {
    if (!('IntersectionObserver' in window)) return false;
    const zones = FAB_ZONES.flatMap((selector) => Array.from(document.querySelectorAll(selector)));
    const links = Array.from(document.querySelectorAll('a[href]')).filter(
        (link) =>
            link !== fab &&
            !link.closest('[data-sheet], dialog, [data-wa-fab], .tabbar') &&
            !zones.some((zone) => zone.contains(link)) &&
            isWhatsAppLink(link.getAttribute('href')),
    );
    const targets = [...zones, ...links];
    if (targets.length === 0) return false;
    const showing = new Set();
    const bar = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--tabbar-h')) || 64;
    const observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) showing.add(entry.target);
                else showing.delete(entry.target);
            }
            onChange(showing.size);
        },
        // The strip behind the bar does not count: what hides there is not in view.
        { rootMargin: `0px 0px -${Math.round(bar)}px 0px` },
    );
    targets.forEach((target) => observer.observe(target));
    return true;
}

/** The cells' total width (their outer edges) is wider than the list's content box: the labels do not fit. */
export function overflowing(cellRects, listRect, padding = 0) {
    if (!Array.isArray(cellRects) || cellRects.length === 0 || !listRect) return false;
    const left = Math.min(...cellRects.map((r) => r.left));
    const right = Math.max(...cellRects.map((r) => r.right));
    return right - left > listRect.width - padding + 0.5;
}

/** .is-tight (Caption size, words may break) while the four labels do not fit the bar (a larger text size). */
function fitLabels(bar) {
    const list = bar.querySelector('.tabbar__list');
    if (!list) return;
    const measure = () => {
        if (!list.getClientRects().length) return; // not drawn (768 px and wider)
        bar.classList.remove('is-tight');
        const style = getComputedStyle(list);
        const padding = parseFloat(style.paddingInlineStart) + parseFloat(style.paddingInlineEnd);
        const cells = Array.from(list.children, (cell) => cell.getBoundingClientRect());
        bar.classList.toggle('is-tight', overflowing(cells, list.getBoundingClientRect(), padding));
    };
    measure();
    document.fonts?.ready.then(measure);
    if ('ResizeObserver' in window) new ResizeObserver(measure).observe(list);
}

export default function tabbar() {
    const bar = document.querySelector('[data-tabbar]');
    if (!bar) return;

    const mobile = window.matchMedia('(width < 768px)');
    fitLabels(bar);
    let state = { hidden: false, lastY: window.scrollY };
    let frame = 0;
    let overlays = 0;

    const setHidden = (value) => {
        if (value === state.hidden) return;
        state = { ...state, hidden: value };
        bar.classList.toggle('is-hidden', value);
    };

    const fab = document.querySelector('[data-wa-fab]');
    const fabState = { overlays: 0, editing: false, others: 0 };
    const renderFab = () => {
        if (!fab) return;
        const away = fabAway(fabState);
        fab.classList.toggle('is-away', away);
        // Out of the tab order and the accessibility tree while it has stepped aside (visibility: hidden in CSS).
        if (away) fab.setAttribute('aria-hidden', 'true');
        else fab.removeAttribute('aria-hidden');
    };
    // The button starts away (markup); it shows once the zones have been looked at (the observer's first report),
    // or at once when the page has none.
    if (fab && !watchFabZones(fab, (count) => {
        fabState.others = count;
        renderFab();
    })) {
        window.requestAnimationFrame(renderFab);
    }

    const pinned = () =>
        overlays > 0 ||
        document.documentElement.classList.contains('is-scroll-locked') ||
        bar.contains(document.activeElement) ||
        typing(document.activeElement) ||
        reducedMotion();

    const update = () => {
        frame = 0;
        const y = Math.max(0, window.scrollY);
        if (!mobile.matches) {
            setHidden(false);
            state = { ...state, lastY: y };
            return;
        }
        // On-screen keyboard up while typing on the page: out of the way (it is covered or would float).
        if (overlays === 0 && typing(document.activeElement) && keyboardOpen(window)) {
            setHidden(true);
            state = { ...state, lastY: y };
            return;
        }
        const end = document.documentElement.scrollHeight - window.innerHeight;
        const next = scrollStep(state, { y, end, pinned: pinned() });
        setHidden(next.hidden);
        state = { ...state, lastY: next.lastY };
    };
    const schedule = () => {
        if (!frame) frame = window.requestAnimationFrame(update);
    };

    window.addEventListener('scroll', schedule, { passive: true });
    window.visualViewport?.addEventListener('resize', schedule);

    const overlayChange = (event) => {
        overlays = Math.max(0, overlays + (event.type.endsWith('-open') ? 1 : -1));
        setHidden(false);
        state = { ...state, lastY: window.scrollY };
        fabState.overlays = overlays;
        renderFab();
    };
    for (const type of ['d2-sheet-open', 'd2-sheet-close', 'd2-modal-open', 'd2-modal-close']) {
        window.addEventListener(type, overlayChange);
    }

    const editingCheck = () => {
        fabState.editing = typing(document.activeElement);
        renderFab();
    };
    document.addEventListener('focusin', () => {
        if (bar.contains(document.activeElement)) setHidden(false);
        schedule();
        editingCheck();
    });
    document.addEventListener('focusout', () => {
        schedule();
        window.requestAnimationFrame(editingCheck);
    });
    mobile.addEventListener('change', () => {
        setHidden(false);
        state = { ...state, lastY: window.scrollY };
    });
    window.addEventListener('pageshow', (event) => {
        if (!event.persisted) return;
        overlays = 0;
        fabState.overlays = 0;
        setHidden(false);
        state = { ...state, lastY: window.scrollY };
        editingCheck();
    });
}
