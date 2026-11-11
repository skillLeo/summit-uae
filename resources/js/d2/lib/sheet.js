/**
 * Design 2 bottom sheet (upgrade spec §8, §12 A4 and E; docs/d2/engineering.md §4).
 * Markup: <x-d2.ui.sheet id="menu" title="…"> (resources/views/components/d2/ui/sheet.blade.php).
 *
 * API
 *   openSheet(id, opener?)   opens [data-sheet="id"]; opener (default: the focused element) gets focus back
 *   closeSheet(options?)     closes the open sheet ({ restore: true } returns focus to the opener)
 *   activeSheet()            the open sheet's id, or null
 *   initSheets()             wires the document once (resources/js/d2/modules/sheets.js calls it)
 *   lockScroll() / unlockScroll()   reference-counted page scroll lock without layout shift (modal.js uses it)
 *   pushHistory(onPop)       one history entry per open overlay; returns pop() (modal.js uses it)
 *
 * Markup hooks (delegated, so content added later works too)
 *   [data-sheet-open="id"]   opens the sheet; gets aria-haspopup="dialog", aria-controls="sheet-id" and an
 *                            aria-expanded that follows the sheet. On a link, a modified click (new tab)
 *                            still follows the link, so href can be the no-JS destination.
 *   [data-sheet-close]       closes it (close button, backdrop)
 *   [data-sheet-drag]        drag area (handle + header): follows the finger or the mouse (pointer events),
 *                            closes past 120 px or on a flick faster than 800 px/s (over at least 16 px),
 *                            otherwise springs back (--spring-sheet). Bottom layout only.
 *   .sheet__body             pull down (touch) while the content is scrolled to its top: the sheet follows
 *                            the finger with the same rules (spec §8 "pull-down closes sheets"); a scroll,
 *                            a sideways swipe or a touch on a field / [data-sheet-no-drag] stays a scroll.
 *   [autofocus] / [data-autofocus] inside the sheet receives focus instead of the title
 *   a[data-sheet-keep]       a link that leaves the sheet open
 * While a sheet is dragged its backdrop fades with the distance (--sheet-fade on the root).
 *
 * Behaviour (Design 1 review A11Y-02, A11Y-03, A11Y-16, A11Y-18, NAV-01…03 are the bugs this avoids):
 * focus moves to the title (or [autofocus]), Tab is trapped, everything outside is inert, Esc anywhere
 * closes, the browser / Android back gesture closes (pushState + popstate), focus returns to the opener,
 * page scroll is locked. Links inside the sheet replace its history entry, so Back after following a
 * link never lands on a dead entry. Events (bubbling, on the sheet root): d2-sheet-open, d2-sheet-close
 * with detail { id }.
 *
 * Layout: < 768 px a bottom sheet; ≥ 768 px a side panel on the inline-end edge (sheet.css).
 * Pure helpers (unit tested in sheet.test.js): releaseVelocity(), shouldDismiss(), dragOffset().
 */
import { focusFirst, restoreFocus, trapFocus } from './focus.js';
import { inertOutside } from './inert.js';
import { reducedMotion } from './prefs.js';

const BOTTOM_LAYOUT = '(width < 768px)';
export const DISMISS_DISTANCE = 120; // px
export const DISMISS_VELOCITY = 0.8; // px per ms (800 px/s)
export const FLICK_MIN = 16; // px: a flick must travel at least this far (a jittery tap never dismisses)
const VELOCITY_WINDOW = 100; // ms of pointer history used for the release velocity
const HIDE_FALLBACK = 800; // ms: hide even if transitionend never arrives
const NO_PULL = 'input, select, textarea, [contenteditable]:not([contenteditable="false"]), [data-sheet-no-drag], .carousel__track';

/** Release velocity in px/ms from [timeStamp, y] samples (first to last); 0 without two distinct times. */
export function releaseVelocity(samples) {
    if (!Array.isArray(samples) || samples.length < 2) return 0;
    const [t0, y0] = samples[0];
    const [t1, y1] = samples[samples.length - 1];
    return t1 > t0 ? (y1 - y0) / (t1 - t0) : 0;
}

/** Past 120 px, or a downward flick faster than 800 px/s that travelled at least 16 px. */
export function shouldDismiss(offset, velocity) {
    return offset > DISMISS_DISTANCE || (offset >= FLICK_MIN && velocity > DISMISS_VELOCITY);
}

/** The sheet's offset for a finger moved dy px: down follows 1:1, up resists (the sheet is fully open). */
export function dragOffset(dy) {
    return dy >= 0 ? dy : -Math.min(24, Math.sqrt(-dy) * 2);
}

let active = null;
let initialised = false;

const esc = (value) => (window.CSS && CSS.escape ? CSS.escape(value) : String(value).replace(/["\\]/g, '\\$&'));
const findSheet = (id) => (id ? document.querySelector(`[data-sheet="${esc(id)}"]`) : null);
const openersOf = (id) => document.querySelectorAll(`[data-sheet-open="${esc(id)}"]`);

export const activeSheet = () => (active ? active.id : null);

// --------------------------------------------------------------------------------- scroll lock

let locks = 0;

/** Locks page scrolling. A classic scrollbar keeps its gutter (scrollbar-gutter: stable): no layout shift. */
export function lockScroll() {
    locks += 1;
    if (locks > 1) return;
    const html = document.documentElement;
    html.classList.toggle('has-scroll-gutter', window.innerWidth - html.clientWidth > 0);
    html.classList.add('is-scroll-locked');
}

export function unlockScroll() {
    if (locks === 0) return;
    locks -= 1;
    if (locks === 0) document.documentElement.classList.remove('is-scroll-locked', 'has-scroll-gutter');
}

// --------------------------------------------------------------------------------- history

const entries = [];
let skipPops = 0;
let historyBound = false;

function onPopstate(event) {
    if (skipPops > 0) {
        // The pop we caused ourselves when an overlay closed by other means.
        skipPops -= 1;
        return;
    }
    const current = event.state && typeof event.state === 'object' ? event.state.d2Overlay : null;
    // Back left one or more overlay entries: close the overlays above the entry that is now current.
    while (entries.length) {
        const top = entries[entries.length - 1];
        if (top.token === current) break;
        entries.pop();
        top.onPop();
    }
}

function bindHistory() {
    if (historyBound) return;
    historyBound = true;
    window.addEventListener('popstate', onPopstate);
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            skipPops = 0;
            entries.length = 0;
        }
    });
}

/**
 * Adds a same-URL history entry for an overlay, so Back closes it. onPop runs when Back leaves the entry.
 * Returns pop({ navigate = true }): call it when the overlay closes by other means; it removes the entry
 * with history.back() (navigate: false only forgets it, e.g. before location.replace()).
 */
export function pushHistory(onPop) {
    bindHistory();
    const token = `d2-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;
    try {
        const base = history.state && typeof history.state === 'object' ? history.state : {};
        history.pushState({ ...base, d2Overlay: token }, '');
    } catch {
        return () => {};
    }
    const entry = { token, onPop };
    entries.push(entry);
    const pop = ({ navigate = true } = {}) => {
        const index = entries.indexOf(entry);
        if (index === -1) return;
        entries.splice(index, 1);
        if (navigate && history.state && history.state.d2Overlay === token) {
            skipPops += 1;
            history.back();
        }
    };
    pop.owns = () => entries.includes(entry) && !!history.state && history.state.d2Overlay === token;
    return pop;
}

// --------------------------------------------------------------------------------- openers

function syncOpeners(id, expanded) {
    const sheet = findSheet(id);
    const panelId = sheet ? sheet.querySelector('[role="dialog"]')?.id : null;
    for (const opener of openersOf(id)) {
        opener.setAttribute('aria-haspopup', 'dialog');
        if (panelId) opener.setAttribute('aria-controls', panelId);
        opener.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }
}

// --------------------------------------------------------------------------------- open / close

function cancelHide(root) {
    if (root._d2Hide) {
        root._d2Hide();
        root._d2Hide = null;
    }
}

function scheduleHide(root, panel, done) {
    cancelHide(root);
    let timer = 0;
    const finish = () => {
        cancelHide(root);
        done();
    };
    const onEnd = (event) => {
        if (event.target === panel && event.propertyName === 'transform') finish();
    };
    panel.addEventListener('transitionend', onEnd);
    timer = window.setTimeout(finish, HIDE_FALLBACK);
    root._d2Hide = () => {
        panel.removeEventListener('transitionend', onEnd);
        window.clearTimeout(timer);
    };
}

export function openSheet(id, opener = null) {
    initSheets();
    const root = findSheet(id);
    const panel = root ? root.querySelector('.sheet__panel') : null;
    if (!root || !panel) return false;
    if (active && active.root === root) return true;

    let returnTo = opener || document.activeElement;
    let popHistory = null;
    if (active) {
        // Switching sheets: focus will still go back to the first opener, and the history entry is reused.
        returnTo = active.opener;
        popHistory = active.popHistory;
        active.popHistory = null;
        closeSheet({ restore: false, instant: true });
    }

    cancelHide(root);
    endDrag();
    root.hidden = false;
    panel.classList.remove('is-dragging', 'is-scrolled');
    root.classList.remove('is-dragging');
    panel.style.removeProperty('--sheet-drag');
    root.style.removeProperty('--sheet-fade');
    const body = panel.querySelector('.sheet__body');
    if (body) body.scrollTop = 0; // every opening starts at the top, under the title that gets focus
    void panel.offsetHeight; // commit the closed position so the spring runs from it
    root.classList.add('is-open');

    lockScroll();
    const state = {
        id,
        root,
        panel,
        opener: returnTo,
        restoreInert: inertOutside(root),
        release: trapFocus(panel),
        popHistory,
    };
    active = state;
    state.popHistory ||= pushHistory(() => {
        if (active) closeSheet({ fromHistory: true });
    });
    syncOpeners(id, true);
    bindPanel(root);

    const title = panel.querySelector('.sheet__title');
    if (panel.querySelector('[autofocus], [data-autofocus]') || !title) focusFirst(panel);
    else title.focus({ preventScroll: true });

    root.dispatchEvent(new CustomEvent('d2-sheet-open', { bubbles: true, detail: { id } }));
    return true;
}

/**
 * options: restore (focus the opener, default true), instant (no exit transition), navigate (remove the
 * history entry with history.back(), default true), fromHistory (internal: Back already removed it).
 */
export function closeSheet({ restore = true, instant = false, navigate = true, fromHistory = false } = {}) {
    const state = active;
    if (!state) return false;
    active = null;
    const { id, root, panel } = state;

    endDrag();
    state.release();
    state.restoreInert();
    unlockScroll();
    if (state.popHistory) state.popHistory({ navigate: navigate && !fromHistory });
    root.classList.remove('is-open', 'is-dragging');
    panel.classList.remove('is-dragging');
    root.style.removeProperty('--sheet-fade');
    syncOpeners(id, false);

    if (restore && !restoreFocus(state.opener)) restoreFocus(document.getElementById('main-content'));

    const hide = () => {
        if (root.classList.contains('is-open')) return;
        root.hidden = true;
        panel.style.removeProperty('--sheet-drag');
    };
    if (instant || reducedMotion()) {
        cancelHide(root);
        hide();
    } else {
        scheduleHide(root, panel, hide);
    }

    root.dispatchEvent(new CustomEvent('d2-sheet-close', { bubbles: true, detail: { id } }));
    return true;
}

// --------------------------------------------------------------------------------- drag to dismiss

const panelBound = new WeakSet();
// The one gesture in progress: { source: 'pointer' | 'touch', root, panel, height, startY, offset, moved,
// samples, area?, pointerId? }.
let drag = null;
let pull = null; // a touch on the sheet body that may turn into a pull-down: { body, panel, root, x, y }

/** Once per sheet: drag areas, the body pull-down, and the head hairline while the body is scrolled. */
function bindPanel(root) {
    if (panelBound.has(root)) return;
    panelBound.add(root);
    const panel = root.querySelector('.sheet__panel');
    for (const area of root.querySelectorAll('[data-sheet-drag]')) {
        area.addEventListener('pointerdown', (event) => startDrag(event, area, panel, root));
    }
    const body = panel.querySelector('.sheet__body');
    if (body) {
        body.addEventListener('scroll', () => panel.classList.toggle('is-scrolled', body.scrollTop > 0), { passive: true });
        if (root.querySelector('[data-sheet-drag]')) bindPull(root, panel, body);
    }
}

function begin(source, root, panel, y, timeStamp, extra = {}) {
    drag = {
        source,
        root,
        panel,
        height: Math.max(1, panel.offsetHeight),
        startY: y,
        offset: 0,
        moved: false,
        samples: [[timeStamp, y]],
        ...extra,
    };
}

function trimSamples(now) {
    const cutoff = now - VELOCITY_WINDOW;
    while (drag.samples.length > 2 && drag.samples[0][0] < cutoff) drag.samples.shift();
}

/** Moves the sheet with the finger and fades the backdrop with the distance (transform and opacity only). */
function follow(y) {
    if (!drag.moved) {
        drag.moved = true;
        drag.panel.classList.add('is-dragging');
        drag.root.classList.add('is-dragging');
    }
    drag.offset = dragOffset(y - drag.startY);
    drag.panel.style.setProperty('--sheet-drag', `${drag.offset.toFixed(1)}px`);
    const fade = 1 - Math.min(1, Math.max(0, drag.offset) / drag.height);
    drag.root.style.setProperty('--sheet-fade', fade.toFixed(3));
}

/** Ends the gesture: closes past the distance or on a flick, otherwise springs back. */
function release(timeStamp, y, cancelled = false) {
    if (!drag) return;
    if (!cancelled && typeof y === 'number') {
        drag.samples.push([timeStamp, y]);
        trimSamples(timeStamp);
    }
    const velocity = releaseVelocity(drag.samples);
    const { moved, offset, panel, root } = drag;
    endDrag();
    if (!moved) return;
    panel.classList.remove('is-dragging');
    root.classList.remove('is-dragging');
    root.style.removeProperty('--sheet-fade'); // the backdrop eases back to full, or out with the sheet
    if (!cancelled && shouldDismiss(offset, velocity)) {
        closeSheet();
    } else {
        panel.style.setProperty('--sheet-drag', '0px'); // springs back
    }
}

// ---- handle + head: pointer events (touch, pen and mouse)

function startDrag(event, area, panel, root) {
    if (!active || active.panel !== panel || drag) return;
    if (!window.matchMedia(BOTTOM_LAYOUT).matches) return;
    if (event.pointerType === 'mouse' && event.button !== 0) return;
    if (event.target.closest('button, a, input, select, textarea, label, [data-sheet-no-drag]')) return;
    begin('pointer', root, panel, event.clientY, event.timeStamp, { area, pointerId: event.pointerId });
    try {
        area.setPointerCapture(event.pointerId);
    } catch {
        /* capture is an optimisation */
    }
    area.addEventListener('pointermove', onDragMove);
    area.addEventListener('pointerup', onDragUp);
    area.addEventListener('pointercancel', onDragCancel);
}

function onDragMove(event) {
    if (!drag || drag.source !== 'pointer' || event.pointerId !== drag.pointerId) return;
    if (!drag.moved && Math.abs(event.clientY - drag.startY) < 4) return;
    follow(event.clientY);
    // Coalesced samples keep the velocity true when the main thread is busy and moves arrive in bursts.
    const coalesced = typeof event.getCoalescedEvents === 'function' ? event.getCoalescedEvents() : [];
    for (const sample of coalesced.length ? coalesced : [event]) drag.samples.push([sample.timeStamp, sample.clientY]);
    trimSamples(event.timeStamp);
}

function onDragUp(event) {
    if (!drag || drag.source !== 'pointer' || event.pointerId !== drag.pointerId) return;
    release(event.timeStamp, event.clientY);
}

function onDragCancel(event) {
    if (!drag || drag.source !== 'pointer' || event.pointerId !== drag.pointerId) return;
    release(event.timeStamp, null, true);
}

// ---- body: pull down while the content is at its top (touch events: the move must be cancelable)

function bindPull(root, panel, body) {
    body.addEventListener(
        'touchstart',
        (event) => {
            pull = null;
            if (!active || active.panel !== panel || drag || event.touches.length !== 1) return;
            if (!window.matchMedia(BOTTOM_LAYOUT).matches || body.scrollTop > 0) return;
            if (event.target instanceof Element && event.target.closest(NO_PULL)) return;
            const touch = event.touches[0];
            pull = { body, panel, root, x: touch.clientX, y: touch.clientY };
        },
        { passive: true },
    );
    body.addEventListener(
        'touchmove',
        (event) => {
            if (!pull || pull.body !== body) return;
            const touch = event.touches[0];
            if (!touch) return;
            if (!drag) {
                const dx = touch.clientX - pull.x;
                const dy = touch.clientY - pull.y;
                // The first move decides: only a mostly vertical move downwards, with the content still at its
                // top, pulls the sheet. Anything else is the content's own scroll for the rest of the touch.
                if (dy <= 0 || Math.abs(dx) > Math.abs(dy) || body.scrollTop > 0 || !event.cancelable) {
                    pull = null;
                    return;
                }
                begin('touch', root, panel, pull.y, event.timeStamp);
            }
            if (drag.source !== 'touch') return;
            event.preventDefault(); // the sheet moves instead of the content (no overscroll bounce)
            follow(touch.clientY);
            drag.samples.push([event.timeStamp, touch.clientY]);
            trimSamples(event.timeStamp);
        },
        { passive: false },
    );
    const end = (event) => {
        if (!pull || pull.body !== body) return;
        pull = null;
        if (!drag || drag.source !== 'touch') return;
        const touch = event.changedTouches && event.changedTouches[0];
        release(event.timeStamp, touch ? touch.clientY : null, event.type === 'touchcancel');
    };
    body.addEventListener('touchend', end);
    body.addEventListener('touchcancel', end);
}

function endDrag() {
    pull = null;
    if (!drag) return;
    const { area, pointerId, source } = drag;
    if (source === 'pointer' && area) {
        area.removeEventListener('pointermove', onDragMove);
        area.removeEventListener('pointerup', onDragUp);
        area.removeEventListener('pointercancel', onDragCancel);
        try {
            if (area.hasPointerCapture(pointerId)) area.releasePointerCapture(pointerId);
        } catch {
            /* already released */
        }
    }
    drag = null;
}

// --------------------------------------------------------------------------------- document wiring

const modified = (event) => event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;

/**
 * A link followed from inside a sheet closes it and replaces the sheet's history entry (so Back returns
 * to the page, not to a dead "sheet open" entry). tel:, mailto:, new-tab and external links leave the
 * sheet open.
 */
function followFromSheet(event, link) {
    if (modified(event)) return;
    if ((link.target && link.target !== '_self') || link.hasAttribute('download') || link.hasAttribute('data-sheet-keep')) return;
    let url;
    try {
        url = new URL(link.href, window.location.href);
    } catch {
        return;
    }
    if (url.origin !== window.location.origin) return;
    const owns = active.popHistory && active.popHistory.owns && active.popHistory.owns();
    if (!owns) {
        closeSheet({ restore: false });
        return;
    }
    event.preventDefault();
    closeSheet({ restore: false, navigate: false });
    // Next task: other click handlers (lang-switch.js adds the #hash) have run by then.
    window.setTimeout(() => {
        const next = new URL(link.href, window.location.href);
        const samePage = next.pathname === window.location.pathname && next.search === window.location.search;
        window.location.replace(next.href);
        if (samePage && next.hash) focusTarget(next.hash);
    }, 0);
}

function focusTarget(hash) {
    let target = null;
    try {
        target = document.getElementById(decodeURIComponent(hash.slice(1)));
    } catch {
        return;
    }
    if (!target) return;
    if (target.tabIndex < 0 && !target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
    target.focus({ preventScroll: true });
}

function onClick(event) {
    if (event.defaultPrevented || !(event.target instanceof Element)) return;
    const opener = event.target.closest('[data-sheet-open]');
    if (opener) {
        if (opener instanceof HTMLAnchorElement && modified(event)) return;
        const id = opener.getAttribute('data-sheet-open');
        if (!findSheet(id)) return; // no such sheet on this page: a link simply navigates
        event.preventDefault();
        openSheet(id, active ? null : opener);
        return;
    }
    if (!active || !active.root.contains(event.target)) return;
    if (event.target.closest('[data-sheet-close]')) {
        event.preventDefault();
        closeSheet();
        return;
    }
    const link = event.target.closest('a[href]');
    if (link) followFromSheet(event, link);
}

function modalOpen() {
    return Array.from(document.querySelectorAll('dialog[open]')).some((dialog) => {
        try {
            return dialog.matches(':modal');
        } catch {
            return true;
        }
    });
}

function onKeydown(event) {
    if (event.key !== 'Escape' || !active || event.defaultPrevented || event.isComposing) return;
    if (modalOpen()) return; // the dialog above handles its own Esc
    const target = event.target;
    // The first Esc in a filled search field clears it (browser default); the next one closes.
    if (target instanceof HTMLInputElement && target.type === 'search' && target.value) return;
    event.preventDefault();
    closeSheet();
}

function onPageshow(event) {
    // Restored from the back/forward cache with a sheet still drawn open: reset it silently.
    if (event.persisted && active) closeSheet({ restore: false, instant: true, navigate: false });
}

export function initSheets() {
    if (initialised) return;
    initialised = true;
    bindHistory();
    document.addEventListener('click', onClick);
    document.addEventListener('keydown', onKeydown);
    window.addEventListener('pageshow', onPageshow);
    const ids = new Set(Array.from(document.querySelectorAll('[data-sheet-open]'), (el) => el.getAttribute('data-sheet-open')));
    for (const id of ids) if (findSheet(id)) syncOpeners(id, false);
}
