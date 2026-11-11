import { describe, expect, it } from 'vitest';
import { QUERY_MAX, openAction, paletteQuery } from './query.js';

// d2:palette-open detail.query (docs/d2/engineering.md §4a): untrusted text from any caller on the page.
describe('paletteQuery', () => {
    it('keeps a plain query as typed', () => {
        expect(paletteQuery('tenancy deposit')).toBe('tenancy deposit');
        expect(paletteQuery('شيك مرتجع')).toBe('شيك مرتجع');
        expect(paletteQuery('Трудовой договор')).toBe('Трудовой договор');
    });

    it('trims the ends and collapses whitespace, tabs and line breaks', () => {
        expect(paletteQuery('  unpaid \n\n salary\t ')).toBe('unpaid salary');
        expect(paletteQuery('a  b')).toBe('a b');
    });

    it('turns control and bidi formatting characters into spaces', () => {
        expect(paletteQuery('cheque\u0000case')).toBe('cheque case');
        expect(paletteQuery('‮esac‬')).toBe('esac');
        expect(paletteQuery('⁧شيك⁩ USDT')).toBe('شيك USDT');
        expect(paletteQuery('a\u0085b\u009Fc')).toBe('a b c');
    });

    it('keeps joiners that belong to the text', () => {
        expect(paletteQuery('می‌خواهم')).toBe('می‌خواهم');
    });

    it('ignores anything that is not a string, and blank strings', () => {
        for (const value of [undefined, null, 42, true, ['cheque'], { query: 'cheque' }, () => 'cheque', '', '   ', '\u0000\n']) {
            expect(paletteQuery(value)).toBe('');
        }
    });

    it("cuts the text to the field's maxlength without splitting a character", () => {
        expect(QUERY_MAX).toBe(120);
        expect(paletteQuery('x'.repeat(300))).toHaveLength(120);
        expect(paletteQuery('abc def', 5)).toBe('abc d');
        expect(paletteQuery('abc def', 4)).toBe('abc');
        expect(paletteQuery('ab😀cd', 3)).toBe('ab');
        expect(paletteQuery('ab😀cd', 4)).toBe('ab😀');
        expect(paletteQuery('abcdef', 0)).toBe('abcdef');
        expect(paletteQuery('abcdef', -1)).toBe('abcdef');
    });
});

describe('openAction', () => {
    it('opens a closed palette, with or without a query', () => {
        expect(openAction({ isOpen: false, source: 'click' })).toBe('open');
        expect(openAction({ isOpen: false, source: 'shortcut-k' })).toBe('open');
        expect(openAction({ isOpen: false, source: 'click', query: 'cheque' })).toBe('open-query');
        expect(openAction()).toBe('open');
    });

    it('toggles an open palette closed on Ctrl/Cmd+K and focuses it otherwise', () => {
        expect(openAction({ isOpen: true, source: 'shortcut-k' })).toBe('close');
        expect(openAction({ isOpen: true, source: 'shortcut-slash' })).toBe('focus');
        expect(openAction({ isOpen: true, source: 'click' })).toBe('focus');
        expect(openAction({ isOpen: true })).toBe('focus');
    });

    it('never closes the palette when a query arrives', () => {
        expect(openAction({ isOpen: true, source: 'shortcut-k', query: 'cheque' })).toBe('query');
        expect(openAction({ isOpen: true, source: 'click', query: 'cheque' })).toBe('query');
    });
});
