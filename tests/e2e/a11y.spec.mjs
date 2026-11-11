/**
 * axe-core (WCAG 2.0/2.1 A + AA, WCAG 2.2 AA) on every page at 390 and 1440. Any violation fails.
 * Full results per page go to storage/app/qa/axe/<page>-<locale>-<width>.json; a readable summary
 * (rule, impact, target, html) is printed and attached to the HTML report.
 */
import path from 'node:path';
import AxeBuilder from '@axe-core/playwright';
import { test, expect } from '@playwright/test';
import { AXE_DIR, IGNORED_HOSTS, ROOT, openPage, qaPages, settle, statusMessage, widthOf, writeJson } from './support/qa.mjs';

const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];
const AXE_WIDTHS = [390, 1440];

// Third-party frames (Turnstile) are outside our control: their content is not audited, but the
// <iframe> element itself (title, focusability) still is.
const THIRD_PARTY_FRAMES = IGNORED_HOSTS.map((host) => ({ fromFrames: [`iframe[src*="${host}"]`, 'html'] }));

const clip = (s, max = 240) => {
  const one = String(s ?? '').replace(/\s+/g, ' ').trim();
  return one.length > max ? `${one.slice(0, max)}…` : one;
};

const targetOf = (node) => node.target.map((t) => (Array.isArray(t) ? t.join(' >>> ') : t)).join(' | ');

function summarise(label, results, file) {
  const out = [];
  const { violations, incomplete } = results;
  const nodes = violations.reduce((n, v) => n + v.nodes.length, 0);
  out.push(
    `axe ${label}: ${violations.length} violation(s) on ${nodes} element(s), ${incomplete.length} rule(s) need review` +
      `  [${path.relative(ROOT, file)}]`,
  );
  for (const v of violations) {
    out.push(`  VIOLATION [${v.impact}] ${v.id}: ${v.help} (${v.nodes.length})`, `    ${v.helpUrl}`);
    for (const node of v.nodes.slice(0, 12)) {
      out.push(`    - target: ${targetOf(node)}`, `      html:   ${clip(node.html)}`);
      const why = clip(node.failureSummary?.split('\n').slice(1).join(' '), 300);
      if (why) out.push(`      fix:    ${why}`);
    }
    if (v.nodes.length > 12) out.push(`    … ${v.nodes.length - 12} more in the JSON report`);
  }
  for (const r of incomplete) {
    out.push(`  REVIEW    [${r.impact ?? 'n/a'}] ${r.id}: ${r.help} (${r.nodes.length}, not counted as a failure)`);
    for (const node of r.nodes.slice(0, 3)) out.push(`    - target: ${targetOf(node)}`);
  }
  return out.join('\n');
}

test.describe('axe', () => {
  test.skip(({ viewport }) => !AXE_WIDTHS.includes(viewport?.width ?? 0), 'axe runs at 390 and 1440 only');

  for (const entry of qaPages()) {
    test.describe(entry.slug, () => {
      test.use({ locale: entry.browserLocale });

      test('WCAG 2.2 AA: zero axe violations', async ({ page, baseURL }, testInfo) => {
        const response = await openPage(page, baseURL, entry);
        expect(response?.status(), statusMessage(entry, response)).toBe(entry.status);
        await settle(page, { intro: true });

        const builder = new AxeBuilder({ page }).withTags(TAGS);
        for (const frame of THIRD_PARTY_FRAMES) builder.exclude(frame);
        const results = await builder.analyze();

        const width = widthOf(page);
        const label = `${entry.slug}-${width}`;
        const file = path.join(AXE_DIR, `${label}.json`);
        writeJson(file, {
          page: entry.path,
          url: results.url,
          viewport: page.viewportSize(),
          tags: TAGS,
          timestamp: results.timestamp,
          testEngine: results.testEngine,
          testEnvironment: results.testEnvironment,
          violations: results.violations,
          incomplete: results.incomplete,
          passes: results.passes.map((r) => ({ id: r.id, impact: r.impact, nodes: r.nodes.length })),
          inapplicable: results.inapplicable.map((r) => r.id),
        });

        const summary = summarise(label, results, file);
        await testInfo.attach('axe-summary.txt', { body: summary, contentType: 'text/plain' });
        // With violations the summary is the failure message; print it inline only for clean pages.
        if (!results.violations.length) console.log(summary);

        expect(results.violations.length, summary).toBe(0);
      });
    });
  }
});
