// Summit QA harness (Playwright on the installed Google Chrome). See docs/qa.md.
import { defineConfig } from '@playwright/test';

const baseURL = process.env.QA_BASE_URL || 'http://127.0.0.1:8000';

// php artisan serve answers one request at a time unless PHP_CLI_SERVER_WORKERS is set.
const workers = process.env.QA_WORKERS
  ? /%$/.test(process.env.QA_WORKERS)
    ? process.env.QA_WORKERS
    : Number(process.env.QA_WORKERS)
  : undefined;

export default defineConfig({
  testDir: 'tests/e2e',
  outputDir: 'storage/app/qa/test-results',
  globalSetup: './tests/e2e/support/global-setup.mjs',
  fullyParallel: true,
  forbidOnly: Boolean(process.env.CI),
  retries: 0,
  workers,
  timeout: 90_000,
  expect: { timeout: 10_000 },
  reporter: [['list'], ['html', { outputFolder: 'storage/app/qa/playwright-report', open: 'never' }]],
  use: {
    channel: 'chrome', // the installed Google Chrome; no browser downloads
    baseURL,
    // Never let the internal design switcher (SUMMIT_DESIGN_SWITCHER) appear in QA output. A cookie, not a
    // header: it only reaches this origin, so third-party requests (Turnstile) are unaffected.
    storageState: { cookies: [{ name: 'summit_qa', value: '1', domain: new URL(baseURL).hostname, path: '/', expires: -1, httpOnly: false, secure: false, sameSite: 'Lax' }], origins: [] },
    timezoneId: 'Asia/Dubai',
    colorScheme: 'light',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    {
      name: 'mobile-390',
      use: { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 3 },
    },
    { name: 'tablet-768', use: { viewport: { width: 768, height: 1024 } } },
    { name: 'desktop-1280', use: { viewport: { width: 1280, height: 800 } } },
    { name: 'desktop-1440', use: { viewport: { width: 1440, height: 900 } } },
  ],
});
