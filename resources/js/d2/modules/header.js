/**
 * Design 2.1 header behaviour (<x-d2.shell.header>, components/header.css; brief §3.7, §3.8, D13, D14).
 *
 * 1. Compact state. The header is sticky at --header-h minus --header-h-top (-24 px on desktop), so scrolling
 *    itself shrinks the visible masthead to the compact bar. Once it has (scrollY >= the header's own lift, at
 *    least 8 px), [data-d2-header] gets .is-scrolled: the shield replaces the lockup. While the navigation row is
 *    shown it also gets one of two layouts (compactLayout):
 *      .is-docked  the seven labels fit in the bar between the shield and the tools: the row moves into the bar
 *                  (English and Arabic in a full container); .is-docking marks its one entrance (the labels fade
 *                  in), removed when that animation ends.
 *      .is-tucked  they do not (Russian, narrower windows): the row slides under the masthead and the menu button
 *                  beside the shield opens the menu sheet.
 *    A small hysteresis keeps the state from flickering at the edge. 'd2:header-compact' ({ detail: { compact,
 *    layout } }) is dispatched on window when the state changes (the mega menu closes unless focus is inside).
 *    .is-live is added after the first frame, so a page opened scrolled does not animate into place.
 * 2. Fit, measured with the real fonts and text size on every width change and once the fonts are in:
 *    - the row: header.css shows the seven labels from 1024 px (Russian from 69rem); .is-nav-collapsed where they
 *      would still overflow it (the menu button then opens the menu sheet). The labels never wrap, shrink or move
 *      into "More".
 *    - the compact bar: the shield's target, the labels, the bar's gap and the tools (which never change between
 *      the two states) against the bar's width: docked or tucked.
 *    - the masthead: the lockup, the bar's gap and the tools; where they would not fit (another font, a larger
 *      text size) .is-mast-tight puts the shield in the lockup's place, so the tools never leave the container.
 * 3. Search: a click on any [data-d2-search] trigger, "/" (when not typing in a field) and Ctrl/Cmd+K dispatch a
 *    cancelable window CustomEvent 'd2:palette-open' ({ detail: { source } }). Ctrl/Cmd+K inside a field
 *    dispatches too, but never falls back to navigating (typed text stays). PROTOCOL for the command palette:
 *    listen on window, call event.preventDefault() to claim the event, then open. While nothing claims it, the
 *    visitor goes to Legal Resources (the trigger's href, which is also the no-JS behaviour). When the palette is
 *    ready it may dispatch 'd2:palette-ready' (or set <html data-palette>): the triggers then announce
 *    aria-haspopup="dialog". The visible trigger has no key cap (D14); its tooltip names the "/" key.
 */
import { isEditable } from '../lib/dom.js';

/** Phones (no masthead lift): the shield replaces the lockup after this much scroll. */
export const MIN_SCROLL = 8;

/** Back to the top state only this far above the threshold (no flicker at the edge). */
export const HYSTERESIS = 8;

/** Scroll distance at which the header is compact: the masthead's lift (its negative sticky top), at least 8 px. */
export function compactThreshold(stickyTop) {
    const lift = Number.isFinite(stickyTop) ? Math.max(0, -stickyTop) : 0;
    return Math.max(MIN_SCROLL, lift);
}

/** The next compact state for a scroll position, with hysteresis on the way back up. */
export function nextCompact(compact, scrollY, threshold) {
    if (compact) return scrollY > Math.max(0, threshold - HYSTERESIS);
    return scrollY >= threshold;
}

/** Does a row need more room than it has (1 px of rounding slack)? */
export function overflows(needed, available) {
    return needed > available + 1;
}

/**
 * The compact bar's layout for the navigation row: 'docked' when the shield's target, the labels (their boxes,
 * paddings included), the bar's gap and the tools fit the bar's width; 'tucked' when they do not; null when the
 * row is not shown at all (below 1024 px, Russian below 69rem, a collapsed row: the menu button is already there).
 */
export function compactLayout({ rowShown, collapsed, brand, labels, gap, tools, bar }) {
    if (!rowShown || collapsed) return null;
    return overflows(brand + labels + gap + tools, bar) ? 'tucked' : 'docked';
}

/** The palette shortcut a keydown is: 'shortcut-k' (Ctrl/Cmd+K, by physical key too), 'shortcut-slash' or null. */
export function shortcut(e, typing) {
    if (!e || e.defaultPrevented || e.isComposing || e.repeat) return null;
    const commandK = (e.ctrlKey || e.metaKey) && !e.altKey && !e.shiftKey && (e.key?.toLowerCase() === 'k' || e.code === 'KeyK');
    if (commandK) return 'shortcut-k';
    if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey && !typing) return 'shortcut-slash';
    return null;
}

const rowShown = (row) => !!row && getComputedStyle(row).display !== 'none';
const px = (value) => parseFloat(value) || 0;

/** The content width of an element (its box minus its inline padding). */
function contentWidth(el) {
    const style = getComputedStyle(el);
    return el.getBoundingClientRect().width - px(style.paddingInlineStart) - px(style.paddingInlineEnd);
}

/**
 * Measures the row, the compact bar and the masthead (section 2 above); sets .is-nav-collapsed and
 * .is-mast-tight and returns the compact layout. Everything it reads is laid out as CSS lays it out at this width;
 * the classes it clears are set again within the same frame (from display: none nothing transitions, and nothing
 * paints in between).
 */
function measure(header) {
    const bar = header.querySelector('.d2-header__bar');
    const brand = header.querySelector('.d2-brand');
    const lockup = header.querySelector('.d2-brand__lockup img');
    const tools = header.querySelector('.d2-header__tools');
    const row = header.querySelector('.d2-header__navrow');
    const list = row?.querySelector('.d2-nav__list');
    const nav = list?.parentElement;
    if (!bar || !tools) return null;

    header.classList.remove('is-nav-collapsed', 'is-mast-tight');
    const barWidth = contentWidth(bar);
    const gap = px(getComputedStyle(bar).columnGap);
    const toolsWidth = tools.getBoundingClientRect().width;

    // The masthead at the top of the page: the lockup (its box, wherever it sits) beside the tools.
    const lockupWidth = lockup ? lockup.getBoundingClientRect().width : 0;
    header.classList.toggle('is-mast-tight', overflows(lockupWidth + gap + toolsWidth, barWidth));

    if (!list || !nav || !rowShown(row)) return null;
    const items = Array.from(list.children).filter((li) => li.getClientRects().length > 0);
    const labels = items.reduce((sum, li) => sum + li.getBoundingClientRect().width, 0);
    // In its own row the first label's padding hangs outside the container (the text starts on its edge).
    const pad = px(getComputedStyle(list.querySelector('.d2-nav__link') ?? list).paddingInlineStart);
    const collapsed = overflows(labels - pad, contentWidth(nav));
    header.classList.toggle('is-nav-collapsed', collapsed);

    return compactLayout({
        rowShown: true,
        collapsed,
        brand: px(getComputedStyle(brand ?? bar).minInlineSize),
        labels,
        gap,
        tools: toolsWidth,
        bar: barWidth,
    });
}

/** The header's state machine: scroll position x fit. Returns refresh(), which measures again and re-applies. */
function controller(header) {
    let threshold = MIN_SCROLL;
    let compact = false;
    let layout = null; // the compact layout the last fit allows: 'docked' | 'tucked' | null
    let ticking = false;
    let undock = 0;

    const set = (name, on) => header.classList.toggle(name, on);

    const endDocking = () => {
        window.clearTimeout(undock);
        header.classList.remove('is-docking');
    };

    const apply = (next) => {
        const docked = next && layout === 'docked';
        const wasDocked = header.classList.contains('is-docked');
        set('is-docked', docked);
        set('is-tucked', next && layout === 'tucked');
        if (docked && !wasDocked && header.classList.contains('is-live')) {
            // The labels fade into the bar once (header.css); the class goes when that animation ends.
            header.classList.add('is-docking');
            window.clearTimeout(undock);
            undock = window.setTimeout(endDocking, 1000);
        } else if (!docked) {
            endDocking();
        }
        if (next === compact) return;
        compact = next;
        set('is-scrolled', next);
        window.dispatchEvent(new CustomEvent('d2:header-compact', { detail: { compact: next, layout: next ? layout : null } }));
    };

    const update = () => {
        ticking = false;
        apply(nextCompact(compact, window.scrollY, threshold));
    };

    const refresh = () => {
        threshold = compactThreshold(px(getComputedStyle(header).top));
        layout = measure(header);
        update();
    };

    header.addEventListener('animationend', (e) => {
        if (e.animationName === 'd2-nav-dock') endDocking();
    });

    refresh();
    // Transitions only once the first state is painted.
    window.requestAnimationFrame(() => window.requestAnimationFrame(() => header.classList.add('is-live')));

    window.addEventListener(
        'scroll',
        () => {
            if (ticking) return;
            ticking = true;
            window.requestAnimationFrame(update);
        },
        { passive: true },
    );

    return refresh;
}

/** Measures again on every width change (and once the fonts are in): breakpoints, fonts and text size move the fit. */
function watch(header, refresh) {
    let frame = 0;
    let width = -1;
    const schedule = () => {
        window.cancelAnimationFrame(frame);
        frame = window.requestAnimationFrame(refresh);
    };
    if ('ResizeObserver' in window) {
        // Width changes only: docking or tucking the row changes the header's height, which needs no new fit.
        new ResizeObserver((entries) => {
            const next = Math.round(entries[0]?.contentRect.width ?? 0);
            if (next === width) return;
            width = next;
            schedule();
        }).observe(header);
    }
    // Breakpoints also move the masthead's lift (the compact threshold), even when the width observer is missing.
    window.addEventListener('resize', schedule, { passive: true });
    document.fonts?.ready.then(schedule);
    document.fonts?.addEventListener?.('loadingdone', schedule);
}

function fallbackUrl(trigger) {
    return trigger?.href || document.querySelector('[data-d2-search]')?.href || document.querySelector('[data-search-url]')?.dataset.searchUrl || null;
}

function openPalette(source, trigger, navigate = true) {
    const event = new CustomEvent('d2:palette-open', { cancelable: true, detail: { source } });
    window.dispatchEvent(event);
    if (event.defaultPrevented || !navigate) return;
    const url = fallbackUrl(trigger);
    if (url) window.location.assign(url);
}

function paletteReady() {
    document.querySelectorAll('[data-d2-search]').forEach((trigger) => {
        trigger.setAttribute('role', 'button');
        trigger.setAttribute('aria-haspopup', 'dialog');
    });
}

export default function header() {
    const bar = document.querySelector('[data-d2-header]');
    if (bar) watch(bar, controller(bar));

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest?.('[data-d2-search]');
        if (!trigger || e.defaultPrevented) return;
        // A modified click (new tab or window) keeps the link: Legal Resources.
        if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        e.preventDefault();
        openPalette('click', trigger);
    });

    document.addEventListener('keydown', (e) => {
        const typing = isEditable(e.target) || isEditable(document.activeElement);
        const source = shortcut(e, typing);
        if (!source) return;
        e.preventDefault();
        // Typing in a field: the palette may open over it, but the fallback never navigates away (a form being
        // filled in would be lost).
        openPalette(source, null, !typing);
    });

    if (document.documentElement.hasAttribute('data-palette')) paletteReady();
    window.addEventListener('d2:palette-ready', paletteReady, { once: true });
}
