// Phase 5 admin flow (docs/briefs/design-2.1-and-admin-cms.md §14.2): triage a request using only the keyboard.
// G then R opens the inbox, J focuses the first request, Enter opens it, A assigns, S changes the status, N adds a
// note, J goes to the next request. The request is a QA fixture (scripts/qa/requests-fixture.php), removed afterwards.
import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { signIn, settle } from './support.mjs';

const SHOTS = 'storage/app/d2/phase-5/flows';
const php = (...args) => execFileSync('php', ['scripts/qa/requests-fixture.php', ...args]).toString().trim();

test.describe.configure({ mode: 'serial' });

let id;
test.beforeAll(() => { php('remove'); id = php('create'); });
test.afterAll(() => { php('remove'); });

/** Tab until the focused element's text matches (a RegExp), or it is a form control of that type (a string). */
async function tabTo(page, what, max = 30) {
  for (let i = 0; i < max; i++) {
    const hit = await page.evaluate((w) => {
      const el = document.activeElement;
      if (!el || !el.closest('.fi-modal-window, .fi-modal')) return false;
      if (w.type) return el.tagName === 'SELECT' ? w.type === 'select' : el.type === w.type;
      return new RegExp(w.re).test((el.textContent || '').trim());
    }, typeof what === 'string' ? { type: what } : { re: what.source });
    if (hit) return;
    await page.keyboard.press('Tab');
  }
  throw new Error(`no focusable element matching ${what}`);
}

test('triage a request using only the keyboard', async ({ page }) => {
  await signIn(page);
  await page.locator('body').click({ position: { x: 1, y: 1 } }).catch(() => {});
  await page.evaluate(() => document.activeElement instanceof HTMLElement && document.activeElement.blur());

  // G then R: the inbox (New), J focuses the first row, Enter opens it
  await page.keyboard.press('g');
  await page.keyboard.press('r');
  await page.waitForURL(/\/admin\/requests$/);
  await settle(page);
  await page.keyboard.press('j');
  await expect(page.locator('[data-inbox="list"] .fi-ta-row').first().locator('a:focus')).toHaveCount(1);
  await page.keyboard.press('Enter');
  await page.waitForURL(new RegExp(`/admin/requests/${id}$`));
  await settle(page);
  await expect(page.getByText('QA Keyboard Flow').first()).toBeVisible();

  // A: assign to me (type-ahead on the select), Enter submits
  await page.keyboard.press('a');
  const assign = page.locator('.fi-modal-window').filter({ has: page.locator('select') }).last();
  await expect(assign).toBeVisible();
  await page.waitForTimeout(500);
  await tabTo(page, 'select');
  await page.keyboard.type('Local');
  await tabTo(page, /^Submit$|^Assign$/);
  await page.keyboard.press('Enter');
  await expect(page.getByText(/Assigned to/).first()).toBeVisible();
  await settle(page);

  // S: status In review (each status is a toggle button: Tab to it, Space selects), then submit
  await page.keyboard.press('s');
  const status = page.locator('.fi-modal-window').filter({ hasText: 'Change status' }).last();
  await expect(status).toBeVisible();
  await page.waitForTimeout(500); // the modal's focus trap settles after its transition
  await tabTo(page, /^In review$/);
  await page.keyboard.press('Space');
  await tabTo(page, /^Submit$|^Change status$/);
  await page.keyboard.press('Enter');
  await expect(page.getByText('Status changed to “In review”.')).toBeVisible();
  await settle(page);

  // N: a note, Tab to its button, Enter
  await page.keyboard.press('n');
  const note = page.locator('.fi-modal-window textarea').last();
  await expect(note).toBeFocused();
  await page.keyboard.type('Called the sender, documents requested.');
  await tabTo(page, /^Add note$/);
  await page.keyboard.press('Enter');
  await expect(page.getByText('Note added.')).toBeVisible();
  await settle(page);
  await page.screenshot({ path: `${SHOTS}/triage-keyboard.jpg`, type: 'jpeg', quality: 60 });

  const state = JSON.parse(php('state', id));
  expect(state.status).toBe('in_review');
  expect(state.assignee_id).not.toBeNull();
  expect(state.notes).toEqual(['Called the sender, documents requested.']);
  expect(state.first_reviewed_at).not.toBeNull();

  // ? opens the shortcuts sheet, Escape closes it
  await page.keyboard.press('Shift+Slash');
  await expect(page.locator('#slc-shortcuts')).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(page.locator('#slc-shortcuts')).toBeHidden();

  // J: the next request of the inbox view
  await page.keyboard.press('j');
  await page.waitForURL((url) => /\/admin\/requests\/\d+$/.test(url.pathname) && !url.pathname.endsWith(`/${id}`));
});
