#!/usr/bin/env node
/**
 * Design 1 visual identity proof (docs/upgrade-plan.md Phase G: "0 differences in Design 1"). The pixel
 * companion of scripts/qa/d1-identity.mjs (which compares the HTML byte for byte): every Design 1 HTML page
 * of the same URL list (read from d1-identity.mjs) is captured full page at 1440 and 390 from the baseline
 * server running the git tag design-1.1 and from this branch (cookies summit_design=1 and summit_qa=1 on both),
 * and the two raw RGBA buffers are compared pixel by pixel (sharp, no tolerance).
 *
 *   node scripts/qa/d1-visual.mjs                       all pages, 1440 and 390
 *   node scripts/qa/d1-visual.mjs --widths=390 --only=/ar/   a subset (regular expression on the path)
 *   node scripts/qa/d1-visual.mjs --concurrency=6 --save=all
 *
 * Env: QA_D1_BASE (baseline, default http://127.0.0.1:8041), QA_D1_BRANCH (branch, default
 * http://127.0.0.1:8040), QA_D2_RUN (output folder name, default today's date).
 * Output: qa/shots/<date>/d1-visual/summary.json and report.txt; thumbs/<slug>-<width>.jpg for every page;
 * for each difference <slug>-<width>.baseline.png, .branch.png and .diff.png (magenta = different pixels);
 * --save=all also keeps the full PNGs of identical pages.
 * Both captures get the same treatment: same browser, viewport, locale (the page language), time zone,
 * fonts and the intro wait, a scroll through the page, finished animations, third-party requests blocked
 * (Turnstile only loads on focus anyway), and static assets cached per origin so the single-threaded
 * baseline server only renders HTML.
 * Exit 0: no difference. Exit 1: a page differs or could not be captured. Exit 2: the run could not happen.
 */
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { chromium } from '@playwright/test';
import sharp from 'sharp';
import { newContext, scrollThrough, untilPageTime } from '../../qa/lib/browser.mjs';
import { ROOT, runDir } from '../../qa/lib/config.mjs';
import { compareRgba, rgba } from '../../qa/lib/pixels.mjs';
import { script, settleAnimations, settleStyles } from '../../qa/lib/runtime.mjs';
import { fullPageBuffer, writeJson } from '../../qa/lib/shots.mjs';

const BASE = (process.env.QA_D1_BASE || 'http://127.0.0.1:8041').replace(/\/+$/, '');
const BRANCH = (process.env.QA_D1_BRANCH || 'http://127.0.0.1:8040').replace(/\/+$/, '');
const OUT = runDir('d1-visual');
const LOCALES = ['en', 'ar', 'ru'];

// ------------------------------------------------------------------------------------ options
const args = Object.fromEntries(
    process.argv.slice(2).map((a) => {
        const [k, ...v] = a.replace(/^--/, '').split('=');
        return [k, v.length ? v.join('=') : 'true'];
    }),
);
if (args.help) {
    console.log('Usage: node scripts/qa/d1-visual.mjs [--widths=1440,390] [--only=<regex>] [--concurrency=4] [--save=diff|all]');
    process.exit(0);
}
const WIDTHS = (args.widths || '1440,390').split(',').map(Number).filter((w) => [1440, 1280, 768, 390].includes(w));
const ONLY = args.only ? new RegExp(args.only) : null;
const CONCURRENCY = Math.max(1, Number(args.concurrency) || 4);
const SAVE_ALL = args.save === 'all';
const DETERMINISTIC = ['--disable-gpu', '--disable-gpu-rasterization', '--disable-partial-raster', '--disable-skia-runtime-opts', '--disable-lcd-text', '--force-color-profile=srgb'];

// ------------------------------------------------------------------------------------ URL list
/** The page list of scripts/qa/d1-identity.mjs (parsed, so both proofs always cover the same URLs). */
function identityLists() {
    const fallback = {
        pages: ['', 'practice-areas/', 'resources/', 'case-studies/', 'faq/', 'about/', 'contact/', 'legal-disclaimer/', 'privacy-policy/', 'terms-and-conditions/', 'styleguide/', 'offline/', 'no-such-page-qa/'],
        services: ['debt-recovery-cheque-cases', 'criminal-cases', 'crypto-digital-currency-cases', 'corporate-commercial-law', 'real-estate-rental-disputes', 'labor-law', 'tax-disputes-compliance', 'family-law-wills', 'compliance-regulatory', 'legal-translation', 'business-services'],
        source: 'built-in copy',
    };
    try {
        const src = readFileSync(path.join(ROOT, 'scripts/qa/d1-identity.mjs'), 'utf8');
        const grab = (name) => {
            const m = src.match(new RegExp(`const ${name} = \\[([^\\]]*)\\]`));
            return m ? [...m[1].matchAll(/'([^']*)'/g)].map((x) => x[1]) : null;
        };
        const pages = grab('pages');
        const services = grab('services');
        if (pages?.length && services?.length) return { pages, services, source: 'scripts/qa/d1-identity.mjs' };
    } catch {
        // fall back
    }
    return fallback;
}

const lists = identityLists();
const targets = [{ path: '/', locale: 'en' }];
for (const locale of LOCALES) {
    for (const p of lists.pages) targets.push({ path: `/${locale}/${p}`, locale });
    for (const s of lists.services) targets.push({ path: `/${locale}/practice-areas/${s}/`, locale });
    targets.push({ path: `/${locale}/?navigator=S05-2`, locale });
}
// /sitemap.xml and /xx/manifest.webmanifest are not pages: d1-identity.mjs compares them byte for byte.
const selected = targets.filter((t) => !ONLY || ONLY.test(t.path));
const slugOf = (p) => (p === '/' ? 'root' : p.replace(/^\//, '').replace(/\/$/, '').replace(/^(en|ar|ru)$/, '$1-home').replace(/^(en|ar|ru)\/\?/, '$1-home-').replace(/[^a-z0-9]+/gi, '-').replace(/-+$/, ''));
const isHome = (p) => /^\/(en|ar|ru)\/(\?.*)?$/.test(p);

// ------------------------------------------------------------------------------------ capture
/** Static assets are fetched once per origin and replayed (identical bytes for every page of that server). */
const caches = new Map();
async function prepare(context, origin) {
    const cache = caches.get(origin) ?? new Map();
    caches.set(origin, cache);
    await context.route('**/*', async (route) => {
        const req = route.request();
        const url = req.url();
        if (!url.startsWith(origin)) {
            if (/^https?:/.test(url)) return route.abort();
            return route.continue();
        }
        if (req.method() !== 'GET' || !['stylesheet', 'script', 'font', 'image', 'manifest', 'media'].includes(req.resourceType())) return route.continue();
        let hit = cache.get(url);
        if (!hit) {
            hit = route.fetch().then(async (r) => ({ status: r.status(), headers: r.headers(), body: await r.body() }));
            cache.set(url, hit);
            hit.catch(() => cache.delete(url));
        }
        try {
            const r = await hit;
            return route.fulfill({ status: r.status, headers: r.headers, body: r.body });
        } catch {
            return route.continue();
        }
    });
}

const contexts = new Map();
async function contextFor(browser, origin, width, locale) {
    const key = `${origin}|${width}|${locale}`;
    if (!contexts.has(key)) {
        contexts.set(
            key,
            (async () => {
                const context = await newContext(browser, { width, locale, baseURL: origin, design: '1', extra: width === 390 ? { deviceScaleFactor: 1 } : {} });
                await prepare(context, origin);
                return context;
            })(),
        );
    }
    return contexts.get(key);
}

async function capture(browser, origin, target, width) {
    const context = await contextFor(browser, origin, width, target.locale);
    const page = await context.newPage();
    try {
        const response = await page.goto(origin + target.path, { waitUntil: 'load', timeout: 60_000 });
        await page.evaluate(() => document.fonts.ready.then(() => true));
        if (isHome(target.path)) await untilPageTime(page, 1700); // "The File" hero intro ends at about 1.45 s
        await scrollThrough(page, { pauseMs: 40 });
        // Font subsets load on demand (unicode-range) as new text scrolls in.
        await page.evaluate(() => document.fonts.ready.then(() => true));
        // GSAP tweens started by the scroll (method section, Matter Navigator) write inline styles each frame.
        await page.evaluate(script(settleStyles, { quietMs: 400, maxMs: 4000 }));
        await page.evaluate(script(settleAnimations, 3000));
        const shot = await fullPageBuffer(page);
        return { status: response?.status() ?? null, ...shot };
    } finally {
        await page.close();
    }
}

async function pool(items, size, fn) {
    let next = 0;
    const workers = Array.from({ length: Math.min(size, items.length) }, async () => {
        while (next < items.length) {
            const i = next++;
            await fn(items[i], i);
        }
    });
    await Promise.all(workers);
}

// ------------------------------------------------------------------------------------ main
async function reachable(origin) {
    try {
        const res = await fetch(`${origin}/en/`, { headers: { cookie: 'summit_design=1; summit_qa=1' }, redirect: 'manual', signal: AbortSignal.timeout(20_000) });
        return res.status < 500;
    } catch {
        return false;
    }
}

async function main() {
    for (const origin of [BASE, BRANCH]) {
        if (!(await reachable(origin))) {
            console.error(`Nothing answers at ${origin}. Baseline: QA_D1_BASE (git tag design-1.1, default :8041); branch: QA_D1_BRANCH (default :8040).`);
            return 2;
        }
    }
    mkdirSync(path.join(OUT, 'thumbs'), { recursive: true });
    // Software raster, sRGB, no partial raster: two captures of the same page must give the same pixels.
    const browser = await chromium.launch({ channel: 'chrome', headless: true, args: DETERMINISTIC });
    const jobs = selected.flatMap((t) => WIDTHS.map((width) => ({ ...t, width, slug: slugOf(t.path) })));
    const rows = [];
    const started = Date.now();
    console.log(`Design 1 visual proof: ${selected.length} pages × ${WIDTHS.join(', ')} px, baseline ${BASE} vs branch ${BRANCH} (URL list: ${lists.source})`);
    try {
        await pool(jobs, CONCURRENCY, async (job) => {
            const name = `${job.slug}-${job.width}`;
            const row = { path: job.path, width: job.width, name };
            try {
                let [a, b] = await Promise.all([capture(browser, BASE, job, job.width), capture(browser, BRANCH, job, job.width)]);
                let cmp = compareRgba(await rgba(a.buffer), await rgba(b.buffer), { withDiff: true });
                if (cmp.different > 0) {
                    // One fresh pair: a difference that survives it is real, a timing flake is not.
                    const [a2, b2] = await Promise.all([capture(browser, BASE, job, job.width), capture(browser, BRANCH, job, job.width)]);
                    const cmp2 = compareRgba(await rgba(a2.buffer), await rgba(b2.buffer), { withDiff: true });
                    row.retried = { first: cmp.different, second: cmp2.different };
                    if (cmp2.different < cmp.different) [a, b, cmp] = [a2, b2, cmp2];
                }
                Object.assign(row, {
                    status: [a.status, b.status],
                    size: { baseline: cmp.sizeA, branch: cmp.sizeB },
                    different: cmp.different,
                    ratio: cmp.ratio,
                    rows: cmp.rows ?? null,
                    same: cmp.different === 0 && a.status === b.status,
                });
                await sharp(b.buffer).resize({ width: 360 }).jpeg({ quality: 70 }).toFile(path.join(OUT, 'thumbs', `${name}.jpg`));
                row.thumb = `d1-visual/thumbs/${name}.jpg`;
                if (!row.same || SAVE_ALL) {
                    writeFileSync(path.join(OUT, `${name}.baseline.png`), a.buffer);
                    writeFileSync(path.join(OUT, `${name}.branch.png`), b.buffer);
                    row.files = { baseline: `d1-visual/${name}.baseline.png`, branch: `d1-visual/${name}.branch.png` };
                }
                if (cmp.different > 0 && cmp.diff) {
                    await sharp(cmp.diff, { raw: { width: cmp.width, height: cmp.height, channels: 4 } }).png().toFile(path.join(OUT, `${name}.diff.png`));
                    row.files.diff = `d1-visual/${name}.diff.png`;
                }
            } catch (error) {
                row.error = String(error?.message || error).split('\n')[0];
                row.same = false;
            }
            rows.push(row);
            const mark = row.error ? 'ERROR' : row.same ? 'SAME ' : 'DIFF ';
            console.log(`${mark} ${String(job.width).padEnd(4)} ${job.path}${row.error ? `  ${row.error}` : row.same ? '' : `  ${row.different} px differ (${(row.ratio * 100).toFixed(3)} %)${row.size.baseline.join('×') !== row.size.branch.join('×') ? `, size ${row.size.baseline.join('×')} vs ${row.size.branch.join('×')}` : ''}`}`);
        });
    } finally {
        await browser.close();
    }
    rows.sort((x, y) => x.path.localeCompare(y.path) || y.width - x.width);
    const diffs = rows.filter((r) => !r.same);
    const summary = {
        generatedAt: new Date().toISOString(),
        baseline: BASE,
        branch: BRANCH,
        widths: WIDTHS,
        urlList: lists.source,
        pages: selected.length,
        comparisons: rows.length,
        different: diffs.length,
        errors: rows.filter((r) => r.error).length,
        seconds: Math.round((Date.now() - started) / 1000),
        rows,
    };
    writeJson(path.join(OUT, 'summary.json'), summary, { keepPrevious: false });
    const report = [
        `Design 1 visual proof: ${rows.length} comparisons (${selected.length} pages × ${WIDTHS.join('/')} px), ${diffs.length} different, ${summary.errors} error(s), ${summary.seconds} s`,
        `baseline ${BASE}  ·  branch ${BRANCH}  ·  URL list from ${lists.source}`,
        '',
        ...rows.map((r) => `${r.error ? 'ERROR' : r.same ? 'SAME ' : 'DIFF '} ${String(r.width).padEnd(4)} ${r.path}${r.error ? `  ${r.error}` : r.same ? '' : `  ${r.different} px`}`),
    ].join('\n');
    writeFileSync(path.join(OUT, 'report.txt'), `${report}\n`);
    console.log(`\n${report.split('\n')[0]}\nReport: ${path.relative(ROOT, path.join(OUT, 'report.txt'))}`);
    return diffs.length ? 1 : 0;
}

if (!existsSync(path.join(ROOT, 'node_modules/@playwright/test'))) {
    console.error('Playwright is not installed (npm ci).');
    process.exit(2);
}
main().then(
    (code) => process.exit(code),
    (error) => {
        console.error(`d1-visual could not run: ${error?.stack || error}`);
        process.exit(2);
    },
);
