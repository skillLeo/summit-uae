#!/usr/bin/env node
/**
 * Accessibility structure check over the sitemap (client PDF item 32), in Google Chrome.
 *
 *   QA_BASE_URL=http://127.0.0.1:8045 node scripts/qa/a11y.mjs [--widths=390,1440] [--axe=0] [--json=report.json]
 *       [--also=/en/no-such-page/,…]   more addresses (any status: the 404 page, noindex utility pages)
 *       [--sitemap=0]                  only the --also addresses
 *
 * For every <loc> of <base>/sitemap.xml (fetched on the base by path) at each width (default 390 px, a phone):
 *   headings    exactly one <h1>; no skipped level going down (an H2 followed by an H4); every heading has text
 *   images      every <img> has an alt attribute (alt="" only on a decorative image); <svg role="img"> and
 *               [role="img"] have an accessible name
 *   controls    every link and button has an accessible name (text, aria-label, aria-labelledby or a titled /
 *               alt-text child); WhatsApp links (wa.me) and the menu, language and search controls
 *               (aria-expanded / aria-haspopup / aria-controls) are named; no positive tabindex; nothing focusable
 *               inside aria-hidden
 *   keyboard    the skip link is the first Tab stop and moves focus to #main-content; the first 25 Tab stops all
 *               show a visible focus indicator (the control, its pseudo-elements or a near ancestor change)
 *   axe-core    WCAG 2.0/2.1 A and AA, WCAG 2.2 AA (as tests/qa/d2/visual.spec.mjs), unless --axe=0; the drawn
 *               sample documents (.doc[data-doc] .doc__body, aria-hidden illustrations) are left out
 *
 * Exit 0: no failure. 1: a failure. 2: the site, its sitemap or Chrome cannot be reached.
 */
import { writeFileSync } from 'node:fs';
import AxeBuilder from '@axe-core/playwright';
import { chromium } from '@playwright/test';

const BASE = (process.env.QA_BASE_URL || 'http://127.0.0.1:8045').replace(/\/+$/, '');
const BASE_ORIGIN = new URL(BASE).origin;
const args = process.argv.slice(2);
const option = (name) => args.find((a) => a.startsWith(`--${name}=`))?.split('=').slice(1).join('=');
const WIDTHS = (option('widths') || '390').split(',').map(Number).filter(Boolean);
const AXE = option('axe') !== '0';
const JSON_OUT = option('json');
const CONCURRENCY = Number(process.env.QA_A11Y_CONCURRENCY || 2);
const AXE_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];
const DOC_ART = '.doc[data-doc] .doc__body';
const TAB_STOPS = 25;

const sitemap = await fetch(`${BASE}/sitemap.xml`).then((r) => (r.ok ? r.text() : null)).catch(() => null);
if (!sitemap) {
    console.error(`a11y: cannot read ${BASE}/sitemap.xml`);
    process.exit(2);
}
const ALSO = (option('also') || '').split(',').map((p) => p.trim()).filter(Boolean);
const fromSitemap = option('sitemap') === '0' ? [] : [...sitemap.matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => new URL(m[1].replace(/&amp;/g, '&')).pathname);
const paths = [...fromSitemap, ...ALSO];

let browser;
try {
    browser = await chromium.launch({ channel: 'chrome', headless: true });
} catch (error) {
    console.error(`a11y: cannot start Google Chrome (${error.message})`);
    process.exit(2);
}

/** Runs in the page: the structural checks. */
function inspect() {
    const out = [];
    const visible = (el) => {
        const s = getComputedStyle(el);
        return s.display !== 'none' && s.visibility !== 'hidden' && el.getClientRects().length > 0;
    };
    const describe = (el) => {
        const id = el.id ? `#${el.id}` : '';
        const cls = typeof el.className === 'string' && el.className.trim() ? `.${el.className.trim().split(/\s+/).slice(0, 2).join('.')}` : '';
        return `<${el.tagName.toLowerCase()}${id}${cls}>`;
    };
    const nameOf = (el) => {
        const label = el.getAttribute('aria-label');
        if (label && label.trim()) return label.trim();
        const by = el.getAttribute('aria-labelledby');
        if (by) {
            const text = by.split(/\s+/).map((id) => document.getElementById(id)?.textContent || '').join(' ').trim();
            if (text) return text;
        }
        const text = (el.innerText || el.textContent || '').trim();
        if (text) return text;
        for (const img of el.querySelectorAll('img[alt]')) if (img.alt.trim()) return img.alt.trim();
        for (const t of el.querySelectorAll('svg title')) if (t.textContent.trim()) return t.textContent.trim();
        return el.getAttribute('title')?.trim() || '';
    };

    // headings
    const headings = [...document.querySelectorAll('h1, h2, h3, h4, h5, h6')].filter((h) => !h.closest('[hidden], template'));
    const h1 = headings.filter((h) => h.tagName === 'H1');
    if (h1.length !== 1) out.push({ rule: 'one-h1', detail: `${h1.length} <h1>` });
    let previous = 0;
    for (const h of headings) {
        const level = Number(h.tagName[1]);
        if (previous && level > previous + 1) out.push({ rule: 'heading-skip', detail: `H${previous} → H${level} ${describe(h)} "${h.textContent.trim().slice(0, 60)}"` });
        if (!h.textContent.trim() && !h.querySelector('img[alt]:not([alt=""]), svg title')) out.push({ rule: 'heading-empty', detail: describe(h) });
        previous = level;
    }

    // images
    for (const img of document.querySelectorAll('img')) {
        if (!img.hasAttribute('alt')) out.push({ rule: 'img-alt', detail: `${describe(img)} ${img.getAttribute('src')}` });
    }
    for (const el of document.querySelectorAll('[role="img"]')) {
        if (!el.closest('[aria-hidden="true"]') && !nameOf(el)) out.push({ rule: 'role-img-name', detail: describe(el) });
    }

    // controls
    const controls = [...document.querySelectorAll('a[href], button, [role="button"], summary')].filter((el) => !el.closest('[aria-hidden="true"], [hidden], template'));
    for (const el of controls) {
        if (!nameOf(el)) out.push({ rule: 'control-name', detail: `${describe(el)} ${el.getAttribute('href') || ''}` });
    }
    for (const el of document.querySelectorAll('a[href*="wa.me"], a[href*="whatsapp"]')) {
        if (el.closest('[aria-hidden="true"]')) continue;
        if (!nameOf(el)) out.push({ rule: 'whatsapp-name', detail: describe(el) });
        if (!/whatsapp|واتساب|ватсап|whatsApp/i.test(nameOf(el)) && !el.getAttribute('aria-label')) out.push({ rule: 'whatsapp-label', detail: `${describe(el)} "${nameOf(el).slice(0, 50)}" (no WhatsApp in its name)` });
    }
    for (const el of document.querySelectorAll('[aria-expanded], [aria-haspopup], [aria-controls]')) {
        if (el.closest('[aria-hidden="true"], [hidden], template')) continue;
        if (/^(BUTTON|A|SUMMARY|INPUT)$/.test(el.tagName) || el.getAttribute('role') === 'button') {
            if (!nameOf(el) && !(el.labels && el.labels.length)) out.push({ rule: 'menu-control-name', detail: describe(el) });
        }
    }
    for (const el of document.querySelectorAll('[tabindex]')) {
        if (Number(el.getAttribute('tabindex')) > 0) out.push({ rule: 'positive-tabindex', detail: describe(el) });
    }
    for (const el of document.querySelectorAll('[aria-hidden="true"] a[href], [aria-hidden="true"] button, [aria-hidden="true"] input, [aria-hidden="true"] [tabindex="0"]')) {
        if (el.getAttribute('tabindex') !== '-1' && !el.disabled && visible(el) && !el.closest('[inert]')) out.push({ rule: 'focusable-in-aria-hidden', detail: describe(el) });
    }
    return out;
}

async function keyboard(page) {
    const out = [];
    await page.evaluate(() => { document.activeElement?.blur(); window.scrollTo(0, 0); });
    await page.keyboard.press('Tab');
    const first = await page.evaluate(() => ({ cls: document.activeElement?.className || '', href: document.activeElement?.getAttribute('href') || '' }));
    if (!/skip-link/.test(first.cls) || first.href !== '#main-content') out.push({ rule: 'skip-link-first', detail: `first Tab stop: .${first.cls} ${first.href}` });
    else {
        await page.keyboard.press('Enter');
        const target = await page.evaluate(() => document.activeElement?.id || '');
        if (target !== 'main-content') out.push({ rule: 'skip-link-target', detail: `focus on #${target}` });
        await page.evaluate(() => { document.activeElement?.blur(); window.scrollTo(0, 0); });
    }
    for (let i = 0; i < TAB_STOPS; i++) {
        await page.keyboard.press('Tab');
        // Visible focus = something on the control, its ::before/::after or one of its four nearest ancestors looks
        // different focused and blurred (outline, ring, border, underline, background): the site draws some rings on
        // the card around a stretched link (.card:has(.card__link:focus-visible)) or on a field's frame.
        const stop = await page.evaluate(() => {
            const el = document.activeElement;
            if (!el || el === document.body) return null;
            const chain = [el];
            for (let p = el.parentElement, i = 0; p && i < 4; p = p.parentElement, i++) chain.push(p);
            const snap = () => chain.flatMap((n) => [null, '::before', '::after'].map((pseudo) => {
                const s = getComputedStyle(n, pseudo);
                return [s.outlineStyle, s.outlineWidth, s.outlineColor, s.boxShadow, s.borderColor, s.textDecorationLine, s.backgroundColor].join('|');
            })).join('#');
            const focused = snap();
            el.blur();
            const blurred = snap();
            el.focus({ preventScroll: true });
            return { visible: focused !== blurred, tag: el.tagName.toLowerCase(), cls: typeof el.className === 'string' ? el.className.split(/\s+/)[0] : '', text: (el.getAttribute('aria-label') || el.textContent || '').trim().slice(0, 40) };
        });
        if (!stop) break;
        if (!stop.visible) out.push({ rule: 'focus-visible', detail: `<${stop.tag}.${stop.cls}> "${stop.text}"` });
    }
    return out;
}

const results = [];
const jobs = paths.flatMap((path) => WIDTHS.map((width) => ({ path, width })));
process.stdout.write(`Accessibility check · ${BASE} · ${paths.length} pages × ${WIDTHS.join(', ')} px${AXE ? ' · axe' : ''}\n`);
const queue = [...jobs];
await Promise.all(Array.from({ length: Math.min(CONCURRENCY, queue.length) }, async () => {
    while (queue.length) {
        const { path, width } = queue.shift();
        const context = await browser.newContext({ viewport: { width, height: width < 768 ? 844 : 900 }, reducedMotion: 'reduce', locale: { en: 'en-GB', ar: 'ar-AE', ru: 'ru-RU' }[path.split('/')[1]] || 'en-GB' });
        const page = await context.newPage();
        const row = { path, width, problems: [] };
        try {
            const res = await page.goto(`${BASE_ORIGIN}${path}`, { waitUntil: 'load', timeout: 180_000 });
            if (res?.status() !== 200 && !ALSO.includes(path)) row.problems.push({ rule: 'status', detail: String(res?.status()) });
            await page.waitForTimeout(300);
            row.problems.push(...(await page.evaluate(inspect)));
            row.problems.push(...(await keyboard(page)));
            if (AXE) {
                const axe = await new AxeBuilder({ page }).withTags(AXE_TAGS).exclude(DOC_ART).analyze();
                for (const v of axe.violations) {
                    for (const node of v.nodes) row.problems.push({ rule: `axe:${v.id}`, detail: `${node.target.join(' ')} (${v.impact})` });
                }
            }
        } catch (error) {
            row.problems.push({ rule: 'error', detail: error.message.split('\n')[0] });
        } finally {
            await context.close();
        }
        results.push(row);
        process.stdout.write(`  ${row.problems.length ? '✗' : '✓'} ${String(width).padStart(4)} ${path}${row.problems.length ? `  (${row.problems.length})` : ''}\n`);
    }
}));
await browser.close();

const byRule = new Map();
for (const r of results) for (const p of r.problems) {
    const list = byRule.get(p.rule) || [];
    list.push(`${r.path} @${r.width}: ${p.detail}`);
    byRule.set(p.rule, list);
}
for (const [rule, list] of [...byRule.entries()].sort()) {
    process.stdout.write(`\n${rule} (${list.length})\n`);
    for (const line of [...new Set(list)].slice(0, 40)) process.stdout.write(`  ${line}\n`);
    if (list.length > 40) process.stdout.write(`  … ${list.length - 40} more\n`);
}
const summary = {
    base: BASE,
    pages: paths.length,
    widths: WIDTHS,
    runs: results.length,
    clean: results.filter((r) => r.problems.length === 0).length,
    problems: Object.fromEntries([...byRule.entries()].map(([k, v]) => [k, v.length])),
};
process.stdout.write(`\n${JSON.stringify(summary)}\n`);
if (JSON_OUT) writeFileSync(JSON_OUT, JSON.stringify({ summary, results }, null, 2));
const failed = results.some((r) => r.problems.length > 0);
process.stdout.write(failed ? 'FAIL (see above).\n' : 'OK.\n');
process.exit(failed ? 1 : 0);
