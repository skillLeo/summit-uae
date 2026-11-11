/**
 * Page checks for every page × viewport project: status, html lang/dir, one H1, no horizontal
 * scroll, no missing-content markers, clean console and network, plus screenshots for the visual
 * review in storage/app/qa/screens/. Home pages also get a reduced-motion run and hero checks.
 */
import { test, expect } from '@playwright/test';
import {
  MISSING_MARKER,
  foldShot,
  fontsReady,
  fullShot,
  loadLazyImages,
  openPage,
  qaPages,
  settle,
  statusMessage,
  warn,
  watchPage,
  widthOf,
} from './support/qa.mjs';

const CTA_SELECTORS = ['main a[href*="#consultation-form"]', 'a[href*="#consultation-form"]'];

const lines = (list, format = (x) => JSON.stringify(x)) => list.map((x) => `  - ${format(x)}`).join('\n');

for (const entry of qaPages()) {
  test.describe(entry.slug, () => {
    test.use({ locale: entry.browserLocale });

    test('page checks and screenshots', async ({ page, baseURL }, testInfo) => {
      const watch = watchPage(page, { expectStatus: entry.status });
      const response = await openPage(page, baseURL, entry);
      expect(response?.status(), statusMessage(entry, response)).toBe(entry.status);
      const shot = `${entry.slug}-${widthOf(page)}`;

      await test.step('fonts loaded and hero intro finished', () => settle(page, { intro: true }));

      await test.step(`html lang="${entry.lang}" dir="${entry.dir}"`, async () => {
        const html = await page.evaluate(() => ({
          lang: document.documentElement.getAttribute('lang'),
          dir: document.documentElement.getAttribute('dir'),
        }));
        expect.soft(html.lang, 'html[lang]').toMatch(new RegExp(`^${entry.lang}(-[a-z0-9]+)*$`, 'i'));
        expect.soft(html.dir, 'html[dir]').toBe(entry.dir);
      });

      await test.step('exactly one h1', async () => {
        const h1s = await page
          .locator('h1')
          .evaluateAll((els) => els.map((el) => el.textContent.replace(/\s+/g, ' ').trim().slice(0, 100)));
        expect.soft(h1s.length, `exactly one <h1>, found ${h1s.length}:\n${lines(h1s)}`).toBe(1);
        if (h1s.length) await expect.soft(page.locator('h1').first(), 'the h1 is rendered').toBeVisible();
      });

      await test.step('no horizontal overflow', async () => {
        const o = await page.evaluate((w) => window.__qa.overflow(w), widthOf(page));
        const format = (c) => `${c.el} (${c.past}px past, ${c.width}px wide)`;
        const culprits =
          `elements past the ${o.deviceWidth}px edge now:\n${lines(o.culprits, format) || '  (none)'}` +
          (o.transient.length ? `\nelements that crossed the edge while the page loaded (animations):\n${lines(o.transient, format)}` : '');
        expect
          .soft(
            o.innerWidth,
            `layout viewport ${o.innerWidth}px must equal the device width (meta viewport width=device-width; ` +
              `wider content makes mobile Chrome widen it); ${culprits}`,
          )
          .toBe(o.deviceWidth);
        expect
          .soft(o.scrollWidth, `document scrollWidth ${o.scrollWidth} > innerWidth ${o.innerWidth}; ${culprits}`)
          .toBeLessThanOrEqual(o.innerWidth);
      });

      await test.step(`no missing-content marker ${MISSING_MARKER}`, async () => {
        const m = await page.evaluate((marker) => window.__qa.missingMarkers(marker), MISSING_MARKER);
        expect
          .soft(m.hits.length, `missing approved copy (${MISSING_MARKER}…⟧) in user-facing text:\n${lines(m.hits, (h) => `${h.where}: ${h.text}`)}`)
          .toBe(0);
        if (m.hidden) {
          warn(testInfo, `${m.hidden} ${MISSING_MARKER}…⟧ marker(s) in DOM text that is not rendered yet (closed accordion, sheet or JSON data)`);
        }
      });

      await test.step('screenshots', async () => {
        await foldShot(page, `${shot}-fold.png`);
        await loadLazyImages(page);
        await fullShot(page, `${shot}.png`);
      });

      await test.step('zero console errors and failed requests', async () => {
        await watch.idle();
        expect.soft(watch.count(), `console and network must be clean:\n${watch.report()}`).toBe(0);
      });
    });

    if (!entry.isHome) return;

    test('hero: H1 rendered instantly, CTA 1 above the fold', async ({ page, baseURL }, testInfo) => {
      const response = await openPage(page, baseURL, entry);
      expect(response?.status(), statusMessage(entry, response)).toBe(entry.status);

      const animated = await page.evaluate(() => window.__qa.animationsOn('h1'));
      expect.soft(animated.length, `the H1 is LCP text and is never animated:\n${lines(animated, String)}`).toBe(0);

      await settle(page, { intro: true });
      const cta = await page.evaluate((selectors) => window.__qa.aboveFold(selectors), CTA_SELECTORS);
      expect(cta.found, 'a link to #consultation-form ("Request a Legal Consultation") exists').toBe(true);
      const ok = cta.inside && cta.covered.length === 0;
      const where =
        `CTA 1 ${cta.label} must sit fully inside the first screen: top ${cta.top}, bottom ${cta.bottom}, ` +
        `left ${cta.left}, right ${cta.right} in ${cta.viewportWidth}×${cta.viewportHeight}` +
        (cta.covered.length ? `, covered by ${cta.covered.join(', ')}` : '');
      // The spec fixes this at 390×844; elsewhere a cut-off CTA is reported as a warning.
      if (widthOf(page) === 390) expect.soft(ok, where).toBe(true);
      else if (!ok) warn(testInfo, where);
    });

    test('reduced motion shows the final state at once', async ({ page, baseURL }) => {
      await page.emulateMedia({ reducedMotion: 'reduce' });
      const response = await openPage(page, baseURL, entry);
      expect(response?.status(), statusMessage(entry, response)).toBe(entry.status);
      const shot = `${entry.slug}-${widthOf(page)}-reduced`;

      // Sample right after load, after fonts, and a moment later (intros started from a timer).
      const seen = new Map();
      const sample = async () => {
        for (const a of await page.evaluate(() => window.__qa.runningAnimations())) {
          seen.set(`${a.target} ${a.animation} ${a.timeline}`, a);
        }
      };
      await sample();
      await fontsReady(page);
      await sample();
      await page.waitForTimeout(400);
      await sample();
      const moving = [...seen.values()];
      expect
        .soft(
          moving.length,
          `prefers-reduced-motion: reduce must show the final frame without animating:\n${lines(
            moving,
            (a) => `${a.target}: ${a.animation} (${a.timeline} timeline)`,
          )}`,
        )
        .toBe(0);

      await foldShot(page, `${shot}-fold.png`);
      await loadLazyImages(page);
      await fullShot(page, `${shot}.png`);
    });
  });
}
