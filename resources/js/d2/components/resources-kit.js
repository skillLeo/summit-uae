/**
 * Shared helpers of the Legal Knowledge Centre (P03, components/resources.js) and the Case Studies page
 * (P04, components/casefile.js). Named exports only: app.js imports every components/*.js file, and this
 * one registers nothing.
 *
 * Pure (unit tested in __tests__/resources-kit.test.js):
 *   searchableText(root, exclude)  root.textContent without the subtrees matching `exclude`
 *   guideText(...parts)            the searchable text of a guide: every word normalised (shared
 *                                  normalise(): Arabic letter forms, diacritics, ё…) plus its light stem
 *                                  (palette/terms.js), so "salary" finds "salaries", «راتب» finds «الراتب»
 *   visibleGuides(guides, filter)  codes of the guides that match { query, topic }: every query word must
 *                                  occur in the guide's text (title, summary, category and full article)
 *   resultParts(strings, count)    the status line for a count: { parts, empty } (the number is its own part,
 *                                  printed in a <bdi>; 0 → the approved "no results" message)
 *   mostVisible(ratios)            index of the most visible card of a swipe rail
 *   topicLabel(options, value)     the approved topic name the phones' picker shows (the chosen option's text)
 * DOM (never run at import time):
 *   rail(list, dots)               position dots of a phone swipe rail, rebuilt for the visible cards
 *   deepLinks(root, options)       links to an anchor inside `root` (cards, contents, related links, "back to
 *                                  the directory"; on hashchange and on a repeated click) reveal the target
 *                                  (options.reveal, e.g. clear a filter that hides it), scroll to it below
 *                                  the sticky header and move keyboard focus to it; an incoming deep link
 *                                  (on load) is revealed and aimed at instantly, without moving focus
 *   jump(target)                   instant scroll to a target, whatever the CSS scroll-behavior
 */
import { scrollBehavior } from '../lib/prefs.js';
import { indexTerms, queryTerms } from '../palette/terms.js';

const TOKEN_SPLIT = /[\n\r\p{Z}\p{P}\p{S}]+/u;

/** Normalised words of the text, each followed by its stem when that differs. */
export function guideText(...parts) {
    const words = [];
    for (const part of parts) {
        for (const word of String(part ?? '').split(TOKEN_SPLIT)) {
            const terms = word ? indexTerms(word) : null;
            if (Array.isArray(terms)) words.push(...terms);
            else if (terms) words.push(terms);
        }
    }
    return ` ${words.join(' ')} `;
}

/**
 * The text content of `root` without the subtrees matching `exclude` (e.g. the guides' disclosure labels,
 * which are interface text, not guide text): the same string as root.textContent otherwise.
 */
export function searchableText(root, exclude = '') {
    if (!root) return '';
    if (!exclude) return root.textContent ?? '';
    const parts = [];
    const walk = (node) => {
        for (const child of node.childNodes) {
            if (child.nodeType === 3) parts.push(child.nodeValue);
            else if (child.nodeType === 1 && !child.matches(exclude)) walk(child);
        }
    };
    walk(root);
    return parts.join('');
}

/** True when every processed query term occurs in the text. */
export function textMatches(text, terms) {
    return terms.every((term) => text.includes(term));
}

/**
 * Codes of the guides that pass the filter, in their order.
 * guides: [{ code, topic, text }] (text from guideText); filter: { query: string, topic: string ('' = all) }.
 */
export function visibleGuides(guides, { query = '', topic = '' } = {}) {
    const terms = queryTerms(typeof query === 'string' ? query : '');
    const wanted = typeof topic === 'string' ? topic : '';
    return guides.filter((guide) => (!wanted || guide.topic === wanted) && textMatches(guide.text, terms)).map((guide) => guide.code);
}

/** True when the filter narrows anything (a query word or a topic). */
export function isFiltering({ query = '', topic = '' } = {}) {
    return queryTerms(typeof query === 'string' ? query : '').length > 0 || (typeof topic === 'string' && topic !== '');
}

/**
 * The status line for `count` results: strings = { results: { "0": "…__N__…", "1": … }, none: "…" } (rendered by
 * the server with trans_choice). Returns { empty, parts: [{ text, number }] }: a part with number: true is the
 * count (printed isolated); with no result the approved message is the only part.
 */
export function resultParts(strings, count) {
    const n = Number.isInteger(count) && count >= 0 ? count : 0;
    if (n === 0) return { empty: true, parts: [{ text: String(strings?.none ?? ''), number: false }] };
    const template = String(strings?.results?.[n] ?? strings?.results?.[String(n)] ?? n);
    const pieces = template.split('__N__');
    const parts = [];
    pieces.forEach((piece, i) => {
        if (piece) parts.push({ text: piece, number: false });
        if (i < pieces.length - 1) parts.push({ text: String(n), number: true });
    });
    return { empty: false, parts };
}

/** Index of the entry with the largest visible ratio (ties keep the first). */
export function mostVisible(ratios) {
    let best = 0;
    for (let i = 1; i < ratios.length; i += 1) {
        if (ratios[i] > ratios[best] + 0.001) best = i;
    }
    return best;
}

/** Element of a "#id" hash (plain string ids only), or null. */
export function hashTarget(hash, doc = document) {
    if (typeof hash !== 'string' || hash.length < 2 || hash[0] !== '#') return null;
    let id;
    try {
        id = decodeURIComponent(hash.slice(1));
    } catch {
        return null;
    }
    return id ? doc.getElementById(id) : null;
}

/** Move keyboard focus to a landing target without scrolling it again. */
export function focusTarget(target) {
    if (!target || typeof target.focus !== 'function') return;
    if (target.tabIndex < 0 && !target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
    target.focus({ preventScroll: true });
}

/** Scroll a target to the top of the window at once, whatever the page's CSS scroll-behavior says. */
export function jump(target) {
    try {
        target.scrollIntoView({ behavior: 'instant', block: 'start' });
    } catch {
        target.scrollIntoView({ block: 'start' });
    }
}

const modified = (event) => event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;

/**
 * Deep links into `root`. options.reveal(target) runs first (it may unhide the target, return true if it
 * changed the page). Returns a cleanup function.
 */
export function deepLinks(root, { reveal = () => false } = {}) {
    const inside = (target) => !!target && root.contains(target);

    const land = (target, { scroll = true } = {}) => {
        if (!inside(target)) return;
        const changed = reveal(target) === true;
        if (scroll || changed) target.scrollIntoView({ behavior: scrollBehavior(), block: 'start' });
        focusTarget(target);
    };

    const onHash = () => {
        const target = hashTarget(window.location.hash);
        // The browser has already scrolled to a visible target; a hidden one is revealed, then scrolled to.
        if (inside(target)) land(target, { scroll: false });
    };

    // Capture phase: a hidden target is revealed before the browser (or a sheet) follows the link.
    const onClick = (event) => {
        if (modified(event) || !(event.target instanceof Element)) return;
        const link = event.target.closest('a[href*="#"]');
        if (!link || !link.hash || link.pathname !== window.location.pathname || link.search !== window.location.search) return;
        const target = hashTarget(link.hash);
        if (!inside(target)) return;
        const changed = reveal(target) === true;
        if (link.hash === window.location.hash || changed) {
            // No hashchange will follow (same hash), or the page changed under the link: aim ourselves.
            window.setTimeout(() => land(target), 0);
        }
    };

    window.addEventListener('hashchange', onHash);
    document.addEventListener('click', onClick, true);

    // On load (no focus move: the visitor has not acted yet, and a focused page would turn the browser's own
    // fragment jump into a long smooth scroll through html:focus-within): reveal a hidden target, then aim
    // at it instantly after every enhancement has run (modules run after Alpine starts; the inner kit's
    // contents bar folds its no-JS list) and once more after the web fonts swap in, unless the visitor
    // has moved meanwhile.
    const initial = hashTarget(window.location.hash);
    if (inside(initial)) {
        const changed = reveal(initial) === true;
        let moved = false;
        const stop = () => {
            moved = true;
        };
        ['wheel', 'touchstart', 'keydown', 'pointerdown'].forEach((type) => window.addEventListener(type, stop, { once: true, passive: true }));
        const aim = () => {
            if (!moved) jump(initial);
        };
        if (changed) window.requestAnimationFrame(aim);
        window.setTimeout(aim, 0);
        if (document.readyState === 'complete') window.setTimeout(aim, 50);
        else window.addEventListener('load', () => window.setTimeout(aim, 0), { once: true });
        document.fonts?.ready?.then(() => window.requestAnimationFrame(aim));
    }

    return () => {
        window.removeEventListener('hashchange', onHash);
        document.removeEventListener('click', onClick, true);
    };
}

/**
 * The approved name the phones' topic picker shows in its box (resources.css .kc-find__pick-value): the text of
 * the option whose value is `value`, else the first option's (all topics); whitespace collapsed, never cut.
 * `options` is the <select>'s options collection (or any list of { value, textContent }).
 */
export function topicLabel(options, value = '') {
    const list = Array.from(options ?? []);
    const option = list.find((o) => o.value === String(value ?? '')) ?? list[0];
    return option ? String(option.textContent ?? '').replace(/\s+/g, ' ').trim() : '';
}

/**
 * Position dots of a phone swipe rail (spec §8 carousels): one dot per visible card in `dots`
 * (aria-hidden, decorative: every card is reachable by swipe and by Tab), the most visible one marked.
 * Returns { refresh(), destroy() }; refresh() after cards are hidden or shown.
 */
export function rail(list, dots, { dotClass = 'kc-dir__dot' } = {}) {
    let observer = null;

    const build = () => {
        if (observer) observer.disconnect();
        observer = null;
        if (!list || !dots) return;
        const items = Array.from(list.children).filter((item) => !item.hidden);
        dots.replaceChildren();
        if (items.length < 2 || !('IntersectionObserver' in window)) return;
        const marks = items.map((_, i) => {
            const dot = document.createElement('span');
            dot.className = dotClass + (i === 0 ? ' is-active' : '');
            return dot;
        });
        dots.append(...marks);
        const ratios = items.map(() => 0);
        observer = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    const index = items.indexOf(entry.target);
                    if (index !== -1) ratios[index] = entry.intersectionRatio;
                }
                const active = mostVisible(ratios);
                marks.forEach((dot, i) => dot.classList.toggle('is-active', i === active));
            },
            { root: list, threshold: [0, 0.25, 0.5, 0.75, 1] },
        );
        items.forEach((item) => observer.observe(item));
    };

    build();

    return {
        refresh: build,
        destroy() {
            if (observer) observer.disconnect();
            observer = null;
        },
    };
}

