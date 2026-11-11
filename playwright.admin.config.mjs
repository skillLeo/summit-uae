// Phase 4 admin flows (docs/briefs/design-2.1-and-admin-cms.md §14.2 "Playwright admin flows"): one worker, in order,
// against the local server of this tree; the global setup snapshots the settings, the teardown puts every record,
// setting and upload back (scripts/qa/cms-reset.php). Signs in with storage/app/d2/admin/credentials.txt.
//   QA_BASE_URL=http://127.0.0.1:8042 npx playwright test --config playwright.admin.config.mjs
import { defineConfig } from '@playwright/test';

const baseURL = process.env.QA_BASE_URL || 'http://127.0.0.1:8042';

export default defineConfig({
  testDir: 'tests/e2e-admin',
  outputDir: 'storage/app/d2/phase-4/flows/results',
  globalSetup: './tests/e2e-admin/setup.mjs',
  globalTeardown: './tests/e2e-admin/teardown.mjs',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 180_000,
  expect: { timeout: 15_000 },
  reporter: [['list']],
  use: {
    channel: 'chrome',
    baseURL,
    storageState: 'storage/app/d2/phase-4/flows/admin-state.json',
    viewport: { width: 1440, height: 1000 },
    timezoneId: 'Asia/Dubai',
    trace: 'off',
    screenshot: 'only-on-failure',
  },
});
