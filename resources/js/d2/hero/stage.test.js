import { describe, expect, it } from 'vitest';
import { slideFrom, SWITCH_SPRING, SWITCH_TRAVEL } from './stage.js';
import { overshoot, settle } from './spring.js';

// The hero document switch (Design 2.1 §6): the next paper comes from the inline-end side with a small rotation and
// settles to 0 on the spring {stiffness 170, damping 20}; going back it comes from the inline-start side.
describe('hero document switch', () => {
    it('slides the next document in from the inline-end side, mirrored in RTL', () => {
        expect(slideFrom(1, 1)).toEqual({ translate: `${SWITCH_TRAVEL}px -6px`, rotate: '4deg' });
        expect(slideFrom(1, -1)).toEqual({ translate: `${-SWITCH_TRAVEL}px -6px`, rotate: '-4deg' });
    });

    it('brings an earlier document in from the inline-start side', () => {
        expect(slideFrom(-1, 1)).toEqual({ translate: `${-SWITCH_TRAVEL}px -6px`, rotate: '-4deg' });
        expect(slideFrom(-1, -1)).toEqual({ translate: `${SWITCH_TRAVEL}px -6px`, rotate: '4deg' });
    });

    it('uses the brief\'s spring: a short, small overshoot, settled well under a second', () => {
        expect(SWITCH_SPRING).toMatchObject({ stiffness: 170, damping: 20 });
        expect(overshoot(SWITCH_SPRING)).toBeLessThan(0.05);
        expect(settle(SWITCH_SPRING, SWITCH_TRAVEL, 0.5)).toBeLessThan(0.9);
    });
});
