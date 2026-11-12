import { expect } from '@playwright/test';


/** The run signs in once (setup.mjs); each flow starts from the admin with that session. */
export async function signIn(page) {
  await page.goto('/admin');
  await expect(page).not.toHaveURL(/\/login/);
}

/** A public page in Design 2 as a visitor (no admin session, no switcher): the server's HTML. */
export async function publicHtml(browser, baseURL, path) {
  const { request } = await import('@playwright/test');
  const ctx = await request.newContext({ extraHTTPHeaders: { Cookie: 'summit_design=2; summit_qa=1' } });
  const res = await ctx.get(`${baseURL}${path}`, { maxRedirects: 0 });
  expect(res.status(), path).toBe(200);
  const html = await res.text();
  await ctx.dispose();
  return html;
}

/** Wait for Livewire to settle after an action. */
export async function settle(page) {
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(300);
}

/** Publish from the Publishing panel (confirmation modal). */
export async function publish(page) {
  await page.locator('.cms-publish-panel').getByRole('button', { name: /^Publish$/ }).click();
  const modal = page.locator('.fi-modal-window').filter({ hasText: 'Publish this record?' });
  await expect(modal).toBeVisible();
  await modal.getByRole('button', { name: /^Publish$/ }).click();
  await expect(page.getByText('Published. The site shows the new version.')).toBeVisible();
  await settle(page);
}

export async function saveDraft(page) {
  await page.getByRole('button', { name: 'Save draft' }).click();
  await expect(page.getByText('Draft saved.').first()).toBeVisible();
  await settle(page);
}

/** The builder item of a block, by its label. */
export function blockItem(page, label) {
  return page.locator('.fi-fo-builder-item').filter({ has: page.locator('.fi-fo-builder-item-header-label', { hasText: label }) }).first();
}

/** Save a settings screen: Save, then the confirmation dialog (Phase 7: settings saves are confirmed and logged). */
export async function saveSettings(page) {
  await page.getByRole('button', { name: /^Save/ }).first().click();
  const modal = page.locator('.fi-modal-window').filter({ has: page.getByRole('heading') }).last();
  await expect(modal).toBeVisible();
  await modal.locator('button[type=submit], .fi-modal-footer-actions button').filter({ hasText: /Save/ }).last().click();
  await expect(modal).toBeHidden();
  await settle(page);
}
