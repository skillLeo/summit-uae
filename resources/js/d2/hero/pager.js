/**
 * Hero pager (<x-d2.hero.pager>; Design 2.1 brief D17, amended by the owner's autoplay): six progress segments (one
 * button per document, filled up to the one shown: CSS reads aria-current), the document's name (a polite live
 * region), the pause / play toggle and previous / next. The pile wraps around (after the last document comes the
 * first), so previous / next are always enabled and ← / → wrap as well; Home / End jump to the ends. ← / → work only
 * while focus is inside the pager (mirrored in RTL: ← is "next" there); there is no global key handler and no
 * counter. One Tab stop for the segments (a roving tabindex: the document shown is the only one in the tab order,
 * and a key that brings another document up moves focus to its segment) plus the toggle; previous / next stay out of
 * the tab order (pointer and assistive technology; the keys do the same).
 *
 * While the samples play, the segment shown fills over the dwell (fill(ms): a WAAPI scaleX from the inline-start
 * edge, the autoplay's timer), the name stops announcing (aria-live off: a change every few seconds would talk over
 * the page) and the toggle reads "Pause"; stopped, the segment is full, the name polite and the toggle reads "Play".
 * Strings come from the server: the segments' aria-labels are the documents' names, the toggle's two names are its
 * data-label-pause / data-label-play.
 */
const clamp = (v, lo, hi) => Math.min(hi, Math.max(lo, v));

/**
 * The document a key brings up, or null (pure; Vitest: hero/pager.test.js). list: the documents in pager order;
 * dir: 1 (LTR) or -1 (RTL, where ← is "next"). The arrows wrap around; Home / End go to the ends.
 */
export function keyTarget(key, active, list, dir = 1) {
    if (!list.length) return null;
    const i = Math.max(0, list.indexOf(active));
    const n = list.length;
    let j = i;
    if (key === 'ArrowRight' || key === 'ArrowLeft') j = (i + ((key === 'ArrowRight') === (dir === 1) ? 1 : -1) + n) % n;
    else if (key === 'Home') j = 0;
    else if (key === 'End') j = n - 1;
    else return null;
    return list[j];
}

/** The toggle's state and name for an autoplay intent (pure; Vitest). */
export function toggleState(playing, labels = {}) {
    return playing ? { state: 'playing', label: labels.pause || '' } : { state: 'stopped', label: labels.play || '' };
}

/**
 * @param {HTMLElement} el  the pager root
 * @param {{
 *   dir?: 1|-1,
 *   onSelect: (type: string, step: number) => void,
 *   onToggle?: () => void,
 *   onPreview?: (type: string|null) => void,
 * }} options
 *   onSelect(type, step): a manual choice; step is +1 for the next document (wrapping), -1 for the previous one
 *   onPreview(type): a segment is hovered or focused (null: no longer)
 */
export function pagerControls(el, { dir = 1, onSelect, onToggle = () => {}, onPreview = () => {} }) {
    const types = (el.dataset.types || '').split(' ').filter(Boolean);
    const segments = Array.from(el.querySelectorAll('[data-pager-seg]'));
    const steps = Array.from(el.querySelectorAll('[data-pager-step]'));
    const toggle = el.querySelector('[data-pager-toggle]');
    const tip = toggle?.querySelector('[data-pager-tip]');
    const name = el.querySelector('[data-pager-name]');
    const labels = { pause: toggle?.dataset.labelPause || '', play: toggle?.dataset.labelPlay || '' };
    let active = segments.find((s) => s.getAttribute('aria-current') === 'true')?.dataset.pagerSeg || types[0];
    let timer = null;

    const segmentOf = (type) => segments.find((s) => s.dataset.pagerSeg === type) ?? null;
    const go = (type, step) => {
        if (!type || type === active || !types.includes(type)) return;
        onSelect(type, step ?? (types.indexOf(type) > types.indexOf(active) ? 1 : -1));
    };

    function update(type) {
        active = type;
        for (const seg of segments) {
            const on = seg.dataset.pagerSeg === type;
            if (on) seg.setAttribute('aria-current', 'true');
            else seg.removeAttribute('aria-current');
            seg.tabIndex = on ? 0 : -1;
        }
        // Only real changes reach the live region: the first call on load must not be announced.
        const label = segmentOf(type)?.getAttribute('aria-label') || '';
        if (name && label && name.textContent !== label) name.textContent = label;
    }

    /** The autoplay's state: the toggle's glyph and name, the fill rule, the live region. */
    function setPlaying(playing) {
        const { state, label } = toggleState(playing, labels);
        if (toggle) {
            toggle.dataset.state = state;
            if (label) toggle.setAttribute('aria-label', label);
            if (tip && label) tip.textContent = label;
        }
        if (playing) el.setAttribute('data-playing', '');
        else el.removeAttribute('data-playing');
        name?.setAttribute('aria-live', playing ? 'off' : 'polite');
    }

    /** Fill the shown segment over `ms`: the autoplay's dwell timer (pause / play / cancel it like any animation). */
    function fill(ms) {
        clearFill();
        const bar = segmentOf(active)?.querySelector('[data-pager-fill]');
        if (!bar || typeof bar.animate !== 'function') return null;
        timer = bar.animate({ transform: ['scaleX(0)', 'scaleX(1)'] }, { duration: ms, easing: 'linear', fill: 'forwards' });
        return timer;
    }

    function clearFill() {
        if (!timer) return;
        try {
            timer.cancel();
        } catch {
            /* gone */
        }
        timer = null;
    }

    function onClick(event) {
        if (toggle && toggle.contains(event.target)) {
            onToggle();
            return;
        }
        const step = event.target.closest('[data-pager-step]');
        if (step && el.contains(step)) {
            const s = Number(step.dataset.pagerStep) < 0 ? -1 : 1;
            const i = types.indexOf(active);
            go(types[(i + s + types.length) % types.length], s);
            return;
        }
        const seg = event.target.closest('[data-pager-seg]');
        if (seg && el.contains(seg)) go(seg.dataset.pagerSeg);
    }

    function onKey(event) {
        if (event.altKey || event.ctrlKey || event.metaKey || event.defaultPrevented) return;
        if (toggle && toggle.contains(event.target)) return;
        const target = keyTarget(event.key, active, types, dir);
        if (target === null) return;
        event.preventDefault();
        const i = types.indexOf(active);
        const j = types.indexOf(target);
        const arrow = event.key === 'ArrowRight' || event.key === 'ArrowLeft';
        go(target, arrow ? ((j - i + types.length) % types.length === 1 ? 1 : -1) : undefined);
        // Focus follows the document shown (also from the previous / next buttons).
        const seg = segmentOf(active);
        if (seg && document.activeElement !== seg) seg.focus({ preventScroll: true });
    }

    // The segment preview: the pile lifts the hovered or focused segment's sheet a little (hero/hover.js).
    const segOf = (event) => event.target.closest?.('[data-pager-seg]');
    function onOver(event) {
        if (event.pointerType && event.pointerType !== 'mouse' && event.pointerType !== 'pen') return;
        const seg = segOf(event);
        if (seg) onPreview(seg.dataset.pagerSeg);
    }
    function onOut(event) {
        const seg = segOf(event);
        if (seg && !seg.contains(event.relatedTarget)) onPreview(null);
    }
    function onFocus(event) {
        const seg = segOf(event);
        if (seg && seg.matches(':focus-visible')) onPreview(seg.dataset.pagerSeg);
    }
    function onBlur(event) {
        if (segOf(event)) onPreview(null);
    }

    el.addEventListener('click', onClick);
    el.addEventListener('keydown', onKey);
    el.addEventListener('pointerover', onOver);
    el.addEventListener('pointerout', onOut);
    el.addEventListener('focusin', onFocus);
    el.addEventListener('focusout', onBlur);
    // The pile wraps around: previous and next always lead somewhere.
    for (const btn of steps) btn.removeAttribute('aria-disabled');
    update(active);

    return {
        update,
        setPlaying,
        fill,
        clearFill,
        types,
        get active() {
            return active;
        },
        destroy() {
            clearFill();
            el.removeEventListener('click', onClick);
            el.removeEventListener('keydown', onKey);
            el.removeEventListener('pointerover', onOver);
            el.removeEventListener('pointerout', onOut);
            el.removeEventListener('focusin', onFocus);
            el.removeEventListener('focusout', onBlur);
        },
    };
}
