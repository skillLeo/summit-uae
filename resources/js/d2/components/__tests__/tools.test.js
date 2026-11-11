import { describe, expect, it } from 'vitest';
import { enterSide } from '../tools.js';

// d2Tools: the side a tool enters from after a switch (tap, keys, swipe, deep link alike).
describe('tools switch', () => {
    it('brings the next tool in from the inline end and the previous one from the start', () => {
        expect(enterSide(0, 1)).toBe('end');
        expect(enterSide(1, 0)).toBe('start');
    });

    it('leaves the panel alone when nothing changed or a value is unknown', () => {
        expect(enterSide(1, 1)).toBeNull();
        expect(enterSide(-1, 1)).toBeNull();
        expect(enterSide(0, -1)).toBeNull();
    });
});
