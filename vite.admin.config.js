import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

/**
 * Internal admin panel (Filament, /admin): its theme only. A THIRD build, separate from vite.config.js
 * (Design 1) and vite.d2.config.js (Design 2): no chunk is shared, so neither public design ever loads
 * admin CSS (or the reverse), and Design 1's bundles stay byte-identical to the design-1 tag.
 *
 *   npm run dev:admin    → Vite dev server on 127.0.0.1:5184, writes public/hot-admin
 *   npm run build:admin  → public/build-admin (AdminPanelProvider: ->viteTheme(…, 'build-admin'))
 *
 * The theme (resources/css/filament/admin/theme.css) is Tailwind CSS v4 on Filament's theme, with the
 * Design 2 tokens (resources/css/d2/tokens.css) mapped onto Filament's components.
 */
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/filament/admin/theme.css'],
            buildDirectory: 'build-admin',
            hotFile: 'public/hot-admin',
            refresh: ['app/Filament/**', 'app/Providers/Filament/**', 'lang/*/admin.php'],
        }),
        tailwindcss(),
    ],
    build: {
        reportCompressedSize: true,
        emptyOutDir: true,
    },
    server: {
        host: '127.0.0.1',
        port: 5184,
        strictPort: true,
        watch: {
            ignored: [
                '**/storage/framework/views/**',
                '**/storage/app/**',
                '**/public/build/**',
                '**/public/build-d2/**',
                '**/public/build-admin/**',
            ],
        },
    },
});
