// Practice-area filter helpers (components/services-layout.js): audience matching and FLIP offsets. The grid is
// equal (Design 2.1 D21), so the former bento span planning (plan, columnCount) is gone.
import { describe, expect, it } from 'vitest';
import * as layout from '../services-layout.js';

const { matches, offset } = layout;

// config/audience_map.php → hero (proposed mapping)
const HERO = {
    individual: ['S01', 'S02', 'S03', 'S05', 'S06', 'S08', 'S10'],
    business: ['S01', 'S04', 'S06', 'S07', 'S09', 'S10', 'S11'],
    investor: ['S03', 'S04', 'S05', 'S07', 'S09'],
};

describe('matches()', () => {
    it('matches every card for "all" or no audience', () => {
        expect(matches('individual business', 'all')).toBe(true);
        expect(matches('', 'all')).toBe(true);
        expect(matches(undefined, '')).toBe(true);
    });

    it('matches whole audience keys only', () => {
        expect(matches('individual business', 'business')).toBe(true);
        expect(matches('individual business', 'investor')).toBe(false);
        expect(matches('investor', 'invest')).toBe(false);
        expect(matches(null, 'business')).toBe(false);
    });

    it('selects the hero mapping of each audience from the cards\' data-svc-audiences', () => {
        const codes = ['S01', 'S02', 'S03', 'S04', 'S05', 'S06', 'S07', 'S08', 'S09', 'S10', 'S11'];
        const attr = (code) => Object.keys(HERO).filter((a) => HERO[a].includes(code)).join(' ');
        for (const [audience, expected] of Object.entries(HERO)) {
            expect(codes.filter((code) => matches(attr(code), audience))).toEqual(expected);
        }
        expect(codes.filter((code) => matches(attr(code), 'all'))).toEqual(codes);
    });
});

describe('offset()', () => {
    const box = (left, top, width, height = 300) => ({ left, top, width, height, right: left + width });

    it('measures from the inline-start edge (left in LTR, right in RTL)', () => {
        expect(offset(box(100, 400, 384), box(508, 0, 384))).toEqual({ x: -408, y: 400, resized: false });
        expect(offset(box(916, 0, 384), box(508, 0, 384), true)).toEqual({ x: 408, y: 0, resized: false });
    });

    it('flags a card whose box changed size (a row of another height)', () => {
        expect(offset(box(0, 0, 384, 300), box(0, 0, 384, 340)).resized).toBe(true);
        expect(offset(box(0, 0, 384, 300), box(0, 0, 384.5, 300.5)).resized).toBe(false);
    });
});

describe('no bento planning', () => {
    it('exports only the filter helpers (equal grid: no spans, no column count)', () => {
        expect(Object.keys(layout).sort()).toEqual(['matches', 'offset']);
    });
});
