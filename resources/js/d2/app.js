/**
 * Design 2 JavaScript entry (vite.d2.config.js → public/build-d2). Never loaded on Design 1 pages.
 *
 * - components/*.js  each `export default (Alpine) => Alpine.data('d2Name', …)`; registered before start.
 * - modules/*.js     each `export default () => { … }`; DOM behaviour run once after Alpine starts.
 * Heavy features (hero deal, command palette, calculators' charts) are dynamic imports inside their
 * component or module, so they never weigh on pages that do not use them.
 * Alpine runs on its CSP build: no inline expressions beyond property names and method calls.
 *
 * Performance (brief 2.1 §6: per page at most 100 KB of JS gzip): the shell's components (the header's menus, the
 * dialogs and the search palette's wiring, and the home hero's stage) are in this bundle; every other component
 * file is its own chunk, fetched before Alpine starts only when the page uses an x-data name it registers
 * (lib/lazy-components.js; the names come from the sources through vite.d2.config.js). A new component file needs
 * nothing more than its Alpine.data() call. No Alpine plugin: Design 2 never used @alpinejs/focus (x-trap, $focus);
 * its sheets and dialogs trap focus with lib/focus.js, so the plugin's 8.5 KB gzip are no longer shipped.
 */
import Alpine from '@alpinejs/csp';
import componentNames from 'virtual:d2-component-names';
import { usedNames, wantedFiles } from './lib/lazy-components.js';

// Test files never ship: a *.test.js beside a component would pull Vitest into the browser bundle.
const shell = import.meta.glob(['./components/{megamenu,langmenu,modal,palette,hero}.js'], { eager: true });
const lazy = import.meta.glob(['./components/*.js', '!./components/*.test.js', '!./components/{megamenu,langmenu,modal,palette,hero}.js']);
const modules = import.meta.glob(['./modules/*.js', '!./modules/*.test.js'], { eager: true });

const register = (mod) => {
    if (mod && typeof mod.default === 'function') mod.default(Alpine);
};

async function start() {
    Object.values(shell).forEach(register);

    const files = wantedFiles(Object.keys(lazy), componentNames, usedNames(document));
    const loaded = await Promise.all(
        files.map((file) =>
            lazy[file]().catch((error) => {
                // A chunk that fails to load leaves its markup as plain HTML; the rest of the page still starts.
                console.error(error);
                return null;
            }),
        ),
    );
    loaded.forEach(register);

    Alpine.start();

    for (const mod of Object.values(modules)) {
        if (typeof mod.default === 'function') {
            try {
                mod.default();
            } catch (error) {
                // One broken enhancement must never take the page down; the HTML works without it.
                console.error(error);
            }
        }
    }
}

start();
