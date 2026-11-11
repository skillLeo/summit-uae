import { describe, expect, it } from 'vitest';
import { INK_IN, PULL_MS, SWITCH_SPRING, switchPlan } from './stage.js';
import { overshoot, settle } from './spring.js';

// The hero document switch (Design 2.1 §6, the owner's option 3): the chosen sheet lifts out of the pile, travels onto
// the top on the spring {stiffness 170, damping 20} and settles; its ink fades in as it arrives, its callouts after.
describe('hero document switch', () => {
    it('uses the brief\'s spring: a short, small overshoot, settled well under a second', () => {
        expect(SWITCH_SPRING).toMatchObject({ stiffness: 170, damping: 20 });
        expect(overshoot(SWITCH_SPRING)).toBeLessThan(0.05);
        expect(settle(SWITCH_SPRING, 200, 0.5)).toBeLessThan(0.9);
    });

    it('lifts first, then travels; the paper settles in about a second', () => {
        const plan = switchPlan(170);
        expect(plan.pull).toBe(PULL_MS);
        expect(plan.travel).toBeGreaterThan(300);
        expect(plan.travel).toBeLessThan(900);
        expect(plan.settled).toBe(PULL_MS + plan.travel);
        expect(plan.settled).toBeLessThan(1200);
    });

    it('fades the ink in while the sheet arrives on top, never before it is on top', () => {
        const plan = switchPlan(170);
        expect(plan.inkIn).toEqual(INK_IN);
        // The ink starts after the lift (the sheet is the top of the pile by then) and is complete before it settles.
        expect(plan.inkIn.delay).toBeGreaterThan(0);
        expect(plan.inkIn.delay + plan.inkIn.duration).toBeLessThanOrEqual(plan.travel);
    });
});
