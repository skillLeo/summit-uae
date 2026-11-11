// Phase 4 admin flows (docs/briefs/design-2.1-and-admin-cms.md §14.2): each one edits in the admin and checks the
// public Design 2 page as a visitor. They run in order; the teardown restores the approved content.
import { test, expect } from '@playwright/test';
import { signIn, publicHtml, publish, saveDraft, settle, blockItem } from './support.mjs';

const SHOTS = 'storage/app/d2/phase-4/flows';

test.describe.configure({ mode: 'serial' });

test('edit the homepage hero in Arabic and publish: the public Arabic page shows it', async ({ page, browser, baseURL }) => {
  const text = 'نص تجريبي للواجهة الرئيسية من لوحة الإدارة';
  await signIn(page);
  await page.goto('/admin/pages/P01/edit');
  await page.locator('.cms-locale-switcher').getByText('Arabic', { exact: false }).first().click();
  await settle(page);
  await expect(page.getByText('Editing: Arabic')).toBeVisible();

  const hero = blockItem(page, 'Hero (document stack)');
  await hero.locator('.fi-fo-builder-item-header-label').click();
  const lead = hero.locator('.cms-locale-ar:not(.cms-locale--off) textarea, .cms-locale-ar:not(.cms-locale--off) input').first();
  await expect(lead).toBeVisible();
  await expect(lead).toHaveAttribute('dir', 'rtl');
  await lead.fill(text);
  await page.screenshot({ path: `${SHOTS}/1-hero-ar.jpg`, type: 'jpeg', quality: 60 });
  await saveDraft(page);
  expect(await publicHtml(browser, baseURL, '/ar/')).not.toContain(text);
  await publish(page);
  expect(await publicHtml(browser, baseURL, '/ar/')).toContain(text);
  expect(await publicHtml(browser, baseURL, '/en/')).not.toContain(text);
});

test('reorder the homepage blocks', async ({ page, browser, baseURL }) => {
  await signIn(page);
  await page.goto('/admin/pages/P01/edit?locale=en');
  const before = await publicHtml(browser, baseURL, '/en/');
  expect(before.indexOf('id="services"')).toBeLessThan(before.indexOf('id="who-we-help"'));
  const who = blockItem(page, 'Who We Help');
  // two steps up: past "Anatomy of a Document" and "Services grid"
  for (let i = 0; i < 2; i++) {
    await blockItem(page, 'Who We Help').locator('button[title="Move up"], button[aria-label="Move up"]').first().click();
    await settle(page);
  }
  await expect(who).toBeVisible();
  await page.screenshot({ path: `${SHOTS}/2-reorder.jpg`, type: 'jpeg', quality: 60 });
  await saveDraft(page);
  await publish(page);
  const after = await publicHtml(browser, baseURL, '/en/');
  expect(after.indexOf('id="who-we-help"')).toBeLessThan(after.indexOf('id="services"'));
});

test('hide a block', async ({ page, browser, baseURL }) => {
  await signIn(page);
  await page.goto('/admin/pages/P01/edit?locale=en');
  expect(await publicHtml(browser, baseURL, '/en/')).toContain('id="questions"');
  const faq = blockItem(page, 'Questions');
  await faq.locator('.fi-fo-builder-item-header-label').click();
  await faq.getByText('Block settings').click();
  await faq.getByRole('switch', { name: 'On' }).click();
  await settle(page);
  await saveDraft(page);
  await expect(blockItem(page, 'Questions').locator('.fi-fo-builder-item-header-label')).toContainText('(Off)');
  await publish(page);
  const html = await publicHtml(browser, baseURL, '/en/');
  expect(html).not.toContain('id="questions"');
  expect(html).toContain('id="consultation-form"');
});

test('create, publish and delete a guide', async ({ page, browser, baseURL }) => {
  await signIn(page);
  await page.goto('/admin/guides/create?locale=en');
  await page.getByLabel('Anchor').fill('qa-flow-guide');
  await page.getByLabel('Order').fill('10');
  await page.locator('.cms-locale-en:not(.cms-locale--off)').filter({ hasText: 'Guide title' }).locator('input').fill('A guide written in the admin');
  await page.locator('.cms-locale-en:not(.cms-locale--off)').filter({ hasText: 'Legal Resources card title' }).locator('input').fill('Admin flow guide card');
  await page.getByRole('button', { name: 'Create', exact: true }).click();
  await page.waitForURL(/\/admin\/guides\/G\d+\/edit/);
  await settle(page);
  expect(await publicHtml(browser, baseURL, '/en/resources/')).not.toContain('id="qa-flow-guide"');
  await publish(page);
  expect(await publicHtml(browser, baseURL, '/en/resources/')).toContain('id="qa-flow-guide"');
  await page.screenshot({ path: `${SHOTS}/4-guide.jpg`, type: 'jpeg', quality: 60 });

  await page.getByRole('button', { name: 'Actions' }).click();
  await page.getByRole('menuitem', { name: 'Move to Trash' }).click();
  await page.locator('.fi-modal-window').getByRole('button', { name: /Move to Trash|Confirm|Delete/ }).last().click();
  await page.waitForURL(/\/admin\/guides(\?|$)/);
  expect(await publicHtml(browser, baseURL, '/en/resources/')).not.toContain('id="qa-flow-guide"');
});

test('upload a team photo and set its focal point', async ({ page, browser, baseURL }) => {
  await signIn(page);
  await page.goto('/admin/team/team-ahmed/edit?locale=en');
  const upload = page.locator('input[type=file]').first();
  await upload.setInputFiles('public/images/team/ahmed-mohammadi-800.jpg');
  // FilePond uploads, then the form can be saved
  await page.waitForTimeout(2500);
  await settle(page);
  const x = page.getByRole('spinbutton', { name: /Across/ }).first();
  const y = page.getByRole('spinbutton', { name: /Down/ }).first();
  await x.fill('52');
  await y.fill('30');
  await settle(page);
  await saveDraft(page);
  await publish(page);
  await page.screenshot({ path: `${SHOTS}/5-team.jpg`, type: 'jpeg', quality: 60 });
  const html = await publicHtml(browser, baseURL, '/en/about/');
  expect(html).toMatch(/\/storage\/[^"']+team[^"']*|\/storage\/\d+\//);
  expect(html).toContain('--portrait-x:52%');
});

test('change office hours and see the contact block update', async ({ page, browser, baseURL }) => {
  await signIn(page);
  const read = async () => {
    const html = await publicHtml(browser, baseURL, '/en/');
    const m = html.match(/id="d2-hours-copy"[^>]*>([^<]+)</);
    return JSON.parse(m[1].replace(/&quot;/g, '"').replace(/&amp;/g, '&')).hours;
  };
  expect((await read()).close).toBe('18:00');
  await page.goto('/admin/settings/office-hours');
  const close = page.getByLabel(/Clos/i).first();
  await close.fill('17:00');
  await page.getByRole('button', { name: /^Save/ }).first().click();
  await settle(page);
  expect((await read()).close).toBe('17:00');
});

test('change WA01 and see every WhatsApp link update', async ({ page, browser, baseURL }) => {
  await signIn(page);
  const draft = 'Hello Summit Legal Consultancy, this is a test of the WA01 draft.';
  await page.goto('/admin/settings/whatsapp');
  const en = page.locator('.cms-locale-en textarea, textarea[lang="en"]').first();
  await en.fill(draft);
  await page.getByRole('button', { name: /^Save/ }).first().click();
  await settle(page);
  // Pages without a page-specific approved WhatsApp message (the practice-area pages carry their own DOCX draft, which
  // equals WA01 today: an owner decision in docs/d2.1/phase-4/README.md).
  for (const path of ['/en/', '/en/contact/', '/en/about/', '/en/faq/']) {
    const html = await publicHtml(browser, baseURL, path);
    const hrefs = [...html.matchAll(/href="(https:\/\/wa\.me\/[^"]+)"/g)].map((m) => m[1].replace(/&amp;/g, '&'));
    expect(hrefs.length, path).toBeGreaterThan(0);
    for (const href of hrefs) {
      expect(decodeURIComponent(href.split('?text=')[1] || ''), path).toBe(draft);
    }
  }
});
