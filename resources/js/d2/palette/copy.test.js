import { describe, expect, it } from 'vitest';
import { fill, formatNumber, plural, pluralCategory, segments } from './copy.js';

describe('fill / segments', () => {
    it('fills Laravel-style placeholders', () => {
        expect(fill('Open now · closes :time UAE time', { time: '18:00' })).toBe('Open now · closes 18:00 UAE time');
        expect(fill('Closed now · opens :day :time UAE time', { day: 'Monday', time: '09:00' })).toBe('Closed now · opens Monday 09:00 UAE time');
    });

    it('leaves unknown placeholders untouched', () => {
        expect(fill('a :missing b', {})).toBe('a :missing b');
    });

    it('marks the filled parts', () => {
        expect(segments('No results for “:query”', { query: 'cheque' })).toEqual([
            { text: 'No results for “' },
            { text: 'cheque', name: 'query' },
            { text: '”' },
        ]);
    });

    it('treats a value as plain text (no nested placeholders)', () => {
        expect(fill(':query', { query: ':time' })).toBe(':time');
    });
});

describe('plural', () => {
    const en = { one: ':count result', other: ':count results' };
    const ru = { one: ':count результат', few: ':count результата', many: ':count результатов' };
    const ar = { zero: 'zero', one: 'one', two: 'two', few: 'few', many: 'many', other: 'other' };

    it('English one / other', () => {
        expect(plural(en, 1, 'en')).toBe(':count result');
        expect(plural(en, 0, 'en')).toBe(':count results');
        expect(plural(en, 7, 'en')).toBe(':count results');
    });

    it('Russian one / few / many', () => {
        expect(plural(ru, 1, 'ru')).toBe(':count результат');
        expect(plural(ru, 21, 'ru')).toBe(':count результат');
        expect(plural(ru, 3, 'ru')).toBe(':count результата');
        expect(plural(ru, 11, 'ru')).toBe(':count результатов');
        expect(plural(ru, 25, 'ru')).toBe(':count результатов');
    });

    it('Arabic six forms', () => {
        expect([0, 1, 2, 3, 10, 11, 99, 100].map((n) => plural(ar, n, 'ar'))).toEqual(['zero', 'one', 'two', 'few', 'few', 'many', 'many', 'other']);
    });

    it('falls back to other, then many', () => {
        expect(plural({ many: 'm' }, 1.5, 'ru')).toBe('m');
        expect(plural('fixed', 3, 'en')).toBe('fixed');
        expect(plural(null, 3, 'en')).toBe('');
    });

    it('categories match Laravel trans_choice for whole numbers', () => {
        expect(pluralCategory(5, 'ru')).toBe('many');
        expect(pluralCategory(0, 'ar')).toBe('zero');
    });
});

describe('formatNumber', () => {
    it('uses Western digits in every language', () => {
        expect(formatNumber(1.5, 'ar')).toBe('1.5');
        expect(formatNumber(12, 'ar')).toBe('12');
        expect(formatNumber(1.5, 'ru')).toBe('1,5');
        expect(formatNumber(1.75, 'en')).toBe('1.75');
    });
});
