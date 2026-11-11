import { describe, expect, it } from 'vitest';
import { normalise } from '../lib/normalise.js';
import {
    STORAGE_KEY,
    applyFilter,
    clearTicks,
    isolateParts,
    parseRef,
    readTicks,
    readyCount,
    refFromHash,
    requestUrl,
    rovingIndex,
    setTick,
    shareUrl,
    tokenize,
} from './logic.js';

describe('situation references and the deep link', () => {
    it('keeps the Design 1 storage key', () => {
        expect(STORAGE_KEY).toBe('summit.navigator.v1');
    });

    it('parses S01-1 … S11-9 and nothing else', () => {
        expect(parseRef('S05-2')).toEqual({ service: 'S05', index: 2 });
        expect(parseRef('S11-4')).toEqual({ service: 'S11', index: 4 });
        for (const bad of ['S00-1', 'S12-1', 'S05-0', 'S05-10', 's05-2', 'S5-2', ' S05-2', 'S05-2 ', '', null, undefined, ['S05-2'], 52]) {
            expect(parseRef(bad)).toBeNull();
        }
    });

    it('reads only an exact #navigator=S05-2 hash', () => {
        expect(refFromHash('#navigator=S05-2')).toBe('S05-2');
        expect(refFromHash('#navigator=S01-4')).toBe('S01-4');
        for (const bad of ['#navigator', '#navigator=', '#navigator=S12-1', '#navigator=S05-2&x=1', '#navigator=S05-2/', 'navigator=S05-2', '#faq-01', '', null]) {
            expect(refFromHash(bad)).toBeNull();
        }
    });

    it('builds the share link from the page without its query', () => {
        const location = { origin: 'https://www.summitlegaluae.com', pathname: '/ar/', search: '?navigator=S01-1' };
        expect(shareUrl(location, 'S05-2')).toBe('https://www.summitlegaluae.com/ar/#navigator=S05-2');
    });

    it('builds the no-JavaScript request link like Prefill::url()', () => {
        const base = 'https://www.summitlegaluae.com/en/#consultation-form';
        expect(requestUrl(base, 'S05', 'S05-2')).toBe('https://www.summitlegaluae.com/en/?service=S05&situation=S05-2#consultation-form');
        expect(requestUrl(base, 'S05', null)).toBe('https://www.summitlegaluae.com/en/?service=S05#consultation-form');
        expect(requestUrl('https://x.test/ru/?service=S01#consultation-form', 'S06', 'S06-1')).toBe('https://x.test/ru/?service=S06&situation=S06-1#consultation-form');
        expect(requestUrl(base, 'S99', '<x>')).toBe('https://www.summitlegaluae.com/en/#consultation-form');
        expect(requestUrl('not a url', 'S05', 'S05-2')).toBe('not a url');
    });
});

describe('filter', () => {
    const items = [
        { ref: 'S01-1', code: 'S01', text: normalise('A cheque was returned unpaid Debt Recovery and Cheque Cases') },
        { ref: 'S01-2', code: 'S01', text: normalise('A company owes you money Debt Recovery and Cheque Cases') },
        { ref: 'S05-2', code: 'S05', text: normalise('Ёлка defect after purchase Property and Tenancy Disputes') },
        { ref: 'S08-1', code: 'S08', text: normalise('أَحْكَامُ الوصيّة قانون الأسرة والوصايا') },
    ];

    it('shows everything without a query or a service', () => {
        const result = applyFilter(items);
        expect(result.count).toBe(4);
        expect(result.filtered).toBe(false);
        expect(result.groups.get('S01')).toBe(2);
    });

    it('needs every word of the query (any order, any case, accents ignored)', () => {
        const t = (q) => tokenize(q, normalise);
        expect([...applyFilter(items, { tokens: t('CHEQUE') }).visible]).toEqual(['S01-1', 'S01-2']);
        expect([...applyFilter(items, { tokens: t('unpaid   cheque') }).visible]).toEqual(['S01-1']);
        expect([...applyFilter(items, { tokens: t('ёлка') }).visible]).toEqual(['S05-2']);
        expect([...applyFilter(items, { tokens: t('елка') }).visible]).toEqual(['S05-2']);
        expect([...applyFilter(items, { tokens: t('احكام') }).visible]).toEqual(['S08-1']);
        expect([...applyFilter(items, { tokens: t('الوصية') }).visible]).toEqual(['S08-1']);
        expect(applyFilter(items, { tokens: t('gratuity') }).count).toBe(0);
    });

    it('combines the service chip with the query and counts per group', () => {
        const result = applyFilter(items, { tokens: tokenize('cheque', normalise), service: 'S05' });
        expect(result.count).toBe(0);
        expect(result.filtered).toBe(true);
        const s01 = applyFilter(items, { service: 'S01' });
        expect([...s01.visible]).toEqual(['S01-1', 'S01-2']);
        expect(s01.groups.get('S05')).toBe(0);
        expect(s01.groups.get('S01')).toBe(2);
    });

    it('ignores empty and non-string queries', () => {
        expect(tokenize('   ', normalise)).toEqual([]);
        expect(tokenize(null, normalise)).toEqual([]);
    });
});

describe('checklist ticks (same value shape as Design 1)', () => {
    it('sets a tick without mutating the previous state', () => {
        const before = {};
        const after = setTick(before, 'S05', 2, true);
        expect(before).toEqual({});
        expect(after).toEqual({ S05: [null, null, true] });
        expect(JSON.stringify(after)).toBe('{"S05":[null,null,true]}');
        expect(setTick(after, 'S05', 0, false)).toEqual({ S05: [false, null, true] });
    });

    it('reads what Design 1 wrote (sparse arrays become null)', () => {
        const design1 = JSON.parse(JSON.stringify({ S05: [true, , false, true], S06: [, true] })); // eslint-disable-line no-sparse-arrays
        expect(readTicks(design1)).toEqual({ S05: [true, null, false, true], S06: [null, true] });
    });

    it('drops unknown codes, wrong shapes and non-boolean values', () => {
        expect(readTicks({ S99: [true], S01: 'yes', S02: [1, 'x', true], __proto__: { S03: [true] } })).toEqual({ S02: [null, null, true] });
        expect(readTicks(null)).toEqual({});
        expect(readTicks([true])).toEqual({});
        expect(readTicks('{"S05":[true]}')).toEqual({});
    });

    it('ignores invalid indices and codes', () => {
        const ticks = { S05: [true] };
        expect(setTick(ticks, 'S05', -1, true)).toBe(ticks);
        expect(setTick(ticks, 'S05', 1.5, true)).toBe(ticks);
        expect(setTick(ticks, 'S12', 0, true)).toBe(ticks);
    });

    it('counts ready documents and clears one service', () => {
        const ticks = { S05: [true, null, true, false, true], S06: [true] };
        expect(readyCount(ticks, 'S05', 4)).toBe(2);
        expect(readyCount(ticks, 'S05', 5)).toBe(3);
        expect(readyCount(ticks, 'S01', 4)).toBe(0);
        expect(clearTicks(ticks, 'S05')).toEqual({ S06: [true] });
        expect(ticks.S05).toBeDefined();
    });
});

describe('helpers', () => {
    it('splits FSI … PDI runs for <bdi>', () => {
        expect(isolateParts('⁨2⁩ من ⁨4⁩ مستندات جاهزة')).toEqual([
            { text: '2', isolate: true },
            { text: ' من ', isolate: false },
            { text: '4', isolate: true },
            { text: ' مستندات جاهزة', isolate: false },
        ]);
        expect(isolateParts('plain')).toEqual([{ text: 'plain', isolate: false }]);
        expect(isolateParts(null)).toEqual([]);
    });

    it('moves along the chip toolbar, mirrored in RTL', () => {
        expect(rovingIndex('ArrowRight', 0, 12)).toBe(1);
        expect(rovingIndex('ArrowLeft', 0, 12)).toBe(11);
        expect(rovingIndex('ArrowLeft', 0, 12, true)).toBe(1);
        expect(rovingIndex('ArrowRight', 0, 12, true)).toBe(11);
        expect(rovingIndex('End', 3, 12)).toBe(11);
        expect(rovingIndex('Home', 3, 12)).toBe(0);
        expect(rovingIndex('Enter', 3, 12)).toBe(-1);
    });
});
