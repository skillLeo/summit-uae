/**
 * Global sheets (resources/views/components/d2/shell/sheets.blade.php: menu, lang, services) and every page
 * sheet: wires [data-sheet-open] / [data-sheet-close] / drag / Esc / Back once for the whole document.
 * The behaviour lives in resources/js/d2/lib/sheet.js (import openSheet / closeSheet from there).
 *
 * The menu, language and services sheets are navigation on small screens: when the window grows into the
 * desktop header (≥ 1280 px, full navigation visible) while one of them is open, it closes so it never hangs
 * over the desktop layout.
 */
import { activeSheet, closeSheet, initSheets } from '../lib/sheet.js';

export const NAVIGATION_SHEETS = ['menu', 'lang', 'services'];

export default function sheets() {
    initSheets();
    const desktop = window.matchMedia('(width >= 1280px)');
    desktop.addEventListener('change', (event) => {
        if (event.matches && NAVIGATION_SHEETS.includes(activeSheet())) closeSheet();
    });
}
