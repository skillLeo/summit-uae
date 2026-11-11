/**
 * Design 2 visual QA (upgrade spec §12 Phase G1, checklist §9). For every page in qa/lib/config.mjs (home,
 * one service, the resources page with the guides, the case studies page, the FAQ, the contact page, the
 * 404) × EN / AR / RU at the project's width: a full-page screenshot in qa/shots/<date>/visual/<name>.png
 * and the measured checks in <name>.json (qa/report.html shows both).
 *
 * Checks that fail the run (annotations on the test plus the verdict printed by qa/lib/verdict.mjs at the
 * end; QA_D2_STRICT=1 makes each one a failing soft assertion instead):
 *   HTTP status · html lang/dir · rendered by Design 2 · no horizontal overflow · no text under 14 px outside
 *   sample documents · exactly one H1, visible · no duplicate ids · no console errors (incl. Alpine / CSP
 *   warnings), page errors or failed requests · no empty band taller than 160 px · axe (WCAG 2.2 AA tags):
 *   0 serious / critical · no missing-copy marker ⟦…⟧ · a watermark on every sample document.
 * Reported only: heading-level skips, underlined links outside running text, banned-claim phrases (the
 * authoritative grep is scripts/compliance-check.mjs), axe moderate / minor and incidental (sample-document
 * art), proposed-copy count.
 *
 * Variants (projects variants-*): reduced = prefers-reduced-motion; nojs = JavaScript disabled (axe needs
 * timers and cannot run there: covered by the default variant).
 */
import AxeBuilder from '@axe-core/playwright';
import { test } from '@playwright/test';
import { findBands, mergeIntervals } from '../../../qa/lib/bands.mjs';
import { newContext, scrollThrough, untilPageTime, watchPage } from '../../../qa/lib/browser.mjs';
import {
    ACTIVE_LOCALES,
    ACTIVE_PAGES,
    AXE_ENABLED,
    IGNORED_HOSTS,
    LOCALES,
    MAX_BAND,
    MIN_TEXT,
    RUN,
    captureName,
    pageUrl,
    runDir,
} from '../../../qa/lib/config.mjs';
import { describeEl, freezeAnimations, measurePage, script, settleAnimations, settleStyles } from '../../../qa/lib/runtime.mjs';
import { fullPageShot, writeJson, writePng } from '../../../qa/lib/shots.mjs';
import { settle } from '../../../qa/lib/verdict.mjs';

const AXE_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

/** Sample documents are illustrations: their fine print is exempt from the 14 px floor (engineering §8). */
const EXEMPT = '.doc, [data-doc]';
/** The drawn (aria-hidden) content of a sample document. */
const DOC_ART = '.doc[data-doc] .doc__body';

/** Checklist §9.12 / §12 compliance phrases (warnings here; scripts/compliance-check.mjs is the gate). */
const BANNED = [
    { id: '24/7', source: '24\\s*/\\s*7' },
    { id: 'free consultation', source: 'free\\s+consultation|استشارة\\s+مجانية|бесплатн\\p{L}*\\s+консультаци\\p{L}*' },
    { id: 'law firm', source: 'law\\s+firm|مكتب\\s+محاماة|شركة\\s+محاماة|юридическ\\p{L}*\\s+фирм\\p{L}*' },
    { id: 'years of experience', source: 'years\\s+of\\s+experience|سنوات\\s+من\\s+الخبرة|лет\\s+опыта' },
    { id: 'award', source: '\\bawards?\\b|award-winning' },
    { id: 'rated', source: '\\b(?:top[- ])?rated\\b' },
    { id: 'success rate', source: 'success\\s+rates?|نسبة\\s+النجاح|процент\\p{L}*\\s+успех\\p{L}*' },
];

const clip = (s, max = 200) => {
    const one = String(s ?? '').replace(/\s+/g, ' ').trim();
    return one.length > max ? `${one.slice(0, max)}…` : one;
};

/**
 * axe (WCAG 2.0/2.1 A + AA, WCAG 2.2 AA). Third-party frames (Turnstile) are not audited. The drawn content
 * of a sample document (.doc__body: aria-hidden, inside a labelled role="img") is incidental text under
 * WCAG 1.4.3 (part of a picture), so it is audited separately and reported as `incidental`, not failed.
 */
async function runAxe(page) {
    const builder = new AxeBuilder({ page }).withTags(AXE_TAGS).exclude(DOC_ART);
    for (const host of IGNORED_HOSTS) builder.exclude({ fromFrames: [`iframe[src*="${host}"]`, 'html'] });
    const results = await builder.analyze();
    let incidental = 0;
    if (await page.locator(DOC_ART).count()) {
        const art = await new AxeBuilder({ page }).include(DOC_ART).withRules(['color-contrast']).analyze();
        incidental = art.violations.reduce((n, v) => n + v.nodes.length, 0);
    }
    const violations = results.violations.map((v) => ({
        id: v.id,
        impact: v.impact,
        help: v.help,
        nodes: v.nodes.length,
        targets: v.nodes.slice(0, 6).map((n) => ({
            target: n.target.map((t) => (Array.isArray(t) ? t.join(' >>> ') : t)).join(' | '),
            html: clip(n.html, 160),
            why: clip(n.failureSummary?.split('\n').slice(1).join(' '), 200),
        })),
    }));
    const count = (impacts) => violations.filter((v) => impacts.includes(v.impact)).length;
    return { serious: count(['serious', 'critical']), other: count(['moderate', 'minor']), violations, passed: results.passes.length, incomplete: results.incomplete.length, incidental };
}

const lines = (list, f = (x) => (typeof x === 'string' ? x : JSON.stringify(x))) => list.map((x) => `  - ${f(x)}`).join('\n');

/** One readable message per failing check (annotation, or the soft assertion with QA_D2_STRICT=1). */
function explain(id, c) {
    switch (id) {
        case 'status':
            return `HTTP ${c.actual}, expected ${c.expected}`;
        case 'langDir':
            return `html lang/dir ${JSON.stringify(c.actual)}, expected ${JSON.stringify(c.expected)}`;
        case 'design2':
            return `not rendered by Design 2 (html[data-design] = ${c.actual})`;
        case 'overflow':
            return `horizontal overflow ${c.px}px:\n${lines(c.culprits)}`;
        case 'smallText':
            return `${c.count} text element(s) under ${c.min}px:\n${lines(c.samples)}`;
        case 'h1':
            return `${c.count} <h1> (${c.visible} visible):\n${lines(c.texts)}`;
        case 'duplicateIds':
            return `duplicate ids:\n${lines(c.list)}`;
        case 'console':
            return `console / network errors:\n${lines(c.errors)}`;
        case 'bands':
            return `empty bands over ${c.max}px:\n${lines(c.list, (b) => `${b.height}px at y=${b.top}${b.edge ? ` (${b.edge} edge)` : ''}: after ${b.above} / before ${b.below}`)}`;
        case 'axe':
            return `axe serious/critical:\n${lines(c.violations.filter((v) => ['serious', 'critical'].includes(v.impact)), (v) => `[${v.impact}] ${v.id} (${v.nodes}): ${v.targets[0]?.target ?? ''}`)}`;
        case 'missingCopy':
            return `missing-copy markers:\n${lines(c.list)}`;
        case 'watermark':
            return `sample documents without a watermark:\n${lines(c.without)}`;
        default:
            return `${id}: ${JSON.stringify(c.list ?? c.skips ?? c).slice(0, 400)}`;
    }
}

for (const entry of ACTIVE_PAGES) {
    for (const locale of ACTIVE_LOCALES) {
        test(`${entry.key}-${locale}`, async ({ browser }, testInfo) => {
            const { width, variant = 'default' } = testInfo.project.metadata;
            const name = captureName(entry.key, locale, width, variant);
            const expectStatus = entry.status ?? 200;
            const record = {
                name,
                page: entry.key,
                label: entry.label,
                locale,
                width,
                variant,
                url: pageUrl(locale, entry.path),
                run: RUN,
                capturedAt: new Date().toISOString(),
                file: `visual/${name}.png`,
            };
            const context = await newContext(browser, { width, locale, variant });
            const page = await context.newPage();
            const watch = watchPage(page, { expectStatus, javaScript: variant !== 'nojs' });
            const started = Date.now();
            try {
                const response = await page.goto(record.url, { waitUntil: 'load', timeout: 60_000 });
                record.status = response?.status() ?? null;
                record.loadMs = Date.now() - started;
                if (variant !== 'nojs') {
                    await page.waitForFunction(() => document.documentElement.classList.contains('js'), null, { timeout: 5000 }).catch(() => {});
                    await page.evaluate(() => document.fonts.ready.then(() => document.fonts.status)).catch(() => null);
                    // The hero storyboard ends with the callouts at about 3.0 s (spec §9.8).
                    if (entry.key === 'home' && variant === 'default') await untilPageTime(page, 3300);
                }
                // Phones keep the hero armed until its stage scrolls into view, then deal (about 3 s).
                const armed = variant === 'default' && (await page.evaluate(() => document.documentElement.classList.contains('hero-armed')));
                record.scrollSteps = await scrollThrough(page, { pauseMs: 60, decode: variant !== 'nojs' });
                // Font subsets load on demand (unicode-range) as new text scrolls in: wait for them again.
                await page.evaluate(() => document.fonts.ready.then(() => document.fonts.status)).catch(() => null);
                if (armed) await page.waitForTimeout(3300);
                if (variant !== 'nojs') {
                    // JS-driven motion (springs) writes inline styles every frame; getAnimations() cannot see it.
                    record.settled = await page.evaluate(script(settleStyles, { quietMs: 300, maxMs: 3000 }));
                    await page.evaluate(script(settleAnimations, 3000));
                }
                await watch.idle(4000);

                const m = await page.evaluate(
                    script(measurePage, { viewportWidth: width, minText: MIN_TEXT, exempt: EXEMPT, banned: BANNED, marker: '⟦' }, { describeEl, mergeIntervals }),
                );
                const bands = findBands(m.content.intervals, { min: MAX_BAND, height: m.size.height, labels: m.content.labels });
                // The tallest gap of the page even when under the limit: shows how close each page comes to it.
                const largestGap = findBands(m.content.intervals, { min: 0, labels: m.content.labels }).sort((a, b) => b.height - a.height)[0] ?? null;
                record.measuredAt = Date.now() - started;
                record.frozen = await page.evaluate(script(freezeAnimations));
                // The first screen at device scale: fixed bars (tab bar, sticky CTA) where the visitor sees them.
                await writePng(runDir('visual', `${name}.fold.png`), await page.screenshot({ animations: 'allow', caret: 'hide' }));
                record.fold = `visual/${name}.fold.png`;
                record.shot = await fullPageShot(page, runDir('visual', `${name}.png`), { animations: 'allow' });
                record.shotMs = Date.now() - started - record.measuredAt;
                watch.freeze();
                const axeStarted = Date.now();
                const axe = AXE_ENABLED && variant !== 'nojs' ? await runAxe(page) : null;
                record.axeMs = Date.now() - axeStarted;
                record.ms = Date.now() - started;
                Object.assign(record, {
                    title: m.title,
                    html: m.html,
                    size: m.size,
                    checks: {
                        status: { expected: expectStatus, actual: record.status, ok: record.status === expectStatus },
                        langDir: {
                            expected: { lang: locale, dir: LOCALES[locale].dir },
                            actual: { lang: m.html.lang, dir: m.html.dir },
                            ok: new RegExp(`^${locale}(-[a-z0-9]+)*$`, 'i').test(m.html.lang ?? '') && m.html.dir === LOCALES[locale].dir,
                        },
                        design2: { actual: m.html.design, ok: m.html.design === '2' },
                        overflow: { ...m.overflow, ok: m.overflow.px === 0 },
                        smallText: { ...m.smallText, ok: m.smallText.count === 0 },
                        h1: { ...m.h1, ok: m.h1.count === 1 && m.h1.visible === 1 },
                        duplicateIds: { list: m.duplicateIds, ok: m.duplicateIds.length === 0 },
                        console: { errors: watch.errors.slice(0, 40), count: watch.errors.length, warnings: watch.warnings.slice(0, 20), ok: watch.errors.length === 0 },
                        bands: { max: MAX_BAND, list: bands, largest: largestGap, contentRuns: m.content.intervals.length, ok: bands.length === 0 },
                        axe: axe ? { ...axe, ok: axe.serious === 0 } : { skipped: variant === 'nojs' ? 'JavaScript disabled' : 'QA_D2_AXE=0', ok: true },
                        missingCopy: { list: m.missingCopy, ok: m.missingCopy.length === 0 },
                        watermark: { docs: m.docs.count, without: m.docs.withoutWatermark, ok: m.docs.withoutWatermark.length === 0 },
                        // reported only
                        headings: { ...m.headings, ok: m.headings.skipCount === 0 && (m.headings.first === null || m.headings.first === 'h1'), warn: true },
                        underlinedLinks: { list: m.underlinedLinks, ok: m.underlinedLinks.length === 0, warn: true },
                        banned: { list: m.banned, ok: m.banned.length === 0, warn: true },
                        proposed: { count: m.proposed, ok: true, info: true },
                    },
                });
            } catch (error) {
                record.error = String(error?.stack || error).split('\n').slice(0, 4).join(' | ');
                throw error;
            } finally {
                writeJson(runDir('visual', `${name}.json`), record);
                await context.close();
            }

            await testInfo.attach('checks.json', { body: JSON.stringify(record, null, 2), contentType: 'application/json' });
            settle(testInfo, record.checks, explain);
        });
    }
}
