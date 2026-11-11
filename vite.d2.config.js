import { readdirSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

/**
 * Design 2 build (docs/upgrade-plan.md). Separate from vite.config.js (Design 1) on purpose: no chunk
 * is ever shared between the designs, Design 1's bundles stay byte-identical to the design-1 tag, and
 * Design 2 pages never load Design 1 CSS/JS (or the reverse).
 *
 *   npm run dev:d2    → Vite dev server, writes public/hot-d2
 *   npm run build:d2  → public/build-d2
 *
 * Same Alpine CSP patch as Design 1 (see vite.config.js): skip reading the deprecated `window.fence`.
 */
function alpineCspSkipDeprecatedGlobals() {
    const needle = 'if (key === "styleMedia" || key === "sharedStorage")';
    return {
        name: 'summit:alpine-csp-skip-deprecated-globals',
        enforce: 'pre',
        transform(code, id) {
            if (!id.includes('@alpinejs/csp')) return null;
            if (!code.includes('getOwnPropertyNames(globalThis)')) return null;
            if (!code.includes(needle)) {
                this.error('@alpinejs/csp changed: review the deprecated-globals patch in vite.d2.config.js');
            }
            return { code: code.replace(needle, 'if (key === "styleMedia" || key === "sharedStorage" || key === "fence")'), map: null };
        },
    };
}

/**
 * Performance (brief 2.1 §6: per page at most 100 KB of JS gzip): resources/js/d2/app.js registers the shell's Alpine
 * components at once and fetches every other component (its own chunk) only when the page uses it. This virtual
 * module tells it which x-data names each component file registers, read from the sources at build time (and again
 * in the dev server whenever a component file is added, changed or removed), so no hand-kept list can go stale:
 *   import componentNames from 'virtual:d2-component-names';
 *   componentNames['./components/faq.js'] → { names: ['d2Faq'], always: false }
 * always: the file registers no x-data name but has a default export (page wiring): it runs on every page.
 * A name the map does not know makes app.js load every component (resources/js/d2/lib/lazy-components.js).
 */
function d2ComponentNames() {
    const id = 'virtual:d2-component-names';
    const resolved = `\0${id}`;
    const dir = path.resolve('resources/js/d2/components');
    const isComponent = (file) => file.startsWith(dir + path.sep) && file.endsWith('.js') && !file.endsWith('.test.js');
    return {
        name: 'summit:d2-component-names',
        resolveId(source) {
            return source === id ? resolved : null;
        },
        load(source) {
            if (source !== resolved) return null;
            const map = {};
            for (const file of readdirSync(dir).sort()) {
                if (!file.endsWith('.js') || file.endsWith('.test.js')) continue;
                const full = path.join(dir, file);
                this.addWatchFile(full);
                const code = readFileSync(full, 'utf8');
                const names = [...new Set([...code.matchAll(/Alpine\.data\(\s*['"`]([A-Za-z_$][\w$]*)['"`]/g)].map((m) => m[1]))];
                map[`./components/${file}`] = { names, always: names.length === 0 && /export\s+default\b/.test(code) };
            }
            return `export default ${JSON.stringify(map)};`;
        },
        configureServer(server) {
            const refresh = (file) => {
                if (!isComponent(path.resolve(file))) return;
                const mod = server.moduleGraph.getModuleById(resolved);
                if (mod) server.moduleGraph.invalidateModule(mod);
            };
            server.watcher.on('add', refresh);
            server.watcher.on('change', refresh);
            server.watcher.on('unlink', refresh);
        },
    };
}

// qa-harness: D2_BUILD_DIR=build-d2-qa builds a production copy into its own folder for
// scripts/qa/lighthouse-d2.mjs, without touching public/build-d2 or public/hot-d2 (default: build-d2).
const qaBuildDirectory = process.env.D2_BUILD_DIR ? { buildDirectory: process.env.D2_BUILD_DIR } : {};

export default defineConfig({
    plugins: [
        alpineCspSkipDeprecatedGlobals(),
        d2ComponentNames(),
        laravel({
            input: [
                'resources/css/d2/app.css',
                'resources/css/d2/styleguide.css',
                'resources/js/d2/app.js',
            ],
            buildDirectory: 'build-d2',
            ...qaBuildDirectory,
            hotFile: 'public/hot-d2',
            refresh: [
                'resources/views/designs/d2/**',
                'resources/views/components/d2/**',
                'lang/*/proposed-d2/**',
            ],
        }),
    ],
    build: {
        modulePreload: { polyfill: false },
        reportCompressedSize: true,
        emptyOutDir: true,
    },
    server: {
        host: '127.0.0.1',
        port: 5183,
        strictPort: true,
        watch: {
            ignored: ['**/storage/framework/views/**', '**/storage/app/**', '**/public/build/**', '**/public/build-d2/**'],
        },
    },
});
