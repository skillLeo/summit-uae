// The hero entrance's schedule (hero/deal.js schedule(); Design 2.1 §6, the owner's Phase 1 deal): the six sheets of
// the pile 80 ms apart (phones 100 ms), the paper spring ending within 1 px of rest (≈ 800 ms for the 62vw travel at
// 1440 px), so the whole deal is over in about 1.2 s and the top document's callouts are drawn well before 3 s.
import { describe, expect, it } from 'vitest';
import { schedule } from './deal.js';

describe('hero entrance schedule', () => {
    it('keeps the delays: 350 ms, then 80 ms apart (phones 100 ms)', () => {
        expect(schedule({ count: 6, reach: 893, mobile: false }).delays).toEqual([350, 430, 510, 590, 670, 750]);
        expect(schedule({ count: 6, reach: 429, mobile: true, start: 0 }).delays).toEqual([0, 100, 200, 300, 400, 500]);
    });

    it.each([
        ['1440 px (canvas at 0.95)', (0.62 * 1440) / (588 / 620)],
        ['1280 px', (0.62 * 1280) / (588 / 620)],
        ['768 px', 0.62 * 768],
    ])('at %s the whole deal takes about 1.2 s and the top document rests by 1.6 s', (_, reach) => {
        const plan = schedule({ count: 6, reach, mobile: false });
        expect(plan.duration).toBeGreaterThan(500);
        expect(plan.duration).toBeLessThanOrEqual(900);
        expect(plan.restAt - plan.delays[0]).toBeLessThanOrEqual(1300);
        expect(plan.restAt).toBeLessThan(1650);
        // The callouts (three, 90 ms apart, a 400 ms leader and a 200 ms label) finish before 3 s.
        expect(plan.restAt + 2 * 90 + 60 + 400 + 200).toBeLessThan(3000);
    });

    it('lands every sheet after the one before it, the top one last', () => {
        const plan = schedule({ count: 6, reach: 893, mobile: false });
        for (let i = 1; i < 6; i++) expect(plan.landed[i]).toBeGreaterThan(plan.landed[i - 1]);
        expect(plan.restAt).toBe(plan.landed[5]);
    });
});
