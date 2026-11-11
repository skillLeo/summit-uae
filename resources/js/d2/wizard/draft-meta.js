/**
 * Consultation wizard: what a stored draft looks like from outside (version, lifetime, key) and a cheap
 * freshness test. Kept apart from draft.js so the eager start-up code (wizard/lazy.js) can decide at start-up
 * whether to show the restore prompt, without loading the controller chunk (draft.js and its validation
 * helpers stay in that chunk).
 *
 * The lifetime is the server's: <x-d2.wizard.form> renders it (data-draft-days) together with the copy that
 * tells the visitor how long the draft is kept, so the two never disagree. DRAFT_TTL is the fallback.
 *
 * Pure functions: unit-tested in draft.test.js.
 */
export const DRAFT_VERSION = 1;
export const DAY = 24 * 60 * 60 * 1000;
export const DRAFT_DAYS = 7;
export const DRAFT_TTL = DRAFT_DAYS * DAY;

export const draftKey = (locale) => `summit:draft:${locale || 'en'}`;

/** A lifetime in ms from a number of days (a data attribute); the default for anything that is not 1–90. */
export function ttlFromDays(days) {
    const n = Number(days);
    return Number.isInteger(n) && n >= 1 && n <= 90 ? n * DAY : DRAFT_TTL;
}

/** A stored value parse() can accept: this version, saved within the lifetime and not in the future. */
export function isFresh(raw, now = Date.now(), ttl = DRAFT_TTL) {
    if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return false;
    if (raw.v !== DRAFT_VERSION || typeof raw.t !== 'number' || !Number.isFinite(raw.t)) return false;
    return now - raw.t <= ttl && raw.t - now <= 60_000;
}
