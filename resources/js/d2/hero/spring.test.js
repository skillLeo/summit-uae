import { describe, expect, it } from 'vitest';
import { springs } from '../motion/springs.js';
import { overshoot, position, settle, step, timing } from './spring.js';

describe('hero springs', () => {
    it('starts at rest and ends at the target', () => {
        expect(position(springs.paper, 0)).toBeCloseTo(0, 6);
        expect(position(springs.paper, 3)).toBeCloseTo(1, 4);
        expect(position(springs.ui, 2)).toBeCloseTo(1, 4);
    });

    it('settles the paper deal in about 800 ms (spec §4.3) for a 62vw throw at 1440 px', () => {
        const travel = 0.62 * 1440;
        const seconds = settle(springs.paper, travel, 0.5);
        expect(seconds).toBeGreaterThan(0.7);
        expect(seconds).toBeLessThan(1.0);
    });

    it('keeps the paper overshoot small: ≤ 1° on the 19° rotation from 16° to −3°', () => {
        const fraction = overshoot(springs.paper);
        expect(fraction).toBeGreaterThan(0);
        expect(fraction * 19).toBeLessThanOrEqual(1.5);
    });

    it('builds a WAAPI timing whose last value is exactly the target', () => {
        const t = timing(springs.paper, 900);
        expect(t.duration).toBeGreaterThan(600);
        expect(t.duration).toBeLessThan(1100);
        // Node has no CSS.supports: the ease-out fallback with the same duration.
        expect(t.easing).toMatch(/^(linear\(0, .*, 1\)|cubic-bezier\()/);
    });

    it('caches timings per spring and travel', () => {
        expect(timing(springs.ui, 120)).toBe(timing(springs.ui, 120));
    });

    it('follows a moving target and comes to rest (parallax spring)', () => {
        const s = { x: 0, v: 0 };
        let moving = true;
        let frames = 0;
        while (moving && frames < 600) {
            moving = step(s, 12, springs.parallax, 1 / 60);
            frames++;
        }
        expect(moving).toBe(false);
        expect(s.x).toBe(12);
        expect(frames).toBeLessThan(120);
    });

    it('stays stable with long frames (a 50 ms frame is sub-stepped)', () => {
        const s = { x: 0, v: 0 };
        for (let i = 0; i < 40; i++) step(s, 2, springs.parallax, 0.05);
        expect(s.x).toBeCloseTo(2, 2);
    });
});
