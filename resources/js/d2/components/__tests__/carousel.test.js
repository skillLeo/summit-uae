import { describe, expect, it } from 'vitest';
import { cut, dotLabel, keyTarget, nearestIndex, snapDelta } from '../carousel.js';

// <x-d2.ui.carousel> helpers (upgrade spec §8 carousels: dots, RTL aware, keyboard operable).
describe('carousel', () => {
    it('picks the slide nearest the snap line (ties keep the first)', () => {
        expect(nearestIndex([])).toBe(-1);
        expect(nearestIndex([0, 300, 600])).toBe(0);
        expect(nearestIndex([290, 2, 310])).toBe(1);
        expect(nearestIndex([5, 5])).toBe(0);
        expect(nearestIndex([10, 9.8])).toBe(0); // within half a pixel: no flicker between two dots
    });

    it('moves in the reading direction: ArrowRight in LTR, ArrowLeft in RTL', () => {
        expect(keyTarget('ArrowRight', 0, 6, false)).toBe(1);
        expect(keyTarget('ArrowLeft', 0, 6, false)).toBe(0);
        expect(keyTarget('ArrowLeft', 2, 6, true)).toBe(3);
        expect(keyTarget('ArrowRight', 2, 6, true)).toBe(1);
        expect(keyTarget('ArrowRight', 5, 6, false)).toBe(5); // no wrap, no autoplay loop
        expect(keyTarget('Home', 4, 6)).toBe(0);
        expect(keyTarget('End', 1, 6)).toBe(5);
        expect(keyTarget('Enter', 1, 6)).toBeNull();
        expect(keyTarget('ArrowRight', 0, 0)).toBeNull();
    });

    it('names each dot from the proposed template in every language', () => {
        expect(dotLabel('Card :n of :total', 2, 9)).toBe('Card 2 of 9');
        expect(dotLabel('البطاقة :n من :total', 1, 6)).toBe('البطاقة 1 من 6');
        expect(dotLabel('Карточка :n из :total', 11, 11)).toBe('Карточка 11 из 11');
        expect(dotLabel(undefined, 1, 2)).toBe('');
    });

    it('measures the scroll to a slide from either edge (scrollBy delta)', () => {
        const track = { left: 0, right: 390 };
        // LTR: the slide's left edge to the track's left edge + scroll padding.
        expect(snapDelta({ left: 334, right: 624 }, track, 24, false)).toBe(310);
        expect(snapDelta({ left: 24, right: 314 }, track, 24, false)).toBe(0);
        // RTL: the slide's right edge to the track's right edge − scroll padding (negative = towards the end).
        expect(snapDelta({ left: -234, right: 56 }, track, 24, true)).toBe(-310);
        expect(snapDelta({ left: 76, right: 366 }, track, 24, true)).toBe(0);
    });

    it('knows when the rail edge cuts a focused slide (it is then snapped in)', () => {
        const track = { left: 0, right: 390 };
        expect(cut({ left: 24, right: 314 }, track)).toBe(false); // at the snap line
        expect(cut({ left: 330, right: 620 }, track)).toBe(true); // the next card, peeking
        expect(cut({ left: -267, right: 23 }, track)).toBe(true); // the previous one
        expect(cut({ left: -0.5, right: 390.5 }, track)).toBe(false); // sub-pixel rounding
    });
});
