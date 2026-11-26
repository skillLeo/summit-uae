/**
 * Header behaviour (<x-d2.shell.header>, components/header.css; brief §3.7, §3.8, D13; the client's final changes,
 * item 1: logo, navigation and language only, on black).
 *
 * 1. Compact state. The header is sticky at --header-h minus --header-h-top (-16 px on desktop), so scrolling
 *    itself shrinks the visible masthead to the compact bar. Once it has (scrollY >= the header's own lift, at
 *    least 8 px), [data-d2-header] gets .is-scrolled (from 1024 px the lockup eases from 56 to 48 px). A small
 *    hysteresis keeps the state from flickering at the edge. 'd2:header-compact' ({ detail: { compact, layout } })
 *    is dispatched on window when the state changes (the mega menu closes unless focus is inside). .is-live is
 *    added after the first frame, so a page opened scrolled does not animate into place.
 * 2. Fit, measured with the real fonts and text size on every width change and once the fonts are in:
 *    - the row: header.css shows the seven labels from 1024 px (Russian from 69rem); .is-nav-collapsed where they
 *      would still overflow their own row (the menu button then opens the menu sheet). The labels never wrap,
 *      shrink or move into "More".
 *    - the bar: the lockup (56 px, or 48 px once compact), the labels and the tools against the bar's width:
 *      .is-inline where they fit (the row rides in the bar, at its end), else the row's own line at the top of
 *      the page and .is-tucked once scrolled (the menu button at the end of the bar). .is-lifted marks a row that
 *      left the flow only because of scrolling (its height stays as margin); .is-docking marks its one entrance
 *      into the bar (the labels fade in), removed when that animation ends.
 *    - the masthead: the lockup beside the tools; where they would not fit (a very narrow window, a larger text
 *      size) .is-mast-tight moves the language menu to the menu sheet. The lockup always shows in full.
 * 3. Search: a click on any [data-d2-search] trigger (the header has none; other pages may), "/" (when not typing
 *    in a field) and Ctrl/Cmd+K dispatch a cancelable window CustomEvent 'd2:palette-open' ({ detail: { source } }).
 *    Ctrl/Cmd+K inside a field dispatches too, but never falls back to navigating (typed text stays). PROTOCOL for
 *    the command palette: listen on window, call event.preventDefault() to claim the event, then open. While nothing
 *    claims it, the visitor goes to Legal Resources (the trigger's href, or the header's data-search-url). When the
 *    palette is ready it may dispatch 'd2:palette-ready' (or set <html data-palette>): the triggers then announce
 *    aria-haspopup="dialog".
 */
import { isEditable } from '../lib/dom.js';

/** Phones (no masthead lift): compact after this much scroll. */
export const MIN_SCROLL = 8;

/** Back to the top state only this far above the threshold (no flicker at the edge). */
export const HYSTERESIS = 8;

/** The lockup in the compact bar from 1024 px: 48 of its 56 px (header.css, --lockup-scale; the owner, 2026-11-25). */
export const COMPACT_SCALE = 48 / 56;

/** The least space between the lockup and the first label when the row rides in the bar. */
export const BRAND_GAP = 32;

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
 * Where the seven labels fit in the bar beside the lockup and the tools: { top, compact } (booleans: at the top of
 * the page with the 56 px lockup, once compact with the 48 px one), or null where the row is not shown at all
 * (below 1024 px, Russian below 69rem, a collapsed row: the menu button is already there).
 */
export function rowFit({ rowShown, collapsed, lockup, labels, gap, tools, bar, scale = COMPACT_SCALE }) {
    if (!rowShown || collapsed) return null;
    const needs = (brand) => brand + BRAND_GAP + labels + gap + tools;
    return { top: !overflows(needs(lockup), bar), compact: !overflows(needs(lockup * scale), bar) };
}

/** The row's state for a fit and a compact state: { inline, tucked, lifted } (header.css). */
export function rowState(fit, compact) {
    if (!fit) return { inline: false, tucked: false, lifted: false };
    const inline = fit.top || (compact && fit.compact);
    return { inline, tucked: compact && !inline, lifted: compact && !fit.top };
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
 * Measures the row, the bar and the masthead (section 2 above); sets .is-nav-collapsed, .is-mast-tight and
 * --tools-w and returns the row's fit. Everything it reads is laid out as CSS lays it out at this width with the
 * row in its own line (the state classes are cleared first and set again within the same frame, with transitions
 * held: .is-measuring).
 */
function measure(header) {
    const bar = header.querySelector('.d2-header__bar');
    const lockup = header.querySelector('.d2-brand__lockup img');
    const tools = header.querySelector('.d2-header__tools');
    const row = header.querySelector('.d2-header__navrow');
    const list = row?.querySelector('.d2-nav__list');
    const nav = list?.parentElement;
    if (!bar || !tools) return null;

    header.classList.remove('is-nav-collapsed', 'is-mast-tight', 'is-inline', 'is-tucked', 'is-lifted');
    const barWidth = contentWidth(bar);
    const gap = px(getComputedStyle(bar).columnGap);
    // Layout widths (offsetWidth): the compact lockup's scale is a transform and must not count here.
    const lockupWidth = lockup ? lockup.offsetWidth : 0;
    const toolsWidth = tools.getBoundingClientRect().width;
    header.style.setProperty('--tools-w', `${Math.ceil(toolsWidth)}px`);

    // The masthead: the lockup beside the tools (the language menu leaves for the menu sheet where they do not fit).
    header.classList.toggle('is-mast-tight', overflows(lockupWidth + gap + toolsWidth, barWidth));

    if (!list || !nav || !rowShown(row)) return null;
    const items = Array.from(list.children).filter((li) => li.getClientRects().length > 0);
    const labels = items.reduce((sum, li) => sum + li.getBoundingClientRect().width, 0);
    // In its own row the first label's padding hangs outside the container (the text starts on its edge).
    const pad = px(getComputedStyle(list.querySelector('.d2-nav__link') ?? list).paddingInlineStart);
    const collapsed = overflows(labels - pad, contentWidth(nav));
    header.classList.toggle('is-nav-collapsed', collapsed);

    return rowFit({ rowShown: true, collapsed, lockup: lockupWidth, labels, gap, tools: toolsWidth, bar: barWidth });
}

/** The header's state machine: scroll position x fit. Returns refresh(), which measures again and re-applies. */
function controller(header) {
    let threshold = MIN_SCROLL;
    let compact = false;
    let fit = null; // the row's fit from the last measure: { top, compact } | null
    let ticking = false;
    let undock = 0;

    const set = (name, on) => header.classList.toggle(name, on);

    const endDocking = () => {
        window.clearTimeout(undock);
        header.classList.remove('is-docking');
    };

    const apply = (next, force = false) => {
        const state = rowState(fit, next);
        const wasDocked = header.classList.contains('is-inline') && header.classList.contains('is-lifted');
        set('is-inline', state.inline);
        set('is-tucked', state.tucked);
        set('is-lifted', state.lifted);
        const docked = state.inline && state.lifted;
        if (docked && !wasDocked && !force && header.classList.contains('is-live')) {
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
        const layout = state.inline ? 'inline' : state.tucked ? 'tucked' : null;
        window.dispatchEvent(new CustomEvent('d2:header-compact', { detail: { compact: next, layout } }));
    };

    const update = () => {
        ticking = false;
        apply(nextCompact(compact, window.scrollY, threshold));
    };

    const refresh = () => {
        // Transitions are held while the state classes are cleared for measuring and set again (no jump on resize).
        header.classList.add('is-measuring');
        threshold = compactThreshold(px(getComputedStyle(header).top));
        fit = measure(header);
        apply(nextCompact(compact, window.scrollY, threshold), true);
        void header.offsetWidth;
        header.classList.remove('is-measuring');
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
        // Width changes only: moving the row into the bar or tucking it changes the header's height, not the fit.
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

/**
 * The styleguide's static specimens (.d2-header--demo: no controller) need the tools' width as well, or the inline
 * row's spacer is 0 and the last label runs under the language menu. Layout width (offsetWidth): the docked frame
 * is zoomed.
 */
function specimens() {
    document.querySelectorAll('.d2-header--demo').forEach((demo) => {
        const tools = demo.querySelector('.d2-header__tools');
        if (tools) demo.style.setProperty('--tools-w', `${tools.offsetWidth}px`);
    });
}

export default function header() {
    const bar = document.querySelector('[data-d2-header]');
    if (bar) watch(bar, controller(bar));
    if (document.querySelector('.d2-header--demo')) {
        specimens();
        document.fonts?.ready.then(specimens);
    }

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
