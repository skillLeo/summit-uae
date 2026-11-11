/**
 * Inner-page table of contents (resources/views/components/d2/inner/toc.blade.php; upgrade spec §7.4,
 * §8 sheets, §12 Phase D).
 *
 * 1. The "On this page" bar (< 1280 px): pages/inner.css lays the bar out from the first paint under html.js
 *    (the sticky bar with its button, the plain list hidden), so mounting moves nothing (§4b integration rule).
 *    Here the bar gets .is-enhanced (its button opens the sheet through lib/sheet.js, data-sheet-open); a bar
 *    whose sheet is missing gets .is-plain instead, which brings the plain list back.
 *    Without JavaScript the bar is the plain list of links.
 * 2. Scroll-spy: the section being read is the last one whose top has passed the reading line (30 % down
 *    the window, never above the header and the bar); at the very end of the page the last section in view
 *    wins. Every TOC link to it (desktop list, sheet, inline list) gets aria-current="true". Before the
 *    first section nothing is current. (Design 2.1: the bar no longer repeats the current heading after a
 *    middle dot, cut to one line: brief §3.6 rules 2 and 8; the sheet marks it.)
 * 3. A desktop TOC link moves keyboard focus to its section and scrolls there (smooth unless reduced motion;
 *    the sheet's links get the same from lib/sheet.js), and the desktop list keeps the current link and the
 *    one before it in view inside itself (never the page), back at its top while nothing or the first
 *    section is current (listScroll()).
 * 4. A desktop list taller than its rail scrolls inside its frame ([data-toc-frame]): the frame carries
 *    .is-clip-start / .is-clip-end while entries are cut off above / below (the CSS fades that edge and shows
 *    the "more" cue at the bottom); a click on the cue ([data-toc-more]) scrolls the list by a page.
 */

import { scrollBehavior } from '../lib/prefs.js';

/** Index of the section being read; -1 before the first one. tops: section tops relative to the viewport. */
export function activeIndex(tops, line, { atEnd = false, viewport = Infinity } = {}) {
    let index = -1;
    for (let i = 0; i < tops.length; i += 1) {
        if (tops[i] - line <= 1) index = i;
        else break;
    }
    if (atEnd) {
        for (let i = tops.length - 1; i > index; i -= 1) {
            if (tops[i] < viewport) return i;
        }
    }
    return index;
}

/**
 * Scroll position of a contents list that keeps `index` (and the entry before it) in view.
 * tops / bottoms: the entries' edges inside the list's scrolled content; null or index ≤ 0 → 0 (the top).
 */
export function listScroll(index, tops, bottoms, { scrollTop = 0, viewport = 0, max = Infinity } = {}) {
    if (!Number.isInteger(index) || index <= 0 || index >= tops.length) return 0;
    let next = scrollTop;
    const bottom = bottoms[index];
    if (bottom > next + viewport) next = bottom - viewport;
    if (tops[index - 1] < next) next = tops[index - 1];
    // the current entry wins when the two do not fit together
    if (bottom > next + viewport) next = Math.min(tops[index], bottom - viewport);
    return Math.max(0, Math.min(max, Math.round(next)));
}

const decode = (hash) => {
    try {
        return decodeURIComponent(hash.replace(/^#/, ''));
    } catch {
        return '';
    }
};

function focusSection(target) {
    if (!target) return;
    if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
    target.focus({ preventScroll: true });
}

export default function toc() {
    const bars = Array.from(document.querySelectorAll('[data-toc-bar]'));
    const lists = Array.from(document.querySelectorAll('[data-toc], [data-toc-inline]'));
    if (!lists.length) return;

    // 1. The bars that have a sheet to open keep the layout the CSS gave them at the first paint.
    for (const bar of bars) {
        const button = bar.querySelector('[data-sheet-open]');
        const id = button ? button.getAttribute('data-sheet-open') : null;
        if (!button || !id || !document.querySelector(`[data-sheet="${CSS.escape(id)}"]`)) {
            bar.classList.add('is-plain');
            continue;
        }
        bar.classList.add('is-enhanced');
    }

    // 2. Targets in document order of the first list.
    const links = lists.flatMap((list) => Array.from(list.querySelectorAll('a[href^="#"]')));
    const ids = [];
    for (const link of links) {
        const id = decode(link.getAttribute('href') || '');
        if (id && !ids.includes(id)) ids.push(id);
    }
    const targets = ids.map((id) => document.getElementById(id)).filter(Boolean);
    if (!targets.length) return;

    const header = document.querySelector('[data-d2-header]');
    const scrollers = Array.from(document.querySelectorAll('[data-toc-scroller]'));

    let current = null;
    let frame = 0;

    // The reading line: 30 % down the window, and never above the header and a stuck TOC bar.
    const readingLine = () => {
        let line = header ? header.getBoundingClientRect().bottom : 0;
        for (const bar of bars) {
            if (!bar.classList.contains('is-enhanced')) continue;
            const box = bar.getBoundingClientRect();
            if (box.height > 0 && box.top <= line + 1) line = Math.max(line, box.bottom);
        }
        return Math.max(line + 24, window.innerHeight * 0.3);
    };

    // The desktop list: the current entry and the one before it stay in view; the top while nothing or the
    // first section is current. Entries hidden by a filter (P03) do not count.
    const keepInView = (id) => {
        for (const scroller of scrollers) {
            if (scroller.scrollHeight <= scroller.clientHeight + 1) continue;
            const entries = Array.from(scroller.querySelectorAll('a[href^="#"]')).filter((a) => a.getClientRects().length > 0);
            const index = id ? entries.findIndex((a) => decode(a.getAttribute('href') || '') === id) : -1;
            const box = scroller.getBoundingClientRect();
            const tops = entries.map((a) => a.getBoundingClientRect().top - box.top + scroller.scrollTop);
            const bottoms = entries.map((a, i) => tops[i] + a.getBoundingClientRect().height);
            const next = listScroll(index, tops, bottoms, { scrollTop: scroller.scrollTop, viewport: scroller.clientHeight, max: scroller.scrollHeight - scroller.clientHeight });
            if (Math.abs(next - scroller.scrollTop) >= 1) scroller.scrollTo({ top: next, behavior: scrollBehavior() });
        }
    };

    const setCurrent = (id) => {
        if (id === current) return;
        current = id;
        for (const link of links) {
            if (id && decode(link.getAttribute('href') || '') === id) link.setAttribute('aria-current', 'true');
            else link.removeAttribute('aria-current');
        }
        keepInView(id);
    };

    // 4. Clipped edges of the desktop lists (fade + "more" cue) and the cue itself.
    const frames = Array.from(document.querySelectorAll('[data-toc-frame]'))
        .map((el) => ({ el, list: el.querySelector('[data-toc-scroller]'), more: el.querySelector('[data-toc-more]') }))
        .filter((f) => f.list);
    const clip = () => {
        for (const { el, list } of frames) {
            const room = list.scrollHeight - list.clientHeight;
            el.classList.toggle('is-clip-start', room > 1 && list.scrollTop > 1);
            el.classList.toggle('is-clip-end', room > 1 && list.scrollTop < room - 1);
        }
    };
    for (const { list, more } of frames) {
        list.addEventListener('scroll', clip, { passive: true });
        // entries hidden or shown again by a filter (P03) change what is cut off
        if (typeof MutationObserver === 'function') new MutationObserver(clip).observe(list, { subtree: true, attributes: true, attributeFilter: ['hidden'] });
        more?.addEventListener('click', () => {
            list.scrollBy({ top: Math.max(list.clientHeight - 48, 48), behavior: scrollBehavior() });
        });
    }

    const update = () => {
        frame = 0;
        clip();
        const doc = document.documentElement;
        const atEnd = window.innerHeight + window.scrollY >= doc.scrollHeight - 2;
        const tops = targets.map((target) => target.getBoundingClientRect().top);
        const index = activeIndex(tops, readingLine(), { atEnd, viewport: window.innerHeight });
        setCurrent(index >= 0 ? targets[index].id : null);
    };

    const schedule = () => {
        if (!frame) frame = window.requestAnimationFrame(update);
    };

    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule, { passive: true });
    window.addEventListener('hashchange', schedule);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(schedule).catch(() => {});
    update();

    // 3. Desktop / inline TOC links (the sheet's links are handled by lib/sheet.js): focus moves to the
    //    section FIRST (without scrolling), then the page scrolls to it — focusing during the browser's own
    //    smooth anchor scroll would cancel that scroll. The hash is updated without a second jump.
    for (const list of lists) {
        if (list.closest('[data-sheet]')) continue;
        list.addEventListener('click', (event) => {
            const link = event.target instanceof Element ? event.target.closest('a[href^="#"]') : null;
            if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            const id = decode(link.getAttribute('href') || '');
            const target = document.getElementById(id);
            if (!target) return;
            event.preventDefault();
            focusSection(target);
            target.scrollIntoView({ behavior: scrollBehavior(), block: 'start' });
            try {
                if (window.location.hash !== `#${id}`) window.history.pushState(window.history.state, '', `#${encodeURIComponent(id)}`);
            } catch {
                /* the jump itself already happened */
            }
            schedule();
        });
    }
}
