#!/usr/bin/env node
/**
 * Design 2 font files per first screen (area hero-perf; App\Design\D2\Fonts, App\Design\D2\FontFaces,
 * resources/css/d2/base/fonts.css). For every page × locale × width it lists the font files the text of the
 * first viewport needs (FIRST) and the ones only the rest of the page needs (LATER), by mapping every
 * character of every laid-out text node, and every watermark line (generated content), to the @font-face
 * rule the browser picks (family order, weight band, unicode-range). FIRST decides the two preloads per
 * locale (Fonts::PRELOAD) and the font groups FontFaces keeps active from the first paint; LATER ones wait
 * for the first paint. Re-run after changing a type role, the shell, a hero or head layout, or the fonts.
 *
 *   node scripts/d2/font-faces.mjs [--base=http://127.0.0.1:8040] [--pages=home,s01] [--locales=en,ar] [--widths=390,1440]
 * Pages: home s01 p02 p03 p04 p05 p06 p07 e404. Needs a PHP server for this working tree with Design 2 assets.
 */
import { chromium } from '@playwright/test';

const args = Object.fromEntries(process.argv.slice(2).map((a) => { const [k, v] = a.replace(/^--/, '').split('='); return [k, v ?? true]; }));
const PAGES = {
    home: '/', s01: '/practice-areas/debt-recovery-cheque-cases/', p03: '/resources/', p07: '/contact/', p05: '/faq/', p04: '/case-studies/', p06: '/about/', p02: '/practice-areas/', e404: '/no-such-page/',
};
const BASE = args.base || process.env.QA_BASE_URL || 'http://127.0.0.1:8040';
const VIEWPORTS = {
    1440: { viewport: { width: 1440, height: 900 } },
    1280: { viewport: { width: 1280, height: 800 } },
    1024: { viewport: { width: 1024, height: 768 } },
    768: { viewport: { width: 768, height: 1024 }, hasTouch: true },
    390: { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 },
    412: { viewport: { width: 412, height: 823 }, isMobile: true, hasTouch: true, deviceScaleFactor: 1.75 },
};
async function newPage(browser, width) {
    const context = await browser.newContext({ ...(VIEWPORTS[width] ?? { viewport: { width, height: 900 } }), reducedMotion: 'reduce' });
    await context.addCookies([{ name: 'summit_design', value: '2', url: BASE }, { name: 'summit_qa', value: '1', url: BASE }]);
    // a dev server's reload client would reload the page whenever a file is saved
    await context.route(/\/@vite\/client$/, (route) => route.fulfill({ status: 200, contentType: 'text/javascript', body: 'export const createHotContext=()=>({data:{},accept(){},acceptExports(){},dispose(){},prune(){},invalidate(){},decline(){},on(){},off(){},send(){}});export const updateStyle=()=>{};export const removeStyle=()=>{};export const injectQuery=(u)=>u;export class ErrorOverlay{}' }));
    return { context, page: await context.newPage() };
}
const pages = (args.pages ? String(args.pages).split(',') : Object.keys(PAGES));
const widths = (args.widths ? String(args.widths).split(',').map(Number) : [1440, 1280, 768, 390]);
const locales = (args.locales ? String(args.locales).split(',') : ['en', 'ar', 'ru']);

const browser = await chromium.launch({ channel: 'chrome', headless: true });
for (const key of pages) for (const locale of locales) for (const width of widths) {
    const { context, page } = await newPage(browser, width);
    await page.goto(`${BASE}/${locale}${PAGES[key]}`, { waitUntil: 'load' });
    await page.evaluate(() => document.fonts.ready);
    await page.waitForTimeout(500);
    const r = await page.evaluate(() => {
        const rules = [];
        for (const sheet of document.styleSheets) {
            let list;
            try { list = sheet.cssRules; } catch { continue; }
            for (const rule of list) {
                if (!(rule instanceof CSSFontFaceRule)) continue;
                const s = rule.style;
                const fam = s.getPropertyValue('font-family').replace(/["']/g, '').trim();
                const src = s.getPropertyValue('src');
                const m = /url\("?([^")]+)"?\)/.exec(src);
                if (!m) continue; // local() fallbacks
                const w = s.getPropertyValue('font-weight').trim().split(/\s+/).map(Number);
                const ranges = s.getPropertyValue('unicode-range').split(',').map((r) => r.trim().replace(/^U\+/i, '')).filter(Boolean).map((r) => {
                    const [a, b] = r.split('-');
                    return [parseInt(a, 16), parseInt(b ?? a, 16)];
                });
                rules.push({ fam, lo: w[0], hi: w[1] ?? w[0], ranges, file: m[1].split('/').pop().replace(/\.[0-9a-f]{8}\.woff2$/, '') });
            }
        }
        const fams = [...new Set(rules.map((r) => r.fam))];
        // CSS font matching (weight) within a family: the band holding the weight, else the nearest per spec.
        const bandFor = (fam, weight) => {
            const mine = rules.filter((r) => r.fam === fam);
            const bands = [...new Map(mine.map((r) => [`${r.lo}-${r.hi}`, { lo: r.lo, hi: r.hi }])).values()];
            const hit = bands.find((b) => weight >= b.lo && weight <= b.hi);
            if (hit) return hit;
            const dist = (b) => (weight < b.lo ? b.lo - weight : weight - b.hi);
            let order;
            if (weight >= 400 && weight <= 500) order = [...bands.filter((b) => b.lo >= weight && b.lo <= 500).sort((a, b) => a.lo - b.lo), ...bands.filter((b) => b.hi < weight).sort((a, b) => b.hi - a.hi), ...bands.filter((b) => b.lo > 500).sort((a, b) => a.lo - b.lo)];
            else if (weight > 500) order = [...bands.filter((b) => b.lo >= weight).sort((a, b) => a.lo - b.lo), ...bands.filter((b) => b.hi < weight).sort((a, b) => b.hi - a.hi)];
            else order = [...bands.filter((b) => b.hi <= weight).sort((a, b) => b.hi - a.hi), ...bands.filter((b) => b.lo > weight).sort((a, b) => a.lo - b.lo)];
            return order[0] ?? bands.sort((a, b) => dist(a) - dist(b))[0];
        };
        const fileFor = (families, weight, cp) => {
            for (const f of families) {
                if (!fams.includes(f)) continue;
                const band = bandFor(f, weight);
                const rule = rules.filter((r) => r.fam === f && r.lo === band.lo && r.hi === band.hi).reverse().find((r) => r.ranges.some(([a, b]) => cp >= a && cp <= b));
                if (rule) return rule.file;
            }
            return null;
        };
        const first = new Map();
        const all = new Map();
        const visibleRect = (q) => q.width && q.bottom > 0 && q.top < innerHeight && q.right > 0 && q.left < innerWidth;
        const note = (el, cs, text, inView) => {
            const families = cs.fontFamily.split(',').map((f) => f.replace(/["']/g, '').trim());
            const weight = Number(cs.fontWeight) || 400;
            for (const ch of text) {
                const file = fileFor(families, weight, ch.codePointAt(0));
                if (!file) continue;
                const who = (el.className?.toString?.() || el.tagName).split(' ')[0].slice(0, 28);
                if (!all.has(file)) all.set(file, who);
                if (inView && !first.has(file)) first.set(file, `${who} "${ch}"`);
            }
        };
        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        while (walker.nextNode()) {
            const n = walker.currentNode;
            const el = n.parentElement;
            const text = n.textContent;
            if (!el || !text.trim() || el.closest('script, style, template, noscript')) continue;
            const cs = getComputedStyle(el);
            if (cs.display === 'none' || el.getClientRects().length === 0) continue; // not laid out: no request
            const range = document.createRange();
            range.selectNodeContents(n);
            note(el, cs, text, cs.visibility !== 'hidden' && [...range.getClientRects()].some(visibleRect));
        }
        // Generated text (the sample documents' watermark lines: content: attr(data-wm)).
        for (const el of document.querySelectorAll('[data-wm]')) {
            const cs = getComputedStyle(el, '::before');
            const text = (cs.content || '').replace(/^["']|["']$/g, '');
            if (!text || text === 'none' || cs.display === 'none' || el.getClientRects().length === 0) continue;
            note(el, cs, text, getComputedStyle(el).visibility !== 'hidden' && [...el.getClientRects()].some(visibleRect));
        }
        // what the browser actually loaded (status), for comparison
        const loaded = [...document.fonts].filter((f) => f.status === 'loaded').length;
        return { first: Object.fromEntries(first), all: Object.fromEntries(all), loaded };
    });
    const later = Object.keys(r.all).filter((f) => !(f in r.first));
    console.log(`${key.padEnd(4)} ${locale} ${String(width).padEnd(4)} FIRST ${Object.entries(r.first).map(([f, who]) => `${f}(${who})`).join(' ')}\n${' '.repeat(13)}LATER ${later.map((f) => `${f}(${r.all[f]})`).join(' ')}`);
    await context.close();
}
await browser.close();
