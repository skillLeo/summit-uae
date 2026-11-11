import { describe, expect, it } from 'vitest';
import { MAX_DEG, tiltFor } from './tilt.js';

// Pointer tilt (Design 2.1 §6): at most 2° in any direction, mirrored on the x axis in RTL.
describe('hero pointer tilt', () => {
    it('follows the pointer and stays at rest in the centre', () => {
        expect(tiltFor(0, 0)).toEqual({ rx: -0, ry: 0 });
        expect(tiltFor(1, 0).ry).toBe(MAX_DEG);
        expect(tiltFor(0, 1).rx).toBe(-MAX_DEG);
        expect(tiltFor(0.5, -0.5)).toEqual({ rx: 1, ry: 1 });
    });

    it('never tilts beyond 2 degrees, corners included', () => {
        for (const [x, y] of [[1, 1], [-1, 1], [1, -1], [-1, -1], [0.9, 0.8]]) {
            const { rx, ry } = tiltFor(x, y);
            expect(Math.hypot(rx, ry)).toBeLessThanOrEqual(MAX_DEG + 1e-9);
        }
    });

    it('mirrors the x axis in RTL', () => {
        expect(tiltFor(1, 0, -1).ry).toBe(-MAX_DEG);
        expect(tiltFor(-0.5, 0, -1).ry).toBe(1);
    });
});
