import { defineConfig } from 'vitest/config';

// Design 2 unit tests only (pure JS: calculators, office hours, search normalisation …).
// tests/e2e holds Design 1's Playwright specs, which Vitest must never collect.
export default defineConfig({
    test: {
        include: ['resources/js/d2/**/*.test.js'],
        environment: 'node',
        passWithNoTests: true,
    },
});
