/**
 * DOM helpers shared by the tool scripts (gratuity-view.js, rent-view.js). Loaded lazily with them.
 * Text always comes from the server (the tool's JSON config block); nothing here holds copy.
 */
import { prefill } from '../lib/prefill.js';
import { reducedMotion } from '../lib/prefs.js';
import { toast } from '../lib/toast.js';
import { parseNumber } from './format.js';

export const FADE_MS = 160; // number cross-fade (spec §12 C6)

/** The tool's strings and settings (<x-d2.tool.config>). */
export function readConfig(root) {
    try {
        return JSON.parse(root.querySelector('script[data-tool-config]')?.textContent || '{}');
    } catch {
        return {};
    }
}

/** The language of the tool (a styleguide specimen may differ from the page). */
export const langOf = (root) => root.closest('[lang]')?.getAttribute('lang') || document.documentElement.lang || 'en';

/** The field key ([data-input]) an event target belongs to. */
export const keyOf = (target) => (target instanceof Element ? target.closest('[data-input]')?.dataset.input ?? null : null);

/**
 * Current values of every [data-input] control, parsed: numbers via parseNumber (Arabic-Indic digits,
 * separators), dates as ISO strings ('invalid' while a date is half typed; the native date input and the
 * Design 2.1 date field, <x-d2.ui.date> [data-date-input], report the same .value and .validity.badInput),
 * radio groups and selects as their value.
 */
export function readValues(root, lang) {
    const values = {};
    for (const el of root.querySelectorAll('[data-input]')) {
        const key = el.dataset.input;
        if (el.tagName === 'FIELDSET') values[key] = el.querySelector('input:checked')?.value ?? null;
        else if (el.tagName === 'SELECT') values[key] = el.value;
        else if (el.type === 'date' || el.hasAttribute('data-date-input')) values[key] = el.validity?.badInput ? 'invalid' : el.value;
        else if (el.type === 'number') values[key] = el.validity?.badInput ? NaN : parseNumber(el.value, lang);
        else values[key] = parseNumber(el.value, lang);
    }
    return values;
}

/** Has the visitor typed something into this control (even a half date or an unparsable number)? */
export const hasInput = (el) => !!el && (el.value !== '' || el.validity?.badInput === true);

/** Fill an element with text and { bdi } parts (format.js parts()), without innerHTML. */
export function fillParts(el, content) {
    const list = Array.isArray(content) ? content : [String(content ?? '')];
    el.replaceChildren(
        ...list.map((part) => {
            if (typeof part === 'string') return document.createTextNode(part);
            const bdi = document.createElement('bdi');
            bdi.textContent = part.bdi;
            return bdi;
        }),
    );
}

/**
 * Change the text of an output with a 160 ms opacity cross-fade (the old value fades out on top of the
 * new one, both in one grid cell). Numbers never count up. Instant under reduced motion, while hidden
 * and on the first render.
 */
export function swap(el, content) {
    if (!el) return;
    const signature = JSON.stringify(content);
    if (el.__toolValue === signature) return;
    const first = el.__toolValue === undefined;
    el.__toolValue = signature;
    const next = document.createElement('span');
    next.className = 'tool__v';
    fillParts(next, content);
    const animate = !first && !reducedMotion() && typeof next.animate === 'function' && el.getClientRects().length > 0;
    if (!animate) {
        el.replaceChildren(next);
        return;
    }
    const previous = Array.from(el.children).filter((child) => child.classList.contains('tool__v'));
    for (const old of previous) old.setAttribute('aria-hidden', 'true');
    el.append(next);
    next.animate([{ opacity: 0 }, { opacity: 1 }], { duration: FADE_MS, easing: 'linear', fill: 'backwards' });
    for (const old of previous) {
        const fade = old.animate([{ opacity: 1 }, { opacity: 0 }], { duration: FADE_MS, easing: 'linear', fill: 'forwards' });
        const drop = () => old.remove();
        fade.onfinish = drop;
        fade.oncancel = drop;
    }
}

/**
 * Show or clear a field error (the <x-d2.ui.field> contract): the hidden [data-field-error] gets the
 * message, its id joins aria-describedby only while shown, aria-invalid and .is-invalid follow.
 */
export function setFieldError(control, message) {
    const field = control?.closest('[data-field]');
    const error = field?.querySelector('[data-field-error]');
    if (!control || !field || !error) return;
    const show = Boolean(message);
    if (show) {
        const text = error.querySelector('.field__error-text');
        if (text && text.textContent !== message) text.textContent = message;
    }
    error.hidden = !show;
    field.classList.toggle('is-invalid', show);
    control.classList.toggle('is-invalid', show);
    if (show) control.setAttribute('aria-invalid', 'true');
    else control.removeAttribute('aria-invalid');
    const ids = (control.getAttribute('aria-describedby') || '').split(/\s+/).filter((id) => id && id !== error.id);
    if (show) ids.push(error.id);
    if (ids.length) control.setAttribute('aria-describedby', ids.join(' '));
    else control.removeAttribute('aria-describedby');
}

/** Enable or disable a Phase A button (aria-disabled keeps focus; disabled-guard blocks clicks). */
export function setDisabled(button, disabled) {
    if (!button) return;
    if (disabled) button.setAttribute('aria-disabled', 'true');
    else button.removeAttribute('aria-disabled');
}

/** Polite announcement in the tool's own live region, after the visitor stops typing. */
export function announcer(region, delay = 900) {
    let timer = 0;
    let last = '';
    return (message, { force = false } = {}) => {
        window.clearTimeout(timer);
        if (!region || !message) return;
        timer = window.setTimeout(() => {
            if (message === last && !force) return;
            last = message;
            region.textContent = '';
            window.requestAnimationFrame(() => {
                region.textContent = message;
            });
        }, delay);
    };
}

async function writeClipboard(text) {
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
            return true;
        }
    } catch {
        /* fall back below */
    }
    // Older browsers: a temporary off-screen textarea (no style attribute: the class positions it).
    const area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.className = 'sr-only';
    document.body.append(area);
    area.select();
    let ok = false;
    try {
        ok = document.execCommand('copy');
    } catch {
        ok = false;
    }
    area.remove();
    return ok;
}

/** "Copy summary": copies summary() and reports the outcome in a toast (success or failure). */
export function bindCopy(root, summary, text) {
    root.addEventListener('click', async (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-action="copy"]') : null;
        if (!button || !root.contains(button)) return;
        const value = summary();
        if (!value) return;
        const ok = await writeClipboard(value);
        toast(ok ? text.copied : text.copyFailed, { tone: ok ? 'success' : 'danger' });
    });
}

/**
 * "Request a Legal Consultation": prefill the wizard (engineering §8) with the service, the tool and the
 * summary. The link's href (?service=…&tool=… #consultation-form) stays the no-JS path and the target of
 * modified clicks (new tab).
 */
export function bindRequest(root, detail) {
    root.addEventListener('click', (event) => {
        const link = event.target instanceof Element ? event.target.closest('[data-action="request"]') : null;
        if (!link || !root.contains(link) || link.getAttribute('aria-disabled') === 'true') return;
        if (event.defaultPrevented || event.button > 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        prefill(detail());
    });
}

/** Debounce to the next animation frame (several input events in one frame → one render). */
export function frameScheduler(callback) {
    let frame = 0;
    return () => {
        if (frame) return;
        frame = window.requestAnimationFrame(() => {
            frame = 0;
            callback();
        });
    };
}
