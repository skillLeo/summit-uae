// Phase 6 admin flow (docs/briefs/design-2.1-and-admin-cms.md §14.2): run reports with filters and export.
// Opens /admin/reports, picks 90 days, sets a filter (site language English), switches tabs, toggles a table view,
// exports the CSV and checks that the download's rows are exactly the table on screen. Reads the local demo requests;
// writes nothing (the filters live in the URL and the session).
import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import { signIn, settle } from './support.mjs';

const SHOTS = 'storage/app/d2/phase-6/reports';

/** A minimal RFC 4180 parser (quoted cells, doubled quotes), BOM removed. */
function parseCsv(text) {
  const src = text.replace(/^﻿/, '');
  const rows = [];
  let row = [];
  let cell = '';
  let quoted = false;
  for (let i = 0; i < src.length; i++) {
    const c = src[i];
    if (quoted) {
      if (c === '"' && src[i + 1] === '"') { cell += '"'; i++; } else if (c === '"') quoted = false; else cell += c;
    } else if (c === '"') quoted = true;
    else if (c === ',') { row.push(cell); cell = ''; } else if (c === '\n' || c === '\r') {
      if (c === '\r' && src[i + 1] === '\n') i++;
      row.push(cell); rows.push(row); row = []; cell = '';
    } else cell += c;
  }
  if (cell !== '' || row.length) { row.push(cell); rows.push(row); }
  return rows;
}

/** The table a card shows: header and body rows as trimmed text. */
async function tableOnScreen(card, key) {
  return card.locator(`table[data-report-table="${key}"]`).evaluate((table) => {
    const text = (el) => el.textContent.replace(/\s+/g, ' ').trim();
    const head = [...table.querySelectorAll('thead th')].map(text);
    const body = [...table.querySelectorAll('tbody tr')].map((tr) => [...tr.querySelectorAll('th, td')].map(text));
    return [head, ...body];
  });
}

async function exportCsv(page, card, key) {
  const [download] = await Promise.all([
    page.waitForEvent('download'),
    card.locator(`[data-report-csv="${key}"]`).click(),
  ]);
  expect(download.suggestedFilename()).toMatch(new RegExp(`^summit-report-${key.replace('_', '-')}-\\d{4}-\\d{2}-\\d{2}-to-\\d{4}-\\d{2}-\\d{2}\\.csv$`));
  const file = await download.path();
  const bytes = fs.readFileSync(file);
  expect([...bytes.subarray(0, 3)]).toEqual([0xef, 0xbb, 0xbf]); // UTF-8 with BOM
  return parseCsv(bytes.toString('utf8'));
}

test('run reports with filters and export', async ({ page }) => {
  fs.mkdirSync(SHOTS, { recursive: true });
  await signIn(page);
  await page.goto('/admin/reports?tab=volume&filters[range]=30d');
  await settle(page);
  await expect(page.getByRole('heading', { name: 'Reports', level: 1 })).toBeVisible();

  // 90 days, then a filter: site language English
  await page.getByLabel('Date range').selectOption('90d');
  await settle(page);
  await expect(page.locator('[data-report-summary]')).toContainText('90 days');
  await page.getByLabel('Site language').selectOption('en');
  await settle(page);
  await expect(page.locator('[data-report-summary]')).toContainText('Site language: English');
  await expect(page).toHaveURL(/filters\[range\]=90d|filters%5Brange%5D=90d/);

  // Volume: per week beyond 31 days; Chart | Table; the CSV is the table on screen
  const volume = page.locator('[data-report-card="volume"]');
  await expect(volume.locator('canvas')).toBeVisible();
  await volume.locator('[data-report-mode="table"]').click();
  await expect(volume.locator('table[data-report-table="volume"]')).toBeVisible();
  await expect(volume.locator('canvas')).toBeHidden();
  const volumeTable = await tableOnScreen(volume, 'volume');
  expect(volumeTable[0][0]).toBe('Week starting');
  expect(volumeTable.length).toBeGreaterThan(12);
  expect(await exportCsv(page, volume, 'volume')).toEqual(volumeTable);
  await page.screenshot({ path: `${SHOTS}/flow-volume-table.jpg`, type: 'jpeg', quality: 70, fullPage: true });

  // Mix tab: matter type, sorted, "Other matter" and "Not chosen" last; its CSV too
  await page.locator('[data-report-tab="mix"]').click();
  await settle(page);
  await expect(page).toHaveURL(/tab=mix/);
  const matter = page.locator('[data-report-card="matter"]');
  await expect(matter.getByRole('heading', { name: 'By matter type' })).toBeVisible();
  await matter.locator('[data-report-mode="table"]').click();
  const matterTable = await tableOnScreen(matter, 'matter');
  const names = matterTable.slice(1).map((r) => r[0]);
  const counts = matterTable.slice(1).map((r) => Number(r[1].replace(/,/g, '')));
  const ranked = counts.slice(0, names.findIndex((n) => n === 'Other matter' || n === 'Not chosen'));
  expect([...ranked].sort((a, b) => b - a)).toEqual(ranked);
  expect(await exportCsv(page, matter, 'matter')).toEqual(matterTable);

  // Sources tab: a table with bars in cells; its CSV equals it
  await page.locator('[data-report-tab="sources"]').click();
  await settle(page);
  const pages = page.locator('[data-report-card="source_pages"]');
  await expect(pages.locator('table[data-report-table="source_pages"] .slc-report-bar__fill').first()).toBeVisible();
  const pagesTable = await tableOnScreen(pages, 'source_pages');
  expect(pagesTable.length).toBeLessThanOrEqual(1 + 12); // top 10, Other, None recorded
  expect(await exportCsv(page, pages, 'source_pages')).toEqual(pagesTable);

  // Workflow tab renders its tiles and charts
  await page.locator('[data-report-tab="workflow"]').click();
  await settle(page);
  await expect(page.locator('[data-kpi="within-target"]')).toBeVisible();
  await expect(page.locator('[data-kpi="spam-rate"]')).toBeVisible();
  await expect(page.locator('[data-report-card="pipeline"] canvas')).toBeVisible();

  // a reload keeps the filters (URL and session)
  await page.reload();
  await settle(page);
  await expect(page.locator('[data-report-summary]')).toContainText('Site language: English');
  await expect(page.locator('[data-report-summary]')).toContainText('90 days');

  // put the defaults back for the next run
  await page.locator('[data-report-reset]').click();
  await settle(page);
  await expect(page.locator('[data-report-summary]')).toContainText('30 days');
});
