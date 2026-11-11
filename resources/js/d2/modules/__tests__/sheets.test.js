import { describe, expect, it } from 'vitest';
import { NAVIGATION_SHEETS, withHash } from '../sheets.js';

// Lives outside modules/*.js on purpose: app.js eagerly imports every modules/*.js file.
describe('global sheets', () => {
    it('closes the menu, language and services sheets when the desktop navigation takes over', () => {
        expect(NAVIGATION_SHEETS).toEqual(['menu', 'lang', 'services']);
    });

    it("keeps the #anchor on the menu sheet's design switch (as the floating switch does)", () => {
        expect(withHash('/en/faq/?design=1', '#faq-03')).toBe('/en/faq/?design=1#faq-03');
        expect(withHash('/ar/?q=1&design=2', '#consultation-form')).toBe('/ar/?q=1&design=2#consultation-form');
        // No hash or an empty one: the plain link; an earlier hash (a page restored from the back-forward cache) is
        // replaced by the current one.
        expect(withHash('/en/faq/?design=1', '')).toBe('/en/faq/?design=1');
        expect(withHash('/en/faq/?design=1', '#')).toBe('/en/faq/?design=1');
        expect(withHash('/en/faq/?design=1#top', '#faq-03')).toBe('/en/faq/?design=1#faq-03');
        expect(withHash('/en/faq/?design=1#top', '')).toBe('/en/faq/?design=1');
        expect(withHash(null, '#x')).toBe(null);
    });
});
