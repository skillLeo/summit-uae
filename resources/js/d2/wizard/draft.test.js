import { describe, expect, it } from 'vitest';
import { DRAFT_TTL, createDraftStore, createThrottle, draftKey, hasContent, parse, serialise } from './draft.js';
import { DAY, DRAFT_DAYS, isFresh, ttlFromDays } from './draft-meta.js';

function memoryStorage() {
    const data = new Map();
    return {
        data,
        read: (key) => (data.has(key) ? JSON.parse(data.get(key)) : null),
        write: (key, value) => {
            data.set(key, JSON.stringify(value));
            return true;
        },
        remove: (key) => data.delete(key),
    };
}

describe('draft', () => {
    it('uses the per-locale key summit:draft:{locale}', () => {
        expect(draftKey('ar')).toBe('summit:draft:ar');
        expect(draftKey('')).toBe('summit:draft:en');
    });

    it('never stores the consent box, the Turnstile token, the idempotency key or the honeypot', () => {
        const draft = serialise({
            name: 'A. Example',
            enquiry_consent: '1',
            consent: true,
            'cf-turnstile-response': 'token',
            token: 'token',
            idempotency_key: '3f2b8c1e-0000-4000-8000-000000000000',
            website: 'spam',
            step: 3,
        });
        const text = JSON.stringify(draft);
        for (const banned of ['enquiry_consent', 'consent"', 'turnstile', 'token', 'idempotency', 'website', 'spam']) {
            expect(text).not.toContain(banned);
        }
        expect(draft.name).toBe('A. Example');
        expect(draft.step).toBe(3);
    });

    it('keeps only strings, a valid step and valid document ticks', () => {
        const draft = serialise({ name: ['x'], step: 9, docs: { S06: [0, 2, 2, -1, 'x', 50], S99: [1], hack: 1 } });
        expect(draft.name).toBe('');
        expect(draft.step).toBe(1);
        expect(draft.docs).toEqual({ S06: [0, 2] });
    });

    it('drops malformed, foreign-version and expired drafts', () => {
        const now = 1_800_000_000_000;
        expect(parse(null, now)).toBeNull();
        expect(parse([], now)).toBeNull();
        expect(parse({ v: 2, t: now }, now)).toBeNull();
        expect(parse({ v: 1, t: now - DRAFT_TTL - 1 }, now)).toBeNull();
        expect(parse({ v: 1, t: now + 3_600_000 }, now)).toBeNull();
        expect(parse({ v: 1, t: now - 1000, name: 'A' }, now)?.name).toBe('A');
    });

    it('offers a restore only for something the visitor typed', () => {
        expect(hasContent(null)).toBe(false);
        expect(hasContent(serialise({ matter: 'S06', audience: 'individual', phone: '+971 ' }))).toBe(false);
        expect(hasContent(serialise({ phone: '+971 50 000 0000' }))).toBe(true);
        expect(hasContent(serialise({ summary: 'Text' }))).toBe(true);
        expect(hasContent(serialise({ message: 'Edited', messageEdited: true }))).toBe(true);
        expect(hasContent(serialise({ message: 'Composed only' }))).toBe(false);
    });

    it('store: write, read, clear, and an expired draft is removed when read', () => {
        const storage = memoryStorage();
        let now = 1_800_000_000_000;
        const store = createDraftStore('summit:draft:en', storage, () => now);
        store.write({ name: 'A. Example', step: 2 });
        expect(store.read()).toMatchObject({ name: 'A. Example', step: 2, v: 1 });
        now += DRAFT_TTL + 1;
        expect(store.read()).toBeNull();
        expect(storage.data.has('summit:draft:en')).toBe(false);
        store.write({ name: 'B' });
        store.clear();
        expect(store.read()).toBeNull();
    });

    it('store: honours the lifetime the page gives (data-draft-days)', () => {
        const storage = memoryStorage();
        let now = 1_800_000_000_000;
        const store = createDraftStore('summit:draft:ru', storage, () => now, ttlFromDays(2));
        store.write({ name: 'A. Example' });
        now += 2 * DAY - 1;
        expect(store.read()?.name).toBe('A. Example');
        now += 2;
        expect(store.read()).toBeNull();
    });

    it('meta: the start-up freshness check agrees with parse() and the lifetime falls back to 7 days', () => {
        const now = 1_800_000_000_000;
        expect(DRAFT_DAYS).toBe(7);
        expect(DRAFT_TTL).toBe(7 * DAY);
        expect(ttlFromDays('7')).toBe(7 * DAY);
        expect(ttlFromDays(30)).toBe(30 * DAY);
        for (const bad of [undefined, '', '0', '-1', '1.5', '91', 'x', null]) expect(ttlFromDays(bad)).toBe(DRAFT_TTL);
        for (const raw of [null, [], 'x', { v: 2, t: now }, { v: 1, t: 'now' }, { v: 1, t: now - DRAFT_TTL - 1 }, { v: 1, t: now + 3_600_000 }]) {
            expect(isFresh(raw, now)).toBe(false);
            expect(parse(raw, now)).toBeNull();
        }
        expect(isFresh({ v: 1, t: now - DRAFT_TTL }, now)).toBe(true);
        expect(isFresh({ v: 1, t: now + 30_000 }, now)).toBe(true);
        expect(isFresh({ v: 1, t: now - 3 * DAY }, now, ttlFromDays(2))).toBe(false);
    });

    it('throttles the "Draft saved" toast', () => {
        let now = 0;
        const should = createThrottle(30_000, () => now);
        expect(should()).toBe(true);
        now = 10_000;
        expect(should()).toBe(false);
        now = 30_000;
        expect(should()).toBe(true);
    });
});
