import { describe, expect, it } from 'vitest';
import { clippedBelow } from '../megamenu.js';

// Design 2.1 mega menu (brief D48; QA round 1): the panel fits 1024 x 768 and larger windows whole. In a shorter
// window its body scrolls, and while part of it lies below the visible area the body fades at its lower edge
// (.is-clipped), so no line is ever cut at the edge without a cue.
describe('megamenu: the fade at the lower edge of a scrolling body', () => {
    it('shows while content lies below the visible part', () => {
        expect(clippedBelow(620, 520, 0)).toBe(true); // a 600 px window: 100 px below
        expect(clippedBelow(620, 520, 50)).toBe(true); // half way
    });

    it('goes once the body is scrolled to its end', () => {
        expect(clippedBelow(620, 520, 100)).toBe(false);
        expect(clippedBelow(620, 520, 99.5)).toBe(false); // sub-pixel scroll positions: 1 px of slack
    });

    it('never shows when the panel fits the window (no inner scrolling)', () => {
        expect(clippedBelow(536, 536, 0)).toBe(false);
        expect(clippedBelow(537, 536, 0)).toBe(false); // rounding
    });
});
