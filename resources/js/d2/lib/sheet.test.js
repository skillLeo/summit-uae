import { describe, expect, it } from 'vitest';
import { DISMISS_DISTANCE, DISMISS_VELOCITY, FLICK_MIN, dragOffset, releaseVelocity, shouldDismiss } from './sheet.js';

// Bottom sheet physics (upgrade spec §8: dismiss past 120 px or faster than 800 px/s).
describe('sheet drag to dismiss', () => {
    it('uses the spec thresholds', () => {
        expect(DISMISS_DISTANCE).toBe(120);
        expect(DISMISS_VELOCITY).toBe(0.8); // px per ms = 800 px/s
        expect(FLICK_MIN).toBe(16);
    });

    it('closes past 120 px whatever the speed', () => {
        expect(shouldDismiss(121, 0)).toBe(true);
        expect(shouldDismiss(120, 0)).toBe(false);
        expect(shouldDismiss(119, 0.2)).toBe(false);
    });

    it('closes on a downward flick faster than 800 px/s that travelled at least 16 px', () => {
        expect(shouldDismiss(40, 0.81)).toBe(true);
        expect(shouldDismiss(40, 0.8)).toBe(false);
        expect(shouldDismiss(15, 3)).toBe(false); // a jittery tap never dismisses
        expect(shouldDismiss(16, 1)).toBe(true);
        expect(shouldDismiss(60, -2)).toBe(false); // flicked back up
    });

    it('measures the release velocity over the sampled window', () => {
        expect(releaseVelocity([])).toBe(0);
        expect(releaseVelocity([[0, 0]])).toBe(0);
        expect(releaseVelocity([[0, 10], [0, 50]])).toBe(0); // no time elapsed
        expect(releaseVelocity([[100, 0], [150, 50], [200, 100]])).toBe(1);
        // A pause before lifting the finger is not a flick.
        expect(releaseVelocity([[100, 0], [140, 60], [400, 60]])).toBeLessThan(DISMISS_VELOCITY);
    });

    it('follows the finger down 1:1 and resists upwards', () => {
        expect(dragOffset(0)).toBe(0);
        expect(dragOffset(87)).toBe(87);
        expect(dragOffset(-4)).toBe(-4); // √4 × 2
        expect(dragOffset(-400)).toBe(-24); // capped: the sheet is already fully open
    });
});
