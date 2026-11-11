/**
 * Consultation wizard: what a response means (docs/enquiries.md §2, §4; Design 1 review SEC-07).
 * Switch on the body's `status`, never on the HTTP code alone:
 *
 *   received      status "received" with a reference (201, or 200 for a replayed key)
 *   invalid       status "invalid" with field errors (422): nothing stored
 *   failed        status "failed" (Turnstile, idempotency key, server error), or a response from outside
 *                 the controller that is known not to have stored anything: 419, 413, 503 maintenance,
 *                 an "invalid" without errors, or a request that never left an offline browser
 *   rate_limited  status "rate_limited" (429)
 *   uncertain     anything else: network error, timeout, unknown or unparsable body. The request may
 *                 have been stored, so the visitor is never told it failed (retry reuses the key)
 *
 * Pure function: unit-tested in outcome.test.js.
 */
const OUTSIDE_FAILED = [413, 419, 503];

export function classify({ status = 0, body = null, error = null, offline = false } = {}) {
    if (error) return offline ? 'failed' : 'uncertain';
    const kind = body && typeof body === 'object' && typeof body.status === 'string' ? body.status : null;
    if (kind === 'received') {
        return typeof body.reference === 'string' && body.reference !== '' ? 'received' : 'uncertain';
    }
    if (kind === 'invalid') {
        const errors = body.errors && typeof body.errors === 'object' ? Object.keys(body.errors) : [];
        return errors.length ? 'invalid' : 'failed';
    }
    if (kind === 'failed') return 'failed';
    if (kind === 'rate_limited') return 'rate_limited';
    if (kind === null && OUTSIDE_FAILED.includes(status)) return 'failed';
    return 'uncertain';
}

/** After which outcomes the single-use Turnstile token must be reset (not after received or invalid). */
export const resetsToken = (outcome) => !['received', 'invalid'].includes(outcome);

/** A fresh idempotency key (a UUID). crypto.randomUUID needs a secure context; fall back to v4 by hand. */
export function newKey(cryptoApi = globalThis.crypto) {
    if (cryptoApi && typeof cryptoApi.randomUUID === 'function') {
        try {
            return cryptoApi.randomUUID();
        } catch {
            /* not a secure context: build it below */
        }
    }
    const bytes = new Uint8Array(16);
    if (cryptoApi && typeof cryptoApi.getRandomValues === 'function') cryptoApi.getRandomValues(bytes);
    else for (let i = 0; i < 16; i++) bytes[i] = Math.floor(Math.random() * 256);
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}
