import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

/**
 * The Alpine CSP build reads every global once at start-up. Reading `window.fence` makes Chrome log
 * a "Fenced Frame API is deprecated" warning (Lighthouse best practices). Alpine already skips
 * `styleMedia` and `sharedStorage` for the same reason; this adds `fence` to that list. The build
 * fails if the upstream code changes, so the patch can never silently stop applying.
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
                this.error('@alpinejs/csp changed: review the deprecated-globals patch in vite.config.js');
            }
            return { code: code.replace(needle, 'if (key === "styleMedia" || key === "sharedStorage" || key === "fence")'), map: null };
        },
    };
}

export default defineConfig({
    plugins: [
        alpineCspSkipDeprecatedGlobals(),
        laravel({
            input: ['resources/scss/app.scss', 'resources/scss/styleguide.scss', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                // Bootstrap 5.3 still uses @import and global Sass functions.
                quietDeps: true,
                silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'mixed-decls', 'if-function'],
            },
        },
    },
    build: {
        modulePreload: { polyfill: false },
        reportCompressedSize: true,
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**', '**/storage/app/**'],
        },
    },
});
