import { describe, expect, it } from 'vitest';
import { activeIndex, listScroll } from '../toc.js';
import { progressOf } from '../reading-progress.js';

// Lives outside modules/*.js on purpose: app.js eagerly imports every modules/*.js file.
// Pure parts of the inner-page kit modules (toc scroll-spy, reading progress). Design 2.1 removed the large-title
// collapse (brief §3.6 rules 7 and 8: a scroll-linked animation and a bar title cut with an ellipsis).

describe('toc scroll-spy: activeIndex', () => {
    const tops = [300, 900, 1500, 2100]; // section tops relative to the viewport

    it('has no current section before the first one reaches the reading line', () => {
        expect(activeIndex(tops, 250)).toBe(-1);
    });

    it('marks the last section whose top passed the reading line', () => {
        expect(activeIndex(tops, 300)).toBe(0);
        expect(activeIndex(tops, 1000)).toBe(1);
        expect(activeIndex([-2000, -900, 120, 800], 270)).toBe(2);
    });

    it('allows one pixel of rounding at the line', () => {
        expect(activeIndex([271], 270)).toBe(0);
        expect(activeIndex([272], 270)).toBe(-1);
    });

    it('at the end of the page the last section in view wins', () => {
        // short last sections never reach the line: at the bottom, the last one whose top is in the window
        expect(activeIndex([-1200, -300, 400, 700], 270, { atEnd: true, viewport: 900 })).toBe(3);
        expect(activeIndex([-1200, -300, 400, 950], 270, { atEnd: true, viewport: 900 })).toBe(2);
    });

    it('handles an empty list', () => {
        expect(activeIndex([], 100)).toBe(-1);
    });
});

describe('toc side list: listScroll', () => {
    // eleven 44 px entries in a 400 px list (scrollHeight 484)
    const tops = Array.from({ length: 11 }, (_, i) => i * 44);
    const bottoms = tops.map((t) => t + 44);
    const opts = (scrollTop) => ({ scrollTop, viewport: 400, max: 84 });

    it('returns to the top while nothing or the first section is current', () => {
        expect(listScroll(-1, tops, bottoms, opts(60))).toBe(0);
        expect(listScroll(0, tops, bottoms, opts(60))).toBe(0);
        expect(listScroll(null, tops, bottoms, opts(60))).toBe(0);
    });

    it('scrolls just enough to show the current entry, never past the end', () => {
        expect(listScroll(9, tops, bottoms, opts(0))).toBe(40); // bottom 440 - 400
        expect(listScroll(10, tops, bottoms, opts(0))).toBe(84);
        expect(listScroll(4, tops, bottoms, opts(0))).toBe(0); // already in view
    });

    it('keeps the entry before the current one in view when scrolling back up', () => {
        expect(listScroll(2, tops, bottoms, opts(84))).toBe(44); // the previous entry's top
        expect(listScroll(1, tops, bottoms, opts(84))).toBe(0);
    });

    it('the current entry wins when it and the previous one do not fit together', () => {
        expect(listScroll(1, [0, 44], [44, 444], { scrollTop: 0, viewport: 300, max: 200 })).toBe(44);
    });
});

describe('reading progress: progressOf', () => {
    // header bottom 72, window 900 → 828 px of reading area
    it('is 0 until the article top reaches the header', () => {
        expect(progressOf(400, 3000, 72, 900)).toBe(0);
        expect(progressOf(72, 3000, 72, 900)).toBe(0);
    });

    it('grows linearly and reaches 1 when the article end reaches the window bottom', () => {
        const distance = 3000 - 828;
        expect(progressOf(72 - distance / 2, 3000, 72, 900)).toBeCloseTo(0.5, 5);
        expect(progressOf(72 - distance, 3000, 72, 900)).toBe(1);
        expect(progressOf(-5000, 3000, 72, 900)).toBe(1);
    });

    it('a short article is complete once its top passes the header', () => {
        expect(progressOf(100, 400, 72, 900)).toBe(0);
        expect(progressOf(60, 400, 72, 900)).toBe(1);
    });

    it('never divides by zero', () => {
        expect(Number.isFinite(progressOf(0, 100, 900, 900))).toBe(true);
    });

    it('a target without a box (display: none) shows no progress', () => {
        expect(progressOf(0, 0, 72, 900)).toBe(0);
        expect(progressOf(-400, 0, 72, 900)).toBe(0);
    });
});
