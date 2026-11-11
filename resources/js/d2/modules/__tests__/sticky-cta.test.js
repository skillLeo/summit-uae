import { describe, expect, it } from 'vitest';
import { ctaVisible, readPrefill } from '../sticky-cta.js';

// Lives outside modules/*.js on purpose: app.js eagerly imports every modules/*.js file.
// Sticky consultation bar (upgrade spec §8: shown above the tab bar only after the hero is scrolled past).
describe('sticky consultation bar', () => {
    const base = { mobile: true, past: true, covered: false, editing: false, keyboard: false };

    it('shows on a phone once the hero is scrolled past', () => {
        expect(ctaVisible(base)).toBe(true);
        expect(ctaVisible({ ...base, past: false })).toBe(false);
        expect(ctaVisible({ ...base, mobile: false })).toBe(false); // never from 768 px
    });

    it('steps aside for the page CTA, a focused field and the on-screen keyboard', () => {
        expect(ctaVisible({ ...base, covered: true })).toBe(false);
        expect(ctaVisible({ ...base, editing: true })).toBe(false);
        expect(ctaVisible({ ...base, keyboard: true })).toBe(false);
    });

    it('never shows where the tab bar exists: one bottom bar on phones, never two stacked (brief 2.1 §6)', () => {
        expect(ctaVisible({ ...base, tabbar: true })).toBe(false);
        expect(ctaVisible({ ...base, tabbar: false })).toBe(true);
    });

    it("steps aside while a page's own bottom dock is on screen (the gratuity total)", () => {
        expect(ctaVisible({ ...base, docked: true })).toBe(false);
        expect(ctaVisible({ ...base, docked: false })).toBe(true);
    });

    it('keeps only known prefill codes', () => {
        expect(readPrefill('{"service":"S06","situation":"S06-2"}')).toEqual({ service: 'S06', situation: 'S06-2' });
        expect(readPrefill('{"service":["S06"],"evil":"x","tool":"rent"}')).toEqual({ tool: 'rent' });
        expect(readPrefill('{"consultant":"team-ahmed","summary":"free text"}')).toEqual({ consultant: 'team-ahmed' });
        expect(readPrefill('{"service":"<script>"}')).toEqual({});
        expect(readPrefill('not json')).toEqual({});
        expect(readPrefill('["S06"]')).toEqual({});
        expect(readPrefill(undefined)).toEqual({});
    });
});
