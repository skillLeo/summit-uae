/**
 * Keyboard access: skip link first, visible focus with a ring on the first 25 Tab stops, 48×48
 * targets in the mobile bottom bar (390 px) and every button reachable with Tab.
 */
import { test, expect } from '@playwright/test';
import { QA_DIR, fontsReady, isIgnoredUrl, openPage, qaPages, settle, statusMessage, widthOf, writeJson } from './support/qa.mjs';

const FIRST_STOPS = 25;
const MIN_TARGET = 48; // CSS px on mobile (spec: targets >= 48 px)
const MAX_PRESSES = 800;

const lines = (list, format) => list.map((x) => `  - ${format(x)}`).join('\n');
const focused = (page) => page.evaluate(() => window.__qa.inspectFocus());

for (const entry of qaPages()) {
  test.describe(entry.slug, () => {
    test.use({ locale: entry.browserLocale });

    test.beforeEach(async ({ page, baseURL }) => {
      const response = await openPage(page, baseURL, entry);
      expect(response?.status(), statusMessage(entry, response)).toBe(entry.status);
      await fontsReady(page);
    });

    test('first Tab stop is a visible skip link that focuses #main-content', async ({ page }) => {
      const targets = await page.locator('#main-content').count();
      expect.soft(targets, 'exactly one #main-content element (the skip link target)').toBe(1);

      await page.keyboard.press('Tab');
      const first = await focused(page);
      expect(first.body, 'Tab moves focus into the page').toBe(false);
      const isSkipLink = first.tag === 'a' && /#main-content$/.test(first.href ?? '');
      expect(isSkipLink, `first Tab stop must be the skip link to #main-content, got ${first.label} (href ${first.href})`).toBe(true);
      expect.soft(first.visible.ok, `the skip link is visible when focused: ${first.visible.reason}`).toBe(true);
      expect.soft(first.ring.ok, `the skip link shows a focus indicator: ${first.ring.detail}`).toBe(true);

      await page.keyboard.press('Enter');
      const landed = await page
        .waitForFunction(() => document.activeElement?.id === 'main-content', null, { timeout: 2000 })
        .then(() => true, () => false);
      const now = await page.evaluate(() => window.__qa.describe(document.activeElement));
      expect.soft(landed, `activating the skip link focuses #main-content (focus is on ${now}; give <main> tabindex="-1")`).toBe(true);
    });

    test(`first ${FIRST_STOPS} Tab stops are visible and show a focus indicator`, async ({ page }, testInfo) => {
      await settle(page, { intro: true });
      const stops = [];
      for (let i = 1; i <= FIRST_STOPS; i += 1) {
        await page.keyboard.press('Tab');
        const stop = await focused(page);
        if (stop.body) break; // wrapped past the last stop: the page has fewer than 25
        stops.push({ n: i, ...stop });
      }
      writeJson(`${QA_DIR}/keyboard/${entry.slug}-${widthOf(page)}-tab-stops.json`, stops);
      testInfo.annotations.push({ type: 'tab stops', description: stops.map((s) => `#${s.n} ${s.label}`).join(' · ') });

      expect(stops.length, 'the page has keyboard-focusable elements').toBeGreaterThan(0);
      // Cross-origin frames (Turnstile) and browser-internal controls (a date input's calendar
      // button) draw focus where the page cannot inspect it.
      const own = stops.filter((s) => !s.iframe && !s.internal);
      const internal = stops.filter((s) => s.internal);
      if (internal.length) {
        testInfo.annotations.push({
          type: 'not inspectable',
          description: `browser-internal focus (ring drawn by Chrome): ${internal.map((s) => `#${s.n} ${s.label}`).join(', ')}`,
        });
      }
      const foreign = stops.filter((s) => s.iframe && !isIgnoredUrl(s.src));
      const hidden = own.filter((s) => !s.visible.ok);
      const noRing = own.filter((s) => !s.ring.ok);
      const thin = own.filter((s) => s.ring.ok && s.ring.thin);
      expect.soft(hidden.length, `focused but not visible:\n${lines(hidden, (s) => `#${s.n} ${s.label}: ${s.visible.reason}`)}`).toBe(0);
      expect
        .soft(noRing.length, `no focus indicator (outline or box-shadow):\n${lines(noRing, (s) => `#${s.n} ${s.label}: ${s.ring.detail}`)}`)
        .toBe(0);
      expect.soft(thin.length, `focus ring thinner than the 2px spec:\n${lines(thin, (s) => `#${s.n} ${s.label}: ${s.ring.detail}`)}`).toBe(0);
      expect.soft(foreign.length, `focus entered frames that cannot be checked:\n${lines(foreign, (s) => `#${s.n} ${s.src}`)}`).toBe(0);
    });

    test.describe('mobile shell', () => {
      test.skip(({ viewport }) => viewport?.width !== 390, 'the mobile bottom bar is measured at 390 px');

      test(`bottom navigation targets are at least ${MIN_TARGET}×${MIN_TARGET} CSS px`, async ({ page }) => {
        await settle(page, { intro: false });
        const nav = await page.evaluate(() => window.__qa.bottomNav());
        expect(
          nav.found,
          'a bottom navigation bar fixed to the bottom of the viewport (spec: 64px bar, 5 items)' +
            (nav.candidates?.length ? `; found but not pinned to the bottom: ${nav.candidates.join(', ')}` : ''),
        ).toBe(true);
        expect(nav.targets.length, `links/buttons inside ${nav.nav}`).toBeGreaterThan(0);
        const small = nav.targets.filter((t) => t.width < MIN_TARGET - 0.01 || t.height < MIN_TARGET - 0.01);
        expect(
          small.length,
          `targets under ${MIN_TARGET}×${MIN_TARGET} in ${nav.nav}:\n${lines(small, (t) => `${t.label}: ${t.width}×${t.height}`)}`,
        ).toBe(0);
      });
    });

    test('every button is reachable with Tab', async ({ page }) => {
      test.setTimeout(150_000);
      await settle(page, { intro: false });
      const total = await page.evaluate(() => window.__qa.startFocusLog());
      test.skip(total === 0, 'no buttons on this page');

      let state = { wrapped: false, presses: 0, stops: 0 };
      for (let presses = 0; presses < MAX_PRESSES && !state.wrapped; presses += 10) {
        for (let i = 0; i < 10; i += 1) await page.keyboard.press('Tab');
        state = await page.evaluate(() => window.__qa.focusLogState());
      }
      const unreached = await page.evaluate(() => window.__qa.unreachedButtons());
      expect(
        unreached.length,
        `${unreached.length} of ${total} visible buttons were never focused in ${state.stops} Tab stops` +
          `${state.wrapped ? '' : ` (stopped after ${MAX_PRESSES} presses without wrapping)`}:\n` +
          lines(unreached, (b) => `${b.label}: ${b.reason}\n      ${b.html}`),
      ).toBe(0);
    });
  });
}
