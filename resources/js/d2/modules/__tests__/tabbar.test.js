import { describe, expect, it } from 'vitest';
import { REVEAL_ZONE, STEP, fabAway, isWhatsAppLink, keyboardOpen, overflowing, scrollStep, typing } from '../tabbar.js';

// Lives outside modules/*.js on purpose: app.js eagerly imports every modules/*.js file.
// Mobile tab bar (upgrade spec §8: hide on scroll down past 24 px, show on scroll up).
describe('tab bar', () => {
    const shown = { hidden: false, lastY: 0 };

    it('stays within the first 24 px and at the end of the page', () => {
        expect(REVEAL_ZONE).toBe(24);
        expect(scrollStep(shown, { y: 24, end: 5000, pinned: false })).toEqual({ hidden: false, lastY: 24 });
        expect(scrollStep({ hidden: true, lastY: 4000 }, { y: 4999, end: 5000, pinned: false }).hidden).toBe(false);
    });

    it('hides on scroll down past 24 px and shows on scroll up', () => {
        const down = scrollStep({ hidden: false, lastY: 20 }, { y: 80, end: 5000, pinned: false });
        expect(down).toEqual({ hidden: true, lastY: 80 });
        const up = scrollStep(down, { y: 60, end: 5000, pinned: false });
        expect(up).toEqual({ hidden: false, lastY: 60 });
    });

    it('ignores jitter smaller than the step', () => {
        const state = { hidden: true, lastY: 500 };
        expect(scrollStep(state, { y: 500 - (STEP - 1), end: 5000, pinned: false })).toBe(state);
    });

    it('never hides while pinned (sheet or dialog open, focus inside, typing, reduced motion)', () => {
        expect(scrollStep({ hidden: false, lastY: 100 }, { y: 400, end: 5000, pinned: true })).toEqual({ hidden: false, lastY: 400 });
    });

    it('knows which fields bring up an on-screen keyboard', () => {
        expect(typing({ tagName: 'INPUT', type: 'text' })).toBe(true);
        expect(typing({ tagName: 'INPUT', type: 'email' })).toBe(true);
        expect(typing({ tagName: 'INPUT', type: 'checkbox' })).toBe(false);
        expect(typing({ tagName: 'INPUT', type: 'radio' })).toBe(false);
        expect(typing({ tagName: 'TEXTAREA' })).toBe(true);
        expect(typing({ tagName: 'SELECT' })).toBe(true);
        expect(typing({ tagName: 'DIV', isContentEditable: true })).toBe(true);
        expect(typing({ tagName: 'BUTTON' })).toBe(false);
        expect(typing(null)).toBe(false);
    });

    it('detects an on-screen keyboard from the visual viewport, not from a pinch zoom', () => {
        expect(keyboardOpen({ innerHeight: 844, visualViewport: { height: 480, scale: 1 } })).toBe(true);
        expect(keyboardOpen({ innerHeight: 844, visualViewport: { height: 800, scale: 1 } })).toBe(false);
        expect(keyboardOpen({ innerHeight: 844, visualViewport: { height: 420, scale: 2 } })).toBe(false);
        expect(keyboardOpen({ innerHeight: 844 })).toBe(false);
        expect(keyboardOpen(undefined)).toBe(false);
    });

    it('lets the floating WhatsApp button step aside for overlays, typing and another WhatsApp control in view', () => {
        expect(fabAway({})).toBe(false);
        expect(fabAway({ overlays: 1 })).toBe(true);
        expect(fabAway({ editing: true })).toBe(true);
        expect(fabAway({ others: 2 })).toBe(true);
        expect(fabAway({ overlays: 0, editing: false, others: 0 })).toBe(false);
        expect(fabAway()).toBe(false);
    });

    it('recognises the WhatsApp links (the WA01 draft) it gives way to', () => {
        expect(isWhatsAppLink('https://wa.me/971524666191?text=Hello')).toBe(true);
        expect(isWhatsAppLink('https://api.whatsapp.com/send?phone=971524666191')).toBe(true);
        expect(isWhatsAppLink('tel:+971524666191')).toBe(false);
        expect(isWhatsAppLink('/en/contact/')).toBe(false);
        expect(isWhatsAppLink('https://wa.me.example.test/')).toBe(false);
        expect(isWhatsAppLink('')).toBe(false);
        expect(isWhatsAppLink(null)).toBe(false);
    });

    it('knows when the four labels do not fit the bar (then the Caption size, never a cut word)', () => {
        const cells = [
            { left: 4, right: 92 },
            { left: 92, right: 180 },
            { left: 180, right: 268 },
            { left: 268, right: 356 },
        ];
        expect(overflowing(cells, { width: 360 }, 8)).toBe(false);
        expect(overflowing([...cells.slice(0, 3), { left: 268, right: 362 }], { width: 360 }, 8)).toBe(true);
        // Right to left: the same total, mirrored.
        expect(overflowing(cells.map((c) => ({ left: 360 - c.right, right: 360 - c.left })), { width: 360 }, 8)).toBe(false);
        expect(overflowing([], { width: 360 })).toBe(false);
        expect(overflowing(cells, null)).toBe(false);
    });
});

