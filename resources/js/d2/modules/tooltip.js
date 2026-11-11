/**
 * Tooltips (spec §6.7): <x-d2.ui.tooltip text="…"> (bubble rendered by the server next to its trigger)
 * and any element with data-tooltip="…" (bubble created here, in one layer at the end of <body>).
 *
 * - Shows 120 ms after hover or keyboard focus (at once when moving between triggers), hides on
 *   leave and blur, on press, and with Esc (the Esc is consumed only while a tooltip is showing).
 *   The pointer may move onto the bubble without it disappearing (WCAG 1.4.13). Touch never shows it.
 * - Sits above the trigger, flips below near the top edge, stays 8 px inside the viewport, follows
 *   scrolling. Rendered as a manual popover (top layer) so transforms, overflow and z-index never clip it.
 * - aria-describedby points at the bubble unless the text only repeats the control's own name; a
 *   tooltip is never the only label of a control.
 * - A data-tooltip bubble copies its trigger's data-copy-status="proposed" (proposed copy stays marked, §8.7).
 * - Enter: opacity + 4 px slide (fade only under reduced motion).
 *
 * The declarative toast triggers ([data-toast]) are bound by modules/toast.js.
 */
import { reducedMotion } from '../lib/prefs.js';

const SHOW_DELAY = 120;
const HIDE_GRACE = 100;
const WARM_WINDOW = 400;
const GAP = 8;
const EDGE = 8;
const FOCUSABLE = 'a[href], button, input, select, textarea, summary, [role="link"], [tabindex]:not([tabindex="-1"])';

const records = new WeakMap();
let active = null;
let pending = null;
let waiting = null; // focused trigger still scrolling into view: shown when it arrives
let suppressed = null;
let showTimer = 0;
let hideTimer = 0;
let lastHidden = -Infinity;
let layer = null;
let uid = 0;
let frame = 0;

const norm = (s) => (s || '').replace(/\s+/g, ' ').trim().toLocaleLowerCase();

function nameOf(el) {
    const ids = el.getAttribute('aria-labelledby');
    if (ids) return ids.split(/\s+/).map((id) => document.getElementById(id)?.textContent ?? '').join(' ');
    return el.getAttribute('aria-label') || el.textContent || '';
}

function describe(trigger, bubble) {
    if (norm(nameOf(trigger)) === norm(bubble.textContent)) return;
    const ids = (trigger.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
    if (!ids.includes(bubble.id)) trigger.setAttribute('aria-describedby', [...ids, bubble.id].join(' '));
}

function hostRecord(host) {
    let rec = records.get(host);
    if (rec) return rec;
    const bubble = host.querySelector(':scope > .tooltip');
    const anchor = host.querySelector(FOCUSABLE) ?? host.firstElementChild;
    if (!bubble || !anchor || anchor === bubble) return null;
    rec = { anchor, zone: host, bubble };
    describe(anchor, bubble);
    records.set(host, rec);
    return rec;
}

/**
 * Proposed copy stays marked in its bubble (brief §8.7): a data-tooltip bubble lives at the end of <body>, outside
 * its trigger, so it copies the trigger's data-copy-status="proposed" (and drops it when the trigger has none).
 */
export function markCopyStatus(trigger, bubble) {
    if (trigger.dataset.copyStatus === 'proposed') bubble.dataset.copyStatus = 'proposed';
    else delete bubble.dataset.copyStatus;
}

function attrRecord(el) {
    const text = (el.dataset.tooltip || '').trim();
    if (!text) return null;
    let rec = records.get(el);
    if (!rec) {
        if (!layer?.isConnected) {
            layer = document.createElement('div');
            layer.className = 'tooltip-layer';
            document.body.append(layer);
        }
        const bubble = document.createElement('span');
        bubble.className = 'tooltip';
        bubble.id = `d2-tip-a${(uid += 1)}`;
        bubble.setAttribute('role', 'tooltip');
        bubble.setAttribute('popover', 'manual');
        bubble.setAttribute('data-overlay', ''); // an overlay (brief §3.4): the design lint allows its shadow
        bubble.dataset.placement = el.dataset.tooltipPlacement === 'bottom' ? 'bottom' : 'top';
        if (el.dataset.tooltipDir === 'ltr' || el.dataset.tooltipDir === 'rtl') bubble.dir = el.dataset.tooltipDir;
        layer.append(bubble);
        rec = { anchor: el, zone: el, bubble };
        records.set(el, rec);
    }
    if (rec.bubble.textContent !== text) rec.bubble.textContent = text;
    markCopyStatus(el, rec.bubble);
    describe(el, rec.bubble);
    return rec;
}

function recordFor(node) {
    const el = node?.closest?.('[data-tooltip], [data-tooltip-host]');
    if (!el || el.closest('.tooltip')) return null;
    return el.hasAttribute('data-tooltip-host') ? hostRecord(el) : attrRecord(el);
}

const inZone = (rec, node) => !!node && (rec.zone.contains(node) || rec.bubble.contains(node));

function place(rec) {
    const { anchor, bubble } = rec;
    const r = anchor.getBoundingClientRect();
    const b = bubble.getBoundingClientRect();
    const vw = document.documentElement.clientWidth;
    const vh = window.innerHeight;
    const above = r.top - GAP - b.height;
    const below = r.bottom + GAP;
    let side = bubble.dataset.placement === 'bottom' ? 'bottom' : 'top';
    if (side === 'top' && above < EDGE) side = 'bottom';
    else if (side === 'bottom' && below + b.height > vh - EDGE && above >= EDGE) side = 'top';
    const x = Math.min(Math.max(EDGE, r.left + r.width / 2 - b.width / 2), Math.max(EDGE, vw - EDGE - b.width));
    const y = side === 'top' ? above : below;
    bubble.style.translate = `${Math.round(x)}px ${Math.round(y)}px`;
    bubble.dataset.side = side;
    return { side, visible: r.bottom > 0 && r.top < vh && r.width > 0 };
}

function hideNow(rec) {
    if (!rec) return;
    rec.bubble.getAnimations?.().forEach((a) => a.cancel());
    try {
        if (rec.bubble.matches(':popover-open')) rec.bubble.hidePopover();
    } catch {
        /* no popover support */
    }
    rec.bubble.classList.remove('is-open');
    if (active === rec) {
        active = null;
        lastHidden = performance.now();
    }
}

function show(rec) {
    window.clearTimeout(hideTimer);
    pending = null;
    if (active === rec) return;
    if (active) hideNow(active);
    const { bubble } = rec;
    bubble.getAnimations?.().forEach((a) => a.cancel());
    bubble.classList.add('is-open');
    try {
        bubble.showPopover?.();
    } catch {
        /* already open, or no popover support: the fixed bubble still shows */
    }
    const { side, visible } = place(rec);
    if (!visible) {
        hideNow(rec);
        waiting = rec;
        return;
    }
    waiting = null;
    active = rec;
    const frames = reducedMotion()
        ? [{ opacity: 0 }, { opacity: 1 }]
        : [
              { opacity: 0, transform: `translateY(${side === 'top' ? 4 : -4}px)` },
              { opacity: 1, transform: 'none' },
          ];
    bubble.animate?.(frames, { duration: 140, easing: 'cubic-bezier(0.22, 1, 0.36, 1)' });
}

function hide(rec = active) {
    window.clearTimeout(hideTimer);
    if (!rec || active !== rec) return;
    const animation = rec.bubble.animate?.([{ opacity: 1 }, { opacity: 0 }], { duration: 100, easing: 'ease-in', fill: 'forwards' });
    if (!animation) {
        hideNow(rec);
        return;
    }
    active = null;
    lastHidden = performance.now();
    animation.finished.then(
        () => {
            if (active !== rec) hideNow(rec);
        },
        () => {},
    );
}

function cancelShow() {
    window.clearTimeout(showTimer);
    pending = null;
    waiting = null;
}

function schedule(rec) {
    window.clearTimeout(hideTimer);
    if (active === rec || pending === rec) return;
    cancelShow();
    if (performance.now() - lastHidden < WARM_WINDOW || active) {
        show(rec);
        return;
    }
    pending = rec;
    showTimer = window.setTimeout(() => {
        if (pending === rec) show(rec);
    }, SHOW_DELAY);
}

/** Keep the bubble on its trigger while the page scrolls (focus scrolling is smooth). */
function follow() {
    if ((!active && !waiting) || frame) return;
    frame = window.requestAnimationFrame(() => {
        frame = 0;
        if (active) {
            if (!place(active).visible) {
                waiting = active.zone.contains(document.activeElement) ? active : null;
                hideNow(active);
            }
        } else if (waiting) {
            if (!waiting.zone.contains(document.activeElement)) waiting = null;
            else if (waiting.anchor.getBoundingClientRect().top < window.innerHeight) show(waiting);
        }
    });
}

export default function tooltips() {
    // Wire aria-describedby up front, so the first keyboard focus already reads the description.
    document.querySelectorAll('[data-tooltip-host]').forEach(hostRecord);
    document.querySelectorAll('[data-tooltip]').forEach(attrRecord);

    document.addEventListener('pointerover', (e) => {
        if (e.pointerType === 'touch') return;
        if (active && active.bubble.contains(e.target)) {
            window.clearTimeout(hideTimer);
            return;
        }
        const rec = recordFor(e.target);
        if (!rec || rec === suppressed) return;
        schedule(rec);
    });

    document.addEventListener('pointerout', (e) => {
        if (e.pointerType === 'touch') return;
        const rec = active ?? pending;
        if (suppressed && inZone(suppressed, e.target) && !inZone(suppressed, e.relatedTarget)) suppressed = null;
        if (!rec || !inZone(rec, e.target) || inZone(rec, e.relatedTarget)) return;
        if (rec.zone.contains(document.activeElement) && document.activeElement.matches(':focus-visible')) return;
        cancelShow();
        hideTimer = window.setTimeout(() => hide(rec), HIDE_GRACE);
    });

    document.addEventListener('focusin', (e) => {
        const rec = recordFor(e.target);
        if (!rec || !e.target.matches(':focus-visible')) return;
        suppressed = null;
        schedule(rec);
    });

    document.addEventListener('focusout', (e) => {
        const rec = active ?? pending ?? waiting;
        if (!rec || !rec.zone.contains(e.target) || rec.zone.contains(e.relatedTarget)) return;
        cancelShow();
        hide(rec);
    });

    document.addEventListener(
        'pointerdown',
        (e) => {
            const rec = recordFor(e.target);
            if (!rec) return;
            cancelShow();
            suppressed = rec;
            if (active === rec) hideNow(rec);
        },
        true,
    );

    document.addEventListener(
        'keydown',
        (e) => {
            if (e.key !== 'Escape') return;
            if (active) {
                e.stopPropagation();
                suppressed = active;
                hide(active);
            }
            cancelShow();
        },
        true,
    );

    window.addEventListener('scroll', follow, { passive: true, capture: true });
    window.addEventListener('resize', follow, { passive: true });
    window.addEventListener('blur', () => {
        cancelShow();
        hideNow(active);
    });
}
