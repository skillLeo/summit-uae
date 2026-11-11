// Design 2 QA harness (upgrade spec §12 Phase G, checklist §9). See qa/README.md.
//
//   npm run qa:d2              visual-* (7 pages × EN/AR/RU × 1440/1280/768/390) + hero frames
//   npm run qa:d2:visual       full-page screenshots and checks only
//   npm run qa:d2:hero         hero frames 0/600/1200/2000/3000 ms, motion and reduced motion
//   npm run qa:d2:variants     reduced motion and JavaScript disabled, 1440 and 390
//   npm run qa:d2:report       qa/report.html from the latest run
//
// Output: qa/shots/<date>/ (screenshots + one JSON of measured checks per capture); Playwright's own HTML
// report and traces: storage/app/qa-d2/. Site: QA_BASE_URL (default http://127.0.0.1:8042), cookies
// summit_design=2 and summit_qa=1, installed Google Chrome, Vite's reload client stubbed.
import { defineConfig } from '@playwright/test';
import { BASE_URL, EXTRA_VARIANTS, PLAYWRIGHT_DIR, VARIANT_WIDTHS, WIDTHS } from './qa/lib/config.mjs';

const workers = process.env.QA_WORKERS
    ? /%$/.test(process.env.QA_WORKERS)
        ? process.env.QA_WORKERS
        : Number(process.env.QA_WORKERS)
    : 4;

const visual = WIDTHS.map((width) => ({
    name: `visual-${width}`,
    testMatch: /visual\.spec\.mjs$/,
    metadata: { width, variant: 'default' },
}));

// Only when QA_D2_VARIANTS is set (npm run qa:d2:variants): reduced motion and no JavaScript.
const variants = process.env.QA_D2_VARIANTS
    ? VARIANT_WIDTHS.flatMap((width) =>
          EXTRA_VARIANTS.map((variant) => ({
              name: `variants-${width}-${variant}`,
              testMatch: /visual\.spec\.mjs$/,
              metadata: { width, variant },
          })),
      )
    : [];

export default defineConfig({
    testDir: 'tests/qa/d2',
    outputDir: `${PLAYWRIGHT_DIR}/test-results`,
    globalSetup: './qa/lib/global-setup.mjs',
    // One verdict for the invocation (qa/lib/verdict.mjs): exits non-zero when any capture fails a check.
    globalTeardown: './qa/lib/verdict.mjs',
    fullyParallel: true,
    forbidOnly: Boolean(process.env.CI),
    retries: 0,
    workers,
    timeout: 180_000,
    expect: { timeout: 10_000 },
    reporter: [['list'], ['html', { outputFolder: `${PLAYWRIGHT_DIR}/playwright-report`, open: 'never' }]],
    use: {
        channel: 'chrome', // the installed Google Chrome; no browser download
        headless: true,
        baseURL: BASE_URL,
        // The specs make their own contexts and write their own evidence (qa/shots/<date>/): no traces.
        trace: 'off',
        screenshot: 'off',
    },
    projects: [
        ...visual,
        ...variants,
        // One worker, one test after another: frame timing suffers least from CPU contention.
        { name: 'hero', testMatch: /hero\.spec\.mjs$/, fullyParallel: false },
        // The harness's own checks on a synthetic page (no site needed).
        { name: 'harness', testMatch: /harness\.spec\.mjs$/ },
    ],
});
