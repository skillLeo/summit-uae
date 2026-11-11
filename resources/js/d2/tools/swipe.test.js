import { describe, expect, it } from 'vitest';
import { axis, committed, EDGE, MIN_DISTANCE, neighbour, resist, SLOP } from './swipe.js';

// Phone swipe between the home tools (upgrade spec §8 "Gestures: swipe between Tools tabs").
describe('tools swipe', () => {
    it('classifies a drag only once it leaves the slop, and gives ties to vertical scrolling', () => {
        expect(axis(3, 4)).toBeNull();
        expect(axis(SLOP - 1, -(SLOP - 1))).toBeNull();
        expect(axis(30, 4)).toBe('x');
        expect(axis(-30, 10)).toBe('x');
        expect(axis(12, 11)).toBe('y'); // diagonal: the page scrolls, the tools stay
        expect(axis(4, 40)).toBe('y');
        expect(axis(0, -12)).toBe('y');
    });

    it('commits past a quarter of the panel (64 px at least) or on a flick the same way', () => {
        expect(committed(-100, 342, 0)).toBe(true); // 342 px panel: 85.5 px needed
        expect(committed(-80, 342, 0)).toBe(false);
        expect(committed(70, 200, 0)).toBe(true); // narrow panel: the 64 px minimum
        expect(committed(MIN_DISTANCE - 1, 200, 0)).toBe(false);
        expect(committed(-40, 342, -0.8)).toBe(true); // a quick flick to the left
        expect(committed(-40, 342, 0.8)).toBe(false); // a flick back the other way does not count
        expect(committed(-15, 342, -2)).toBe(false); // too short even for a flick
        expect(committed(0, 342, 0)).toBe(false);
    });

    it('opens the next tool in the reading direction and never wraps around', () => {
        const order = ['gratuity', 'rent'];
        expect(neighbour(order, 'gratuity', -90)).toBe('rent'); // LTR: finger to the left → next
        expect(neighbour(order, 'gratuity', 90)).toBeNull(); // nothing before the first tool
        expect(neighbour(order, 'rent', 90)).toBe('gratuity');
        expect(neighbour(order, 'rent', -90)).toBeNull(); // nothing after the last tool
        expect(neighbour(order, 'gratuity', 90, true)).toBe('rent'); // RTL: finger to the right → next
        expect(neighbour(order, 'gratuity', -90, true)).toBeNull();
        expect(neighbour(order, 'rent', -90, true)).toBe('gratuity');
        expect(neighbour(order, 'rent', 0)).toBeNull();
        expect(neighbour(order, 'unknown', -90)).toBeNull();
    });

    it('follows the finger, and resists towards a missing neighbour', () => {
        expect(resist(-80, true)).toBe(-80);
        expect(resist(80, false)).toBe(20);
        expect(resist(-40, false)).toBe(-10);
    });

    it('leaves the screen edges to the browser back gesture', () => {
        expect(EDGE).toBeGreaterThanOrEqual(16);
    });
});
