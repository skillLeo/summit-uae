import { describe, expect, it } from 'vitest';
import { nextOffset } from './sticky.js';

// D29: the starting file is sticky 96 px from the top; a file taller than the screen moves with the scroll so its end
// and its start can both be read.
describe('nextOffset (the sticky offset of the starting file)', () => {
    const tall = { base: 96, height: 1500, viewport: 900, gap: 24 }; // lowest offset: 900 - 1500 - 24 = -624

    it('keeps 96 px for a file that fits the screen, whatever the scroll', () => {
        const short = { base: 96, height: 400, viewport: 900, gap: 24 };
        expect(nextOffset(96, 300, short)).toBe(96);
        expect(nextOffset(96, -300, short)).toBe(96);
        // just fits: 96 + 780 + 24 = 900
        expect(nextOffset(96, 500, { ...short, height: 780 })).toBe(96);
    });

    it('carries a tall file up with the page while scrolling down, until its end is 24 px above the screen edge', () => {
        expect(nextOffset(96, 100, tall)).toBe(-4);
        expect(nextOffset(-4, 400, tall)).toBe(-404);
        expect(nextOffset(-404, 400, tall)).toBe(-624);
        expect(nextOffset(-624, 400, tall)).toBe(-624);
    });

    it('brings its start back while scrolling up, and stops at 96 px', () => {
        expect(nextOffset(-624, -200, tall)).toBe(-424);
        expect(nextOffset(-424, -1000, tall)).toBe(96);
    });

    it('clamps an offset left out of range by a file that grew or shrank (no scroll)', () => {
        expect(nextOffset(-624, 0, { ...tall, height: 1000 })).toBe(-124);
        expect(nextOffset(-624, 0, { ...tall, height: 300 })).toBe(96);
    });
});
