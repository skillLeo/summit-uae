import { describe, expect, it } from 'vitest';
import { WORKER, shouldRegister } from '../pwa.js';

// Lives outside modules/*.js on purpose: app.js eagerly imports every modules/*.js file.
// Design 2 PWA: one service worker for both designs (Design 1's frozen /sw.js), only when the page opts in.
describe('pwa registration', () => {
    const doc = (pwa) => ({ documentElement: { hasAttribute: (name) => pwa && name === 'data-pwa' } });
    const win = (overrides = {}) => ({ isSecureContext: true, navigator: { serviceWorker: {} }, document: doc(true), ...overrides });

    it('registers Design 1 worker script, never a second one', () => {
        expect(WORKER).toBe('/sw.js');
    });

    it('registers only on <html data-pwa> in a secure context with service worker support', () => {
        expect(shouldRegister(win())).toBe(true);
        expect(shouldRegister(win({ document: doc(false) }))).toBe(false);
        expect(shouldRegister(win({ isSecureContext: false }))).toBe(false);
        expect(shouldRegister(win({ navigator: {} }))).toBe(false);
        expect(shouldRegister(undefined)).toBe(false);
    });
});
