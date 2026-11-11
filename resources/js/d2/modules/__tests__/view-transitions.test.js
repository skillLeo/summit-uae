import { afterEach, describe, expect, it } from 'vitest';
import viewTransitions, { isLanguageSwitch, localeOf } from '../view-transitions.js';

// Lives outside modules/*.js on purpose: app.js eagerly imports every modules/*.js file.

const SITE = 'https://www.example.test';
const LOCALES = ['en', 'ar', 'ru'];

describe('localeOf', () => {
    it('reads the language segment of same-origin URLs', () => {
        expect(localeOf(`${SITE}/ar/faq/`, `${SITE}/en/`, LOCALES)).toBe('ar');
        expect(localeOf('/ru/', `${SITE}/en/faq/`, LOCALES)).toBe('ru');
        expect(localeOf('../about/', `${SITE}/en/faq/`, LOCALES)).toBe('en');
        expect(localeOf(`${SITE}/en`, `${SITE}/`, LOCALES)).toBe('en');
    });

    it('returns an empty string outside the languages and null for other origins or invalid URLs', () => {
        expect(localeOf(`${SITE}/`, `${SITE}/en/`, LOCALES)).toBe('');
        expect(localeOf(`${SITE}/up`, `${SITE}/en/`, LOCALES)).toBe('');
        expect(localeOf(`${SITE}/de/`, `${SITE}/en/`, LOCALES)).toBe('');
        expect(localeOf(`${SITE}/english/`, `${SITE}/en/`, LOCALES)).toBe('');
        expect(localeOf('https://other.test/ar/', `${SITE}/en/`, LOCALES)).toBeNull();
        expect(localeOf('http://[bad', `${SITE}/en/`, LOCALES)).toBeNull();
    });
});

describe('isLanguageSwitch', () => {
    it('is true only when the destination is another site language', () => {
        expect(isLanguageSwitch(`${SITE}/en/faq/`, `${SITE}/ar/faq/`)).toBe(true);
        expect(isLanguageSwitch(`${SITE}/ar/faq/#faq-03`, `${SITE}/ru/faq/#faq-03`)).toBe(true);
        expect(isLanguageSwitch(`${SITE}/en/faq/`, `${SITE}/en/about/`)).toBe(false);
        expect(isLanguageSwitch(`${SITE}/en/faq/`, `${SITE}/en/faq/`)).toBe(false);
        expect(isLanguageSwitch(`${SITE}/en/faq/`, `${SITE}/`)).toBe(false);
        expect(isLanguageSwitch(`${SITE}/en/faq/`, 'https://other.test/ar/')).toBe(false);
    });

    it('treats every choice on the root chooser as a language switch', () => {
        expect(isLanguageSwitch(`${SITE}/`, `${SITE}/en/`, { chooser: true })).toBe(true);
        expect(isLanguageSwitch(`${SITE}/`, `${SITE}/ar/`, { chooser: true })).toBe(true);
        expect(isLanguageSwitch(`${SITE}/`, `${SITE}/`, { chooser: true })).toBe(false);
        expect(isLanguageSwitch(`${SITE}/`, `${SITE}/ar/`)).toBe(false);
    });

    it('ignores missing or non-string input', () => {
        expect(isLanguageSwitch(`${SITE}/en/`, null)).toBe(false);
        expect(isLanguageSwitch(undefined, `${SITE}/ar/`)).toBe(false);
        expect(isLanguageSwitch(`${SITE}/en/`, ['https://www.example.test/ar/'])).toBe(false);
    });
});

/** A minimal document/window pair: listeners are captured so tests can fire the events by hand. */
function fakePage({ href = `${SITE}/en/faq/`, lang = 'en', page = 'faq', langLinks = LOCALES } = {}) {
    const on = { document: {}, window: {} };
    const classes = new Set();
    const root = {
        lang,
        dataset: { page },
        classList: { add: (c) => classes.add(c), remove: (c) => classes.delete(c), contains: (c) => classes.has(c) },
        querySelectorAll: () => langLinks.map((code) => ({ dataset: { langSwitch: code } })),
    };
    globalThis.document = { documentElement: root, addEventListener: (type, fn) => { on.document[type] = fn; } };
    globalThis.window = { location: { href }, addEventListener: (type, fn) => { on.window[type] = fn; } };
    viewTransitions();
    return { on, classes };
}

/** A cross-document transition as pageswap hands it over: counts its skipTransition() calls. */
const fakeTransition = () => {
    const transition = { skipped: 0 };
    transition.skipTransition = () => {
        transition.skipped += 1;
    };
    return transition;
};
const swap = (url, transition = fakeTransition()) => ({ viewTransition: transition, activation: url ? { entry: { url } } : null });
const click = (link, extra = {}) => ({ target: { closest: () => link }, button: 0, defaultPrevented: false, ...extra });

describe('view-transitions module', () => {
    afterEach(() => {
        delete globalThis.document;
        delete globalThis.window;
    });

    it('skips the transition of a language switch, from the Navigation API destination', () => {
        const { on, classes } = fakePage();
        const event = swap(`${SITE}/ar/faq/`);
        on.window.pageswap(event);
        expect(event.viewTransition.skipped).toBe(1);
        // No class trick any more: nothing is renamed, nothing slides.
        expect(classes.size).toBe(0);
    });

    it('keeps the transition (the sliding current-page marks) for a navigation in the same language', () => {
        const { on } = fakePage();
        const event = swap(`${SITE}/en/about/`);
        on.window.pageswap(event);
        expect(event.viewTransition.skipped).toBe(0);
    });

    it('does nothing without a view transition (no support, or reduced motion)', () => {
        const { on } = fakePage();
        expect(() => on.window.pageswap(swap(`${SITE}/ar/faq/`, null))).not.toThrow();
    });

    it('falls back to the language link just clicked when the Navigation API is missing', () => {
        const { on } = fakePage();
        on.document.click(click({ href: `${SITE}/ru/faq/`, target: '' }));
        const event = swap(null);
        on.window.pageswap(event);
        expect(event.viewTransition.skipped).toBe(1);
    });

    it('ignores modified clicks and links that open elsewhere', () => {
        const { on } = fakePage();
        on.document.click(click({ href: `${SITE}/ru/faq/`, target: '' }, { metaKey: true }));
        let event = swap(null);
        on.window.pageswap(event);
        expect(event.viewTransition.skipped).toBe(0);
        on.document.click(click({ href: `${SITE}/ru/faq/`, target: '_blank' }));
        event = swap(null);
        on.window.pageswap(event);
        expect(event.viewTransition.skipped).toBe(0);
    });

    it('treats every choice on the root chooser as a language switch', () => {
        const { on } = fakePage({ href: `${SITE}/`, page: 'chooser' });
        const event = swap(`${SITE}/en/`);
        on.window.pageswap(event);
        expect(event.viewTransition.skipped).toBe(1);
    });

    it('forgets a clicked language link after a few seconds', () => {
        const { on } = fakePage();
        const now = performance.now;
        let t = 1000;
        performance.now = () => t;
        try {
            on.document.click(click({ href: `${SITE}/ru/faq/`, target: '' }));
            t += 5000;
            const event = swap(null);
            on.window.pageswap(event);
            expect(event.viewTransition.skipped).toBe(0);
        } finally {
            performance.now = now;
        }
    });
});
