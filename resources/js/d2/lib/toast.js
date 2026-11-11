/**
 * Toasts (spec §6.7; Design 2.1: an overlay, data-overlay): `toast(message, { tone } = {})` shows a bottom-centre
 * ink panel in the layout's [data-toast-region] (a polite live region, so the message is announced). On phones it
 * sits above the tab bar (CSS: tab bar height + safe area + 12 px). The tone is in the words, not a colour.
 *
 * - Auto-hides after 4 s; every timer pauses while the pointer is over a toast, while focus is inside
 *   one (an action button) and while the page is hidden.
 * - At most two are visible: a third one dismisses the oldest.
 * - Enter / exit animate transform + opacity (spring); under reduced motion they only fade. The toasts
 *   that stay slide to their new place (FLIP).
 * - The message is plain text (textContent), never HTML. Pass server-provided strings only.
 *
 * Options: tone 'neutral' | 'info' | 'success' | 'warning' | 'danger'; duration (ms, default 4000);
 * action { label, onClick } for one optional button. Returns { dismiss() }.
 * Declarative triggers: <button data-toast="…" data-toast-tone="success"> after bindToastTriggers().
 */
import { reducedMotion } from './prefs.js';

const MAX_VISIBLE = 2;
const DURATION = 4000;
const MIN_AFTER_PAUSE = 1200;
const TONES = ['neutral', 'info', 'success', 'warning', 'danger'];
const EASE_OUT = 'cubic-bezier(0.22, 1, 0.36, 1)';
const EASE_IN = 'cubic-bezier(0.55, 0, 1, 0.45)';

const live = []; // visible toasts, oldest first
const pauseReasons = new Set();
let regionEl = null;
let triggersBound = false;

function token(name, fallback) {
    const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return value || fallback;
}

function ms(name, fallback) {
    const value = token(name, '');
    const n = parseFloat(value);
    if (!Number.isFinite(n)) return fallback;
    return value.endsWith('ms') ? n : value.endsWith('s') ? n * 1000 : n;
}

/** Web Animations with a CSS-spring easing; falls back to ease-out where linear() is unknown. */
function play(el, keyframes, options) {
    if (typeof el.animate !== 'function') return null;
    try {
        return el.animate(keyframes, options);
    } catch {
        return el.animate(keyframes, { ...options, easing: EASE_OUT });
    }
}

function snapshot(region) {
    return new Map(Array.from(region.children, (child) => [child, child.getBoundingClientRect()]));
}

/** Slide toasts that moved because a sibling arrived or left. */
function settle(region, before, except) {
    if (reducedMotion()) return;
    const duration = ms('--spring-ui-dur', 240);
    const easing = token('--spring-ui', EASE_OUT);
    for (const child of region.children) {
        const old = before.get(child);
        if (!old || child === except) continue;
        const dy = old.top - child.getBoundingClientRect().top;
        if (Math.abs(dy) < 0.5) continue;
        play(child, [{ transform: `translateY(${dy}px)` }, { transform: 'translateY(0)' }], { duration, easing, composite: 'add' });
    }
}

function startTimer(item) {
    if (item.closing || item.timer || pauseReasons.size) return;
    item.startedAt = performance.now();
    item.timer = window.setTimeout(() => close(item), item.remaining);
}

function stopTimer(item) {
    if (!item.timer) return;
    window.clearTimeout(item.timer);
    item.timer = 0;
    item.remaining = Math.max(MIN_AFTER_PAUSE, item.remaining - (performance.now() - item.startedAt));
}

function setPaused(reason, on) {
    const had = pauseReasons.size > 0;
    if (on) pauseReasons.add(reason);
    else pauseReasons.delete(reason);
    const has = pauseReasons.size > 0;
    if (had === has) return;
    live.forEach(has ? stopTimer : startTimer);
}

function region() {
    if (regionEl?.isConnected) return regionEl;
    regionEl = document.querySelector('[data-toast-region]');
    if (!regionEl) {
        regionEl = document.createElement('div');
        regionEl.className = 'toast-region';
        regionEl.setAttribute('data-toast-region', '');
        // Not aria-atomic: each toast is its own element, so only the new one is announced.
        regionEl.setAttribute('aria-live', 'polite');
        document.body.append(regionEl);
    }
    if (!regionEl.dataset.toastBound) {
        regionEl.dataset.toastBound = '1';
        const r = regionEl;
        r.addEventListener('pointerover', () => setPaused('hover', true));
        r.addEventListener('pointerout', (e) => {
            if (!r.contains(e.relatedTarget)) setPaused('hover', false);
        });
        r.addEventListener('focusin', () => setPaused('focus', true));
        r.addEventListener('focusout', (e) => {
            if (!r.contains(e.relatedTarget)) setPaused('focus', false);
        });
        document.addEventListener('visibilitychange', () => setPaused('hidden', document.hidden));
    }
    return regionEl;
}

function close(item) {
    if (item.closing) return;
    item.closing = true;
    stopTimer(item);
    const i = live.indexOf(item);
    if (i > -1) live.splice(i, 1);
    if (item.el.contains(document.activeElement)) setPaused('focus', false);

    const reduced = reducedMotion();
    const frames = reduced
        ? [{ opacity: 1 }, { opacity: 0 }]
        : [
              { opacity: 1, transform: 'none' },
              { opacity: 0, transform: 'translateY(8px) scale(0.96)' },
          ];
    const animation = play(item.el, frames, { duration: ms('--dur-fade', 160), easing: EASE_IN, fill: 'forwards' });
    const done = () => {
        const r = item.el.parentElement;
        if (!r) return;
        const before = snapshot(r);
        item.el.remove();
        settle(r, before);
    };
    if (animation) animation.finished.then(done, done);
    else done();
}

/**
 * Show a toast. `message` must be a server-provided (translated) string.
 * @returns {{ dismiss: () => void }}
 */
export function toast(message, { tone = 'neutral', duration = DURATION, action = null } = {}) {
    const text = typeof message === 'string' ? message.trim() : '';
    if (!text || typeof document === 'undefined') return { dismiss() {} };
    const r = region();
    const safeTone = TONES.includes(tone) ? tone : 'neutral';

    const el = document.createElement('div');
    el.className = `toast toast--${safeTone}`;
    // An overlay (brief §3.4, design lint: [data-overlay] may carry the overlay shadow). The tone is carried by
    // the words: Design 2.1 draws no coloured dot.
    el.setAttribute('data-overlay', '');
    const body = document.createElement('p');
    body.className = 'toast__text';
    body.textContent = text;
    el.append(body);

    const item = { el, remaining: Math.max(1000, Number(duration) || DURATION), startedAt: 0, timer: 0, closing: false };

    if (action && typeof action.label === 'string' && action.label && typeof action.onClick === 'function') {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'toast__action';
        button.textContent = action.label;
        button.addEventListener('click', () => {
            action.onClick();
            close(item);
        });
        el.append(button);
    }

    while (live.length >= MAX_VISIBLE) close(live[0]);

    const before = snapshot(r);
    r.append(el);
    live.push(item);
    settle(r, before, el);

    const reduced = reducedMotion();
    play(
        el,
        reduced
            ? [{ opacity: 0 }, { opacity: 1 }]
            : [
                  { opacity: 0, transform: 'translateY(16px) scale(0.96)' },
                  { opacity: 1, transform: 'none' },
              ],
        reduced
            ? { duration: ms('--dur-fade', 160), easing: EASE_OUT }
            : { duration: ms('--spring-snappy-dur', 200), easing: token('--spring-snappy', EASE_OUT) },
    );
    startTimer(item);

    return { dismiss: () => close(item) };
}

/**
 * Declarative triggers: any element with data-toast="message" (and optional data-toast-tone) shows that
 * toast when clicked. Safe to call more than once.
 */
export function bindToastTriggers() {
    if (triggersBound) return;
    triggersBound = true;
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest?.('[data-toast]');
        if (!trigger || trigger.getAttribute('aria-disabled') === 'true') return;
        toast(trigger.dataset.toast, { tone: trigger.dataset.toastTone });
    });
}
