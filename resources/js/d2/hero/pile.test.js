import { describe, expect, it } from 'vitest';
import { depthFactor, FAN, hoverTargets, moveToFront, nextOf, PARALLAX_PX, PEEK, pullPose, PULL, stepOf } from './pile.js';

// The hero pile (Design 2.1 D15, §6; the owner's option 3 and autoplay).
describe('hero pile order', () => {
    const pager = ['cheque', 'contract', 'tenancy', 'transfer', 'messages', 'notice'];

    it('brings a document to the top; the old top lies right under it, the rest keep their order', () => {
        expect(moveToFront(pager, 'tenancy')).toEqual(['tenancy', 'cheque', 'contract', 'transfer', 'messages', 'notice']);
        expect(moveToFront(['tenancy', 'cheque', 'contract'], 'contract')).toEqual(['contract', 'tenancy', 'cheque']);
        expect(moveToFront(pager, 'cheque')).toEqual(pager);
        expect(moveToFront(pager, 'nope')).toEqual(pager);
    });

    it('cycles through the pager order and wraps around', () => {
        expect(nextOf(pager, 'cheque')).toBe('contract');
        expect(nextOf(pager, 'notice')).toBe('cheque');
        expect(nextOf(pager, 'cheque', -1)).toBe('notice');
        expect(nextOf([], 'cheque')).toBeNull();
        expect(stepOf(pager, 'cheque', 'tenancy')).toBe(1);
        expect(stepOf(pager, 'tenancy', 'cheque')).toBe(-1);
    });
});

describe('hero pile motion', () => {
    it('pulls a chosen sheet out towards the inline-end side, a little up, straighter and lifted (mirrored in RTL)', () => {
        const pose = { x: -14, y: -8, rot: -2.4 };
        const ltr = pullPose(pose, 338, 1);
        expect(ltr.x).toBeGreaterThan(pose.x + PULL.min - 0.01);
        expect(ltr.y).toBe(pose.y - PULL.rise);
        expect(Math.abs(ltr.rot)).toBeLessThan(Math.abs(pose.rot));
        expect(ltr.scale).toBeGreaterThan(1);
        const rtl = pullPose({ x: 14, y: -8, rot: 2.4 }, 338, -1);
        expect(rtl.x).toBeCloseTo(-ltr.x, 6);
        // Never further than the clamp, however wide the sheet.
        expect(pullPose({ x: 0, y: 0, rot: 0 }, 2000, 1).x).toBe(PULL.max);
    });

    it('moves the top sheet most and the bottom one least', () => {
        expect(depthFactor(0)).toBe(1);
        expect(depthFactor(5)).toBeCloseTo(0.4, 6);
        expect(depthFactor(2)).toBeGreaterThan(depthFactor(3));
    });

    const docs = [
        { type: 'cheque', depth: 0, pose: { x: 0, y: 0, rot: 0 } },
        { type: 'contract', depth: 1, pose: { x: -14, y: -8, rot: -2.4 } },
        { type: 'messages', depth: 5, pose: { x: 58, y: -26, rot: 6.5 } },
    ];

    it('follows the pointer by depth, at most 12 px', () => {
        const t = hoverTargets({ pointer: { x: 1, y: -1 }, docs });
        expect(t.get('cheque')).toEqual({ tx: PARALLAX_PX, ty: -PARALLAX_PX, r: 0, air: 0 });
        expect(Math.abs(t.get('messages').tx)).toBeLessThan(Math.abs(t.get('cheque').tx));
        for (const v of t.values()) expect(Math.hypot(v.tx, v.ty)).toBeLessThanOrEqual(PARALLAX_PX * Math.SQRT2 + 1e-9);
        expect(hoverTargets({ pointer: { x: 1, y: 0 }, docs, dir: -1 }).get('cheque').tx).toBe(-PARALLAX_PX);
    });

    it('breathes open on pointer enter: back sheets fan out along their own offset and rotation, the top stays', () => {
        const t = hoverTargets({ open: true, docs });
        expect(t.get('cheque')).toEqual({ tx: 0, ty: 0, r: 0, air: 0 });
        expect(Math.sign(t.get('contract').tx)).toBe(-1);
        expect(Math.sign(t.get('contract').r)).toBe(-1);
        expect(Math.abs(t.get('messages').tx)).toBeLessThanOrEqual(FAN.maxPx);
        expect(Math.abs(t.get('messages').r)).toBeLessThanOrEqual(FAN.maxDeg);
    });

    it('lifts a previewed back sheet up with part of its shadow; the top sheet never peeks', () => {
        const t = hoverTargets({ peek: 'contract', docs });
        expect(t.get('contract').ty).toBe(-PEEK.up);
        expect(t.get('contract').air).toBe(PEEK.air);
        expect(hoverTargets({ peek: 'cheque', docs }).get('cheque')).toEqual({ tx: 0, ty: 0, r: 0, air: 0 });
        expect(hoverTargets({ docs }).get('contract')).toEqual({ tx: 0, ty: 0, r: 0, air: 0 });
    });
});
