/**
 * Matter Navigator: pure logic (no DOM), shared by navigator/controller.js and its Vitest tests.
 * Keeps Design 1's contracts (resources/js/components/navigator.js):
 *   - a situation reference is "S05-2" (service code + 1-based situation index);
 *   - the deep link is #navigator=S05-2;
 *   - the checklist ticks live in localStorage under summit.navigator.v1 with the same value shape,
 *     { "S05": [true, null, false, true] } (one array per service, indexed by document, holes = null).
 * Untrusted input (the URL hash, stored JSON) is validated as plain strings and known shapes only.
 */

export const STORAGE_KEY = 'summit.navigator.v1';

const REF = /^(S(?:0[1-9]|1[01]))-([1-9])$/;
const HASH = /^#navigator=(S(?:0[1-9]|1[01])-[1-9])$/;
const SERVICE = /^S(?:0[1-9]|1[01])$/;
// Unicode isolates around numbers in server-built strings: FIRST STRONG ISOLATE … POP DIRECTIONAL ISOLATE.
const FSI = '⁨';
const PDI = '⁩';

/** "S05-2" → { service: 'S05', index: 2 }; anything else → null. */
export function parseRef(ref) {
    if (typeof ref !== 'string') return null;
    const match = REF.exec(ref);
    return match ? { service: match[1], index: Number(match[2]) } : null;
}

/** The situation reference of a #navigator=S05-2 hash, or null. */
export function refFromHash(hash) {
    if (typeof hash !== 'string') return null;
    const match = HASH.exec(hash);
    return match ? match[1] : null;
}

/** Query words for the filter: normalised (lib/normalise.js), split on white space. */
export function tokenize(query, normalise) {
    if (typeof query !== 'string' || !query.trim()) return [];
    return normalise(query).split(/\s+/u).filter(Boolean);
}

/**
 * Which situations stay visible. items: [{ ref, code, text }] with `text` already normalised (the
 * situation, its service title and the short practice-area name); every query word must occur in it.
 * service: a service code ('' = all). Returns { visible: Set<ref>, groups: Map<code, count>, count, filtered }.
 */
export function applyFilter(items, { tokens = [], service = '' } = {}) {
    const visible = new Set();
    const groups = new Map();
    for (const item of items) {
        const inService = !service || item.code === service;
        const show = inService && tokens.every((word) => item.text.includes(word));
        if (!groups.has(item.code)) groups.set(item.code, 0);
        if (show) {
            visible.add(item.ref);
            groups.set(item.code, groups.get(item.code) + 1);
        }
    }
    return { visible, groups, count: visible.size, filtered: tokens.length > 0 || !!service };
}

/** Stored ticks, validated: only known service codes, only arrays of true / false / null. */
export function readTicks(raw) {
    const out = {};
    if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return out;
    for (const [code, list] of Object.entries(raw)) {
        if (!SERVICE.test(code) || !Array.isArray(list) || list.length > 32) continue;
        out[code] = list.map((value) => (value === true ? true : value === false ? false : null));
    }
    return out;
}

/** A new ticks object with document `index` of `code` set (Design 1: list[i] = checked). */
export function setTick(ticks, code, index, checked) {
    if (!SERVICE.test(code) || !Number.isInteger(index) || index < 0 || index > 31) return ticks;
    const list = Array.isArray(ticks[code]) ? ticks[code].slice() : [];
    while (list.length < index) list.push(null);
    list[index] = !!checked;
    return { ...ticks, [code]: list };
}

/** A new ticks object without `code` (the "Clear ticks" button). */
export function clearTicks(ticks, code) {
    const next = { ...ticks };
    delete next[code];
    return next;
}

/** How many of the first `total` documents of `code` are ticked. */
export function readyCount(ticks, code, total) {
    const list = Array.isArray(ticks[code]) ? ticks[code] : [];
    let ready = 0;
    for (let i = 0; i < total; i += 1) if (list[i] === true) ready += 1;
    return ready;
}

/** The link "Copy link" puts on the clipboard: this page without its query, plus #navigator=S05-2. */
export function shareUrl(location, ref) {
    return `${location.origin}${location.pathname}#navigator=${ref}`;
}

/**
 * The no-JavaScript destination of "Request a Legal Consultation" (App\Design\D2\Prefill::url()):
 * the consultation URL with ?service=S05&situation=S05-2 and #consultation-form.
 */
export function requestUrl(base, code, ref) {
    let url;
    try {
        url = new URL(base);
    } catch {
        return base;
    }
    url.search = '';
    if (SERVICE.test(code)) url.searchParams.set('service', code);
    if (parseRef(ref)) url.searchParams.set('situation', ref);
    url.hash = 'consultation-form';
    return url.toString();
}

/**
 * Splits a server-built string whose numbers are wrapped in FSI … PDI into parts, so the caller can
 * render each isolated run as <bdi> (Arabic sentences keep their direction; the numbers stay whole).
 */
export function isolateParts(text) {
    const parts = [];
    let buffer = '';
    let isolated = false;
    for (const char of String(text ?? '')) {
        if (char === FSI || char === PDI) {
            if (buffer) parts.push({ text: buffer, isolate: isolated });
            buffer = '';
            isolated = char === FSI;
        } else {
            buffer += char;
        }
    }
    if (buffer) parts.push({ text: buffer, isolate: isolated });
    return parts;
}

/** Arrow keys move along the chip toolbar; in RTL "right" points back to the start. */
export function rovingIndex(key, index, count, rtl = false) {
    if (count <= 0) return -1;
    const forward = rtl ? 'ArrowLeft' : 'ArrowRight';
    const back = rtl ? 'ArrowRight' : 'ArrowLeft';
    if (key === forward) return (index + 1) % count;
    if (key === back) return (index - 1 + count) % count;
    if (key === 'Home') return 0;
    if (key === 'End') return count - 1;
    return -1;
}
