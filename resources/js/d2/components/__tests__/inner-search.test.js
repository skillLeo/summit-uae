// The 404 search field (components/inner-search.js): the words handed to the command palette in
// d2:palette-open's detail.query. Lives outside components/*.js on purpose: app.js eagerly imports every
// components/*.js file.
import { describe, expect, it } from 'vitest';
import { paletteQuery } from '../inner-search.js';

describe('paletteQuery', () => {
    it('keeps the words on one line with single spaces', () => {
        expect(paletteQuery('  unpaid   salary \n')).toBe('unpaid salary');
        expect(paletteQuery('الراتب\tالمتأخر')).toBe('الراتب المتأخر');
        expect(paletteQuery('зарплата')).toBe('зарплата');
    });

    it('gives an empty query for an empty field or anything that is not a string', () => {
        expect(paletteQuery('')).toBe('');
        expect(paletteQuery('   ')).toBe('');
        expect(paletteQuery(null)).toBe('');
        expect(paletteQuery(undefined)).toBe('');
        expect(paletteQuery(['cheque'])).toBe('');
        expect(paletteQuery({ q: 'cheque' })).toBe('');
    });

    it('caps very long input without a trailing space', () => {
        const long = `${'word '.repeat(80)}end`;
        const query = paletteQuery(long);
        expect(query.length).toBeLessThanOrEqual(200);
        expect(query.endsWith(' ')).toBe(false);
        expect(paletteQuery('abc def', 4)).toBe('abc');
    });
});
