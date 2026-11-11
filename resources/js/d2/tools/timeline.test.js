import { describe, expect, it } from 'vitest';
import { axisMax, tickStep } from './timeline.js';

describe('timeline axis', () => {
    it('uses about ten ticks at most', () => {
        expect([1, 10, 11, 20, 21, 50, 51].map(tickStep)).toEqual([1, 1, 2, 2, 5, 5, 10]);
    });

    it('covers the calendar span in whole years, at least one', () => {
        expect(axisMax(0.2)).toBe(1);
        expect(axisMax(1)).toBe(1);
        expect(axisMax(5.0055)).toBe(6);
        expect(axisMax(8.62)).toBe(9);
    });

    it('ends on a regular tick (no crowded last label)', () => {
        expect(axisMax(10.4)).toBe(12);
        expect(axisMax(40.03)).toBe(45);
        expect(axisMax(55)).toBe(60);
        for (const years of [3.3, 12.1, 19.9, 23, 47.5, 66]) {
            const max = axisMax(years);
            expect(max % tickStep(max)).toBe(0);
            expect(max).toBeGreaterThanOrEqual(years);
        }
    });
});
