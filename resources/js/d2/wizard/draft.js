/**
 * Consultation wizard: the draft kept in this browser (localStorage key summit:draft:{locale}).
 *
 * Saved: the step and the visitor's answers. NEVER saved: the consent box, the Turnstile token, the
 * idempotency key, the honeypot and an attached document (they are simply not in the allow-list below). A
 * draft older than its lifetime (seven days unless the page says otherwise, see draft-meta.js) is dropped
 * when read, so personal details do not linger on a shared computer. The draft is cleared after a received
 * request, when the visitor deletes it and when they untick "Keep a draft on this device".
 *
 * Pure functions (storage is passed in): unit-tested in draft.test.js.
 */
import { DRAFT_TTL, DRAFT_VERSION, draftKey, isFresh } from './draft-meta.js';
import { isPrefixOnly } from './validate.js';

export { DRAFT_TTL, DRAFT_VERSION, draftKey };

const TEXT = ['audience', 'name', 'matter', 'situation', 'summary', 'deadlineDate', 'deadlineDetails', 'phone', 'email', 'language', 'message', 'messageBase', 'consultant', 'tool'];
const MAX_TEXT = 6000;

const text = (value) => (typeof value === 'string' ? value.slice(0, MAX_TEXT) : '');

function cleanDocs(docs) {
    const out = {};
    if (!docs || typeof docs !== 'object' || Array.isArray(docs)) return out;
    for (const [code, list] of Object.entries(docs)) {
        if (!/^S(0[1-9]|1[01])$/.test(code) || !Array.isArray(list)) continue;
        const indexes = [...new Set(list.filter((i) => Number.isInteger(i) && i >= 0 && i < 20))].sort((a, b) => a - b);
        if (indexes.length) out[code] = indexes;
    }
    return out;
}

/** The storable draft: only allow-listed fields, strings only, plus version and time. */
export function serialise(values = {}, now = Date.now()) {
    const draft = { v: DRAFT_VERSION, t: now };
    for (const key of TEXT) draft[key] = text(values[key]);
    draft.step = Number.isInteger(values.step) && values.step >= 1 && values.step <= 5 ? values.step : 1;
    draft.messageEdited = values.messageEdited === true;
    draft.docs = cleanDocs(values.docs);
    return draft;
}

/** A stored draft, validated and sanitised; null when missing, malformed, another version or expired. */
export function parse(raw, now = Date.now(), ttl = DRAFT_TTL) {
    if (!isFresh(raw, now, ttl)) return null;
    return serialise(raw, raw.t);
}

/** Worth offering to restore: something the visitor typed (a chosen option alone is not). */
export function hasContent(draft) {
    if (!draft) return false;
    const typed = ['name', 'summary', 'email', 'deadlineDate', 'deadlineDetails'].some((key) => (draft[key] || '').trim() !== '');
    const phone = (draft.phone || '').trim();
    return typed || (phone !== '' && !isPrefixOnly(phone)) || (draft.messageEdited && (draft.message || '').trim() !== '');
}

/** Read / write / clear through a storage object with read(key) / write(key, value) / remove(key). */
export function createDraftStore(key, storage, clock = () => Date.now(), ttl = DRAFT_TTL) {
    return {
        read() {
            const draft = parse(storage.read(key), clock(), ttl);
            if (!draft) storage.remove(key);
            return draft;
        },
        write(values) {
            return storage.write(key, serialise(values, clock()));
        },
        clear() {
            storage.remove(key);
        },
    };
}

/** true at most once per `interval` ms (the "Draft saved" toast). */
export function createThrottle(interval, clock = () => Date.now()) {
    let last = -Infinity;
    return () => {
        const now = clock();
        if (now - last < interval) return false;
        last = now;
        return true;
    };
}
