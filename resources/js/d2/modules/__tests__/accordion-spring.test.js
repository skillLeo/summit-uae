import { describe, expect, it } from 'vitest';
import { springFrames } from '../accordion-spring.js';

// Lives outside modules/*.js on purpose: app.js eagerly imports every modules/*.js file.
describe('accordion height spring (browsers without interpolate-size)', () => {
    it('runs the height from where the item is to where it goes', () => {
        expect(springFrames(56, 240, true)).toEqual({ keyframes: [{ blockSize: '56px' }, { blockSize: '240px' }], keepOpen: true });
        expect(springFrames(240, 56, false)).toEqual({ keyframes: [{ blockSize: '240px' }, { blockSize: '56px' }], keepOpen: false });
    });

    it('never produces a negative or invalid height', () => {
        expect(springFrames(-4, Number.NaN, true).keyframes).toEqual([{ blockSize: '0px' }, { blockSize: '0px' }]);
        expect(springFrames('120', undefined, 0)).toEqual({ keyframes: [{ blockSize: '120px' }, { blockSize: '0px' }], keepOpen: false });
    });
});
