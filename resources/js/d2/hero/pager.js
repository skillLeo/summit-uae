/**
 * Hero pager (<x-d2.hero.pager>; Design 2.1 brief D17): six progress segments (one button per document, filled up
 * to the one shown: CSS reads aria-current), the document's name (a polite live region) and previous / next. ← / →
 * work only while focus is inside the pager (mirrored in RTL: ← is "next" there), Home / End jump to the ends; there
 * is no global key handler, no autoplay, no counter and no wrap-around. A step that leads nowhere is aria-disabled
 * (modules/disabled-guard.js blocks its clicks, focus stays). One Tab stop: a roving tabindex on the segments (the
 * document shown is the only one in the tab order), and a key that brings another document up moves focus to its
 * segment; previous / next stay out of the tab order (pointer and assistive technology; the keys do the same).
 * Strings come from the server: the segments' aria-labels are the documents' names.
 */
const clamp = (v, lo, hi) => Math.min(hi, Math.max(lo, v));

/**
 * The document a key brings up, or null (pure; Vitest: hero/pager.test.js). list: the documents in pager order;
 * dir: 1 (LTR) or -1 (RTL, where ← is "next").
 */
export function keyTarget(key, active, list, dir = 1) {
    if (!list.length) return null;
    const i = Math.max(0, list.indexOf(active));
    let j = i;
    if (key === 'ArrowRight' || key === 'ArrowLeft') j = clamp(i + ((key === 'ArrowRight') === (dir === 1) ? 1 : -1), 0, list.length - 1);
    else if (key === 'Home') j = 0;
    else if (key === 'End') j = list.length - 1;
    else return null;
    return list[j];
}

/** Which steps are disabled for the document at index i of n (pure; Vitest). */
export function edges(i, n) {
    return { prev: i <= 0, next: i < 0 || i >= n - 1 };
}

/**
 * @param {HTMLElement} el  the pager root
 * @param {{dir?: 1|-1, onSelect: (type: string, step: number) => void}} options
 *   onSelect(type, step): step is +1 when the document comes after the one shown, -1 before it
 */
export function pagerControls(el, { dir = 1, onSelect }) {
    const types = (el.dataset.types || '').split(' ').filter(Boolean);
    const segments = Array.from(el.querySelectorAll('[data-pager-seg]'));
    const steps = Array.from(el.querySelectorAll('[data-pager-step]'));
    const name = el.querySelector('[data-pager-name]');
    let active = segments.find((s) => s.getAttribute('aria-current') === 'true')?.dataset.pagerSeg || types[0];

    const segmentOf = (type) => segments.find((s) => s.dataset.pagerSeg === type) ?? null;
    const go = (type) => {
        if (!type || type === active || !types.includes(type)) return;
        onSelect(type, types.indexOf(type) > types.indexOf(active) ? 1 : -1);
    };

    function update(type) {
        active = type;
        for (const seg of segments) {
            const on = seg.dataset.pagerSeg === type;
            if (on) seg.setAttribute('aria-current', 'true');
            else seg.removeAttribute('aria-current');
            seg.tabIndex = on ? 0 : -1;
        }
        const { prev, next } = edges(types.indexOf(type), types.length);
        for (const btn of steps) {
            const off = Number(btn.dataset.pagerStep) < 0 ? prev : next;
            if (off) btn.setAttribute('aria-disabled', 'true');
            else btn.removeAttribute('aria-disabled');
        }
        // Only real changes reach the live region: the first call on load must not be announced.
        const label = segmentOf(type)?.getAttribute('aria-label') || '';
        if (name && label && name.textContent !== label) name.textContent = label;
    }

    function onClick(event) {
        const step = event.target.closest('[data-pager-step]');
        if (step && el.contains(step)) {
            if (step.getAttribute('aria-disabled') === 'true') return;
            const i = types.indexOf(active);
            go(types[clamp(i + Number(step.dataset.pagerStep), 0, types.length - 1)]);
            return;
        }
        const seg = event.target.closest('[data-pager-seg]');
        if (seg && el.contains(seg)) go(seg.dataset.pagerSeg);
    }

    function onKey(event) {
        if (event.altKey || event.ctrlKey || event.metaKey || event.defaultPrevented) return;
        const target = keyTarget(event.key, active, types, dir);
        if (target === null) return;
        event.preventDefault();
        go(target);
        // Focus follows the document shown (also from the previous / next buttons).
        const seg = segmentOf(active);
        if (seg && document.activeElement !== seg) seg.focus({ preventScroll: true });
    }

    el.addEventListener('click', onClick);
    el.addEventListener('keydown', onKey);
    update(active);

    return {
        update,
        types,
        get active() {
            return active;
        },
        destroy() {
            el.removeEventListener('click', onClick);
            el.removeEventListener('keydown', onKey);
        },
    };
}
