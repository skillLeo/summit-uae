// The hero entrance's schedule (hero/deal.js schedule(); Design 2.1 §6): the two sheets and the document 110 ms
// apart (phones 140 ms), the paper spring ending within 1 px of rest (≈ 800 ms for the 62vw travel at 1440 px), so
// the document rests ≈ 1.4 s after the storyboard's origin and its callouts are drawn well before 3 s.
import { describe, expect, it } from 'vitest';
import { schedule } from './deal.js';

describe('hero entrance schedule', () => {
    it('keeps the delays: 350 ms, then 110 ms apart (phones 140 ms)', () => {
        expect(schedule({ count: 3, reach: 893, mobile: false }).delays).toEqual([350, 460, 570]);
        expect(schedule({ count: 3, reach: 429, mobile: true, start: 0 }).delays).toEqual([0, 140, 280]);
    });

    it.each([
        ['1440 px (canvas at 0.95)', (0.62 * 1440) / (588 / 620)],
        ['1280 px', (0.62 * 1280) / (588 / 620)],
        ['768 px', 0.62 * 768],
    ])('at %s the document rests by 1.5 s', (_, reach) => {
        const plan = schedule({ count: 3, reach, mobile: false });
        expect(plan.duration).toBeGreaterThan(500);
        expect(plan.duration).toBeLessThanOrEqual(900);
        expect(plan.restAt).toBeLessThan(1500);
        // The callouts (three, 90 ms apart, a 400 ms leader and a 200 ms label) finish before 3 s.
        expect(plan.restAt + 2 * 90 + 60 + 400 + 200).toBeLessThan(3000);
    });

    it('lands every element after the one before it', () => {
        const plan = schedule({ count: 3, reach: 893, mobile: false });
        for (let i = 1; i < 3; i++) expect(plan.landed[i]).toBeGreaterThan(plan.landed[i - 1]);
        expect(plan.restAt).toBe(plan.landed[2]);
    });
});
