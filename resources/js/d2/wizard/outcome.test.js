import { describe, expect, it } from 'vitest';
import { classify, newKey, resetsToken } from './outcome.js';

describe('classify (docs/enquiries.md §2)', () => {
    it('received only with status "received" and a reference', () => {
        expect(classify({ status: 201, body: { status: 'received', reference: 'SLC-261110-7KQ2MX' } })).toBe('received');
        expect(classify({ status: 200, body: { status: 'received', reference: 'SLC-261110-7KQ2MX' } })).toBe('received');
        expect(classify({ status: 201, body: { status: 'received' } })).toBe('uncertain');
    });

    it('invalid needs field errors; without them nothing was stored: failed', () => {
        expect(classify({ status: 422, body: { status: 'invalid', errors: { name: ['x'] } } })).toBe('invalid');
        expect(classify({ status: 422, body: { status: 'invalid', errors: {} } })).toBe('failed');
    });

    it('failed and rate_limited follow the body status, whatever the code', () => {
        expect(classify({ status: 422, body: { status: 'failed' } })).toBe('failed');
        expect(classify({ status: 500, body: { status: 'failed' } })).toBe('failed');
        expect(classify({ status: 503, body: { status: 'failed' } })).toBe('failed');
        expect(classify({ status: 429, body: { status: 'rate_limited' } })).toBe('rate_limited');
    });

    it('responses from outside the controller that stored nothing are failed', () => {
        for (const status of [419, 413, 503]) expect(classify({ status, body: null })).toBe('failed');
    });

    it('anything else is uncertain: timeout, network error, unknown body, 500 without status', () => {
        expect(classify({ error: new Error('AbortError') })).toBe('uncertain');
        expect(classify({ status: 0, error: new TypeError('Failed to fetch') })).toBe('uncertain');
        expect(classify({ status: 200, body: { ok: true } })).toBe('uncertain');
        expect(classify({ status: 500, body: null })).toBe('uncertain');
        expect(classify({ status: 302, body: null })).toBe('uncertain');
    });

    it('a request that never left an offline browser failed', () => {
        expect(classify({ error: new TypeError('Failed to fetch'), offline: true })).toBe('failed');
    });

    it('the single-use Turnstile token is reset after every outcome but received and invalid', () => {
        expect(resetsToken('received')).toBe(false);
        expect(resetsToken('invalid')).toBe(false);
        for (const outcome of ['failed', 'rate_limited', 'uncertain']) expect(resetsToken(outcome)).toBe(true);
    });
});

describe('newKey', () => {
    const uuid = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;

    it('uses crypto.randomUUID when available', () => {
        expect(newKey({ randomUUID: () => '3f2b8c1e-0000-4000-8000-000000000000' })).toBe('3f2b8c1e-0000-4000-8000-000000000000');
    });

    it('builds a v4 UUID by hand outside a secure context', () => {
        const fake = { getRandomValues: (bytes) => bytes.fill(171) };
        expect(newKey(fake)).toMatch(uuid);
        expect(newKey({})).toMatch(uuid);
        expect(newKey(globalThis.crypto)).toMatch(uuid);
    });
});
