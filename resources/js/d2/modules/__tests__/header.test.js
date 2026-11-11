import { describe, expect, it } from 'vitest';
import { HYSTERESIS, MIN_SCROLL, compactLayout, compactThreshold, nextCompact, overflows, shortcut } from '../header.js';

// Lives outside modules/*.js on purpose: app.js eagerly imports every modules/*.js file.
// Design 2.1 header (brief §3.7, §3.8, D13, D14): the compact state, the navigation fit and the search shortcuts.
describe('header: compact state', () => {
    it('turns compact once the masthead has lifted by its own spare height (88 - 64 = 24 px on desktop)', () => {
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

// D13 + QA round 1: once scrolled, the seven labels stay in the 64 px bar wherever they fit beside the shield and
// the tools; the menu button replaces them only where they do not. Widths measured on the live header (Onest and
// IBM Plex Sans Arabic, 15/500): label boxes with their 12 px paddings, the shield's 44 px target, the bar's 16 px
// gap, the tools (search, language, CTA).
describe('header: the compact bar docks the seven labels where they fit', () => {
    const en = { rowShown: true, collapsed: false, brand: 44, labels: 724, gap: 16, tools: 400 };
    const ar = { rowShown: true, collapsed: false, brand: 44, labels: 755, gap: 16, tools: 304 };
    const ru = { rowShown: true, collapsed: false, brand: 44, labels: 1030, gap: 16, tools: 493 };

    it('docks English and Arabic in a full 1200 px container (windows from about 1,250 px)', () => {
        expect(compactLayout({ ...en, bar: 1200 })).toBe('docked');
        expect(compactLayout({ ...ar, bar: 1200 })).toBe('docked');
        expect(compactLayout({ ...en, bar: 1184 })).toBe('docked'); // 44 + 724 + 16 + 400: exactly full
        expect(compactLayout({ ...ar, bar: 1119 })).toBe('docked');
    });

    it('tucks the row (menu button beside the shield) where the labels do not fit: Russian, narrower windows', () => {
        expect(compactLayout({ ...ru, bar: 1200 })).toBe('tucked'); // Russian never fits beside its tools
        expect(compactLayout({ ...en, bar: 1116 })).toBe('tucked'); // English at 1180 px
        expect(compactLayout({ ...en, bar: 960 })).toBe('tucked'); // 1024 px
        expect(compactLayout({ ...ar, bar: 1118 })).toBe('docked'); // 1119 needed: within the 1 px rounding slack
        expect(compactLayout({ ...ar, bar: 1116 })).toBe('tucked'); // Arabic at 1180 px
    });

    it('has no compact layout where the row is not shown or collapsed (the menu button at the end is already there)', () => {
        expect(compactLayout({ ...en, bar: 1200, rowShown: false })).toBe(null); // below 1024 px; Russian below 69rem
        expect(compactLayout({ ...en, bar: 1200, collapsed: true })).toBe(null); // the row would overflow even alone
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
