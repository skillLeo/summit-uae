/**
 * Global sheets (resources/views/components/d2/shell/sheets.blade.php: menu, lang, services) and every page
 * sheet: wires [data-sheet-open] / [data-sheet-close] / drag / Esc / Back once for the whole document.
 * The behaviour lives in resources/js/d2/lib/sheet.js (import openSheet / closeSheet from there).
 *
 * The menu, language and services sheets are navigation on small screens: when the window grows into the
 * desktop header (≥ 1280 px, full navigation visible) while one of them is open, it closes so it never hangs
 * over the desktop layout.
 *
 * The menu sheet's "Design 1 | Design 2" switch (phones, SUMMIT_DESIGN_SWITCHER; App\Design\D2\Shell::designSwitch())
 * keeps the page, the language and the #anchor exactly like the floating switch (resources/views/design-switch):
 * its links carry the path and query, and the current #hash is added at click time (lib/sheet.js then follows the
 * link from the sheet; the server's redirect keeps the fragment).
 */
import { activeSheet, closeSheet, initSheets } from '../lib/sheet.js';

export const NAVIGATION_SHEETS = ['menu', 'lang', 'services'];

/** The design switch link's href with the page's current #hash in place of any earlier one (as the floating switch). */
export function withHash(href, hash) {
    if (typeof href !== 'string' || !href) return href;
    const base = href.split('#')[0];
    return typeof hash === 'string' && hash.length > 1 && hash[0] === '#' ? base + hash : base;
}

export default function sheets() {
    initSheets();
    const desktop = window.matchMedia('(width >= 1280px)');
    desktop.addEventListener('change', (event) => {
        if (event.matches && NAVIGATION_SHEETS.includes(activeSheet())) closeSheet();
    });

    // Capture phase: the #hash is on the link before lib/sheet.js reads its href (its handler bubbles).
    document.addEventListener(
        'click',
        (event) => {
            const link = event.target instanceof Element ? event.target.closest('a[data-d2-design]') : null;
            if (!link) return;
            // The current design: nothing to switch to (as the floating switch).
            if (link.getAttribute('aria-current') === 'true') {
                event.preventDefault();
                return;
            }
            link.setAttribute('href', withHash(link.getAttribute('href'), window.location.hash));
        },
        true,
    );
}
