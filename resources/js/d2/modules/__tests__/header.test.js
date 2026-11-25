import { describe, expect, it } from 'vitest';
import { BRAND_GAP, COMPACT_SCALE, HYSTERESIS, MIN_SCROLL, compactThreshold, nextCompact, overflows, rowFit, rowState, shortcut } from '../header.js';

// Lives outside modules/*.js on purpose: app.js eagerly imports every modules/*.js file.
// The header (brief §3.7, §3.8, D13, D14; the client's final changes, item 1): the compact state, the navigation fit
// and the search shortcuts.
describe('header: compact state', () => {
    it('turns compact once the masthead has lifted by its own spare height (88 - 72 = 16 px on desktop)', () => {
        expect(compactThreshold(-16)).toBe(16);
        expect(compactThreshold(-24)).toBe(24);
        expect(compactThreshold(-8)).toBe(MIN_SCROLL); // 72 → 64 from 768 px
        expect(nextCompact(false, 23, 24)).toBe(false);
        expect(nextCompact(false, 24, 24)).toBe(true);
    });

    it('never turns compact before 8 px of scroll (phones: no masthead lift)', () => {
        expect(MIN_SCROLL).toBe(8);
        expect(compactThreshold(0)).toBe(8);
        expect(compactThreshold(12)).toBe(8); // a positive sticky top is not a lift
        expect(compactThreshold(Number.NaN)).toBe(8); // "auto" or unreadable
        expect(nextCompact(false, 7, compactThreshold(0))).toBe(false);
        expect(nextCompact(false, 8, compactThreshold(0))).toBe(true);
    });

    it('returns to the top state only clearly above the threshold (no flicker at the edge)', () => {
        expect(HYSTERESIS).toBe(8);
        expect(nextCompact(true, 23, 24)).toBe(true);
        expect(nextCompact(true, 17, 24)).toBe(true);
        expect(nextCompact(true, 16, 24)).toBe(false);
        expect(nextCompact(true, 0, 8)).toBe(false);
        expect(nextCompact(true, 1, 8)).toBe(true); // phones: back only at the very top
    });
});

describe('header: navigation fit', () => {
    it('collapses the row only when the seven labels need more room than the row has (1 px rounding slack)', () => {
        expect(overflows(1018, 1200)).toBe(false); // Russian at 1440 / 1280 px: the container's 1200 px
        expect(overflows(1018, 960)).toBe(true); // Russian at 1024 px: the menu button
        expect(overflows(712.6, 712)).toBe(false);
        expect(overflows(713.2, 712)).toBe(true);
    });
});

// D13 + the client's item 1: the header holds the lockup, the seven labels and the language menu only. The labels ride
// in the bar, at its end, wherever they fit beside the lockup (56 px at the top of the page, 44 px once compact) and
// the tools; elsewhere they take their own row at the top of the page and tuck behind the menu button once scrolled.
// Widths measured on the live header (Onest and IBM Plex Sans Arabic, 15/500): label boxes with their 12 px paddings,
// the 222 px lockup, the bar's 16 px gap, the language trigger.
describe('header: the seven labels ride in the bar where they fit', () => {
    const en = { rowShown: true, collapsed: false, lockup: 222, labels: 724, gap: 16, tools: 104 };
    const ar = { rowShown: true, collapsed: false, lockup: 222, labels: 755, gap: 16, tools: 92 };
    const ru = { rowShown: true, collapsed: false, lockup: 222, labels: 1030, gap: 16, tools: 108 };
    const needs = (row, scale = 1) => row.lockup * scale + BRAND_GAP + row.labels + row.gap + row.tools;

    it('fits English and Arabic in one row in a full 1200 px container, at the top and once compact', () => {
        expect(rowFit({ ...en, bar: 1200 })).toEqual({ top: true, compact: true });
        expect(rowFit({ ...ar, bar: 1200 })).toEqual({ top: true, compact: true });
        expect(rowFit({ ...en, bar: needs(en) })).toEqual({ top: true, compact: true }); // exactly full
    });

    it('fits only once compact where the 44 px lockup leaves the room the 56 px one does not', () => {
        const bar = needs(en, COMPACT_SCALE) + 1;
        expect(rowFit({ ...en, bar })).toEqual({ top: false, compact: true });
        expect(rowState(rowFit({ ...en, bar }), false)).toEqual({ inline: false, tucked: false, lifted: false });
        expect(rowState(rowFit({ ...en, bar }), true)).toEqual({ inline: true, tucked: false, lifted: true });
    });

    it('gives Russian its own row at the top and tucks it once scrolled (it never fits beside the lockup)', () => {
        const fit = rowFit({ ...ru, bar: 1200 });
        expect(fit).toEqual({ top: false, compact: false });
        expect(rowState(fit, false)).toEqual({ inline: false, tucked: false, lifted: false });
        expect(rowState(fit, true)).toEqual({ inline: false, tucked: true, lifted: true });
    });

    it('keeps an inline row inline when compact, with nothing lifted (the header never changes height)', () => {
        const fit = rowFit({ ...en, bar: 1200 });
        expect(rowState(fit, false)).toEqual({ inline: true, tucked: false, lifted: false });
        expect(rowState(fit, true)).toEqual({ inline: true, tucked: false, lifted: false });
    });

    it('has no row state where the row is not shown or collapsed (the menu button at the end is already there)', () => {
        expect(rowFit({ ...en, bar: 1200, rowShown: false })).toBe(null); // below 1024 px; Russian below 69rem
        expect(rowFit({ ...en, bar: 1200, collapsed: true })).toBe(null); // the row would overflow even alone
        expect(rowState(null, true)).toEqual({ inline: false, tucked: false, lifted: false });
    });

    it('scales the lockup from 56 to 44 px in the compact bar', () => {
        expect(COMPACT_SCALE * 56).toBeCloseTo(44, 6);
    });
});

describe('header: search shortcuts (D14: no key cap, the shortcuts stay)', () => {
    const key = (init) => ({ key: '', code: '', ctrlKey: false, metaKey: false, altKey: false, shiftKey: false, repeat: false, isComposing: false, defaultPrevented: false, ...init });

    it('opens the palette on "/" unless the visitor is typing', () => {
        expect(shortcut(key({ key: '/' }), false)).toBe('shortcut-slash');
        expect(shortcut(key({ key: '/' }), true)).toBe(null);
        expect(shortcut(key({ key: '/', ctrlKey: true }), false)).toBe(null);
        expect(shortcut(key({ key: '/', altKey: true }), false)).toBe(null);
    });

    it('opens the palette on Ctrl+K and Cmd+K, also while typing, also on non-Latin layouts (physical key)', () => {
        expect(shortcut(key({ key: 'k', ctrlKey: true }), false)).toBe('shortcut-k');
        expect(shortcut(key({ key: 'K', metaKey: true }), true)).toBe('shortcut-k');
        expect(shortcut(key({ key: 'л', code: 'KeyK', ctrlKey: true }), false)).toBe('shortcut-k'); // Russian layout
        expect(shortcut(key({ key: 'ن', code: 'KeyK', metaKey: true }), false)).toBe('shortcut-k'); // Arabic layout
        expect(shortcut(key({ key: 'k', ctrlKey: true, shiftKey: true }), false)).toBe(null);
        expect(shortcut(key({ key: 'k', ctrlKey: true, altKey: true }), false)).toBe(null);
        expect(shortcut(key({ key: 'k' }), false)).toBe(null);
    });

    it('ignores repeats, IME composition and keys another handler has claimed', () => {
        expect(shortcut(key({ key: '/', repeat: true }), false)).toBe(null);
        expect(shortcut(key({ key: '/', isComposing: true }), false)).toBe(null);
        expect(shortcut(key({ key: 'k', ctrlKey: true, defaultPrevented: true }), false)).toBe(null);
        expect(shortcut(null, false)).toBe(null);
    });
});
