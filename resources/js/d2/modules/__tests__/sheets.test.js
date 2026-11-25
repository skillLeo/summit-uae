import { describe, expect, it } from 'vitest';
import { NAVIGATION_SHEETS } from '../sheets.js';

// Lives outside modules/*.js on purpose: app.js eagerly imports every modules/*.js file.
describe('global sheets', () => {
    it('closes the menu, language and services sheets when the desktop navigation takes over', () => {
        expect(NAVIGATION_SHEETS).toEqual(['menu', 'lang', 'services']);
    });
});
