#!/usr/bin/env node
/**
 * Header fit calculator (area shell / hero-perf; <x-d2.shell.header>, components/header.css). Renders the
 * real header with every piece forced visible, measures each piece, then computes the spare width of each
 * configuration per language and band (content = bar width minus padding). Its numbers calibrate the
 * first-paint nav thresholds ($inlineFrom in resources/views/components/d2/shell/header.blade.php), which
 * header.js then refines to the real width (priority+). Re-run after changing a nav label, the CTA, the
 * language pill, the header paddings or the fonts.
 *
 *   node scripts/d2/header-fit.mjs [--base=http://127.0.0.1:8040] [--out=storage/app/d2/shell/fit.json]
 *   SIM=1 … maps the Design 2 family names onto the plain IBM Plex / Source Serif subsets (the upstream
 *   families the Design 2 fonts are cut from), to compare a font change.
 * Needs a PHP server for this working tree with Design 2 assets (dev server or build).
 */
import { chromium } from '@playwright/test';
import fs from 'node:fs';
const args = Object.fromEntries(process.argv.slice(2).map((a) => { const [k, v] = a.replace(/^--/, '').split(/=(.*)/s); return [k, v ?? true]; }));
const BASE = args.base || process.env.QA_BASE_URL || 'http://127.0.0.1:8040';
const SIM = process.env.SIM === '1';
const CTA_PAD = Number(process.env.CTA_PAD || 42); // both sides together (button.css owner decides)
const M = JSON.parse(fs.readFileSync('public/fonts/d2/manifest.json', 'utf8'));
const faces = `
@font-face{font-family:"D2 Sans";src:url(${M['sans-latin']});font-weight:100 900}
@font-face{font-family:"D2 Sans";src:url(${M['sans-cyrillic']});font-weight:100 900;unicode-range:U+0400-045F}
@font-face{font-family:"D2 Sans Arabic";src:url(${M['sans-arabic-400']});font-weight:400}
@font-face{font-family:"D2 Sans Arabic";src:url(${M['sans-arabic-500']});font-weight:450 549}
@font-face{font-family:"D2 Sans Arabic";src:url(${M['sans-arabic-600']});font-weight:550 649}
@font-face{font-family:"D2 Sans Arabic";src:url(${M['sans-arabic-700']});font-weight:650 900}
@font-face{font-family:"D2 Serif";src:url(${M['serif-latin']});font-weight:100 900}
@font-face{font-family:"D2 Mono";src:url(${M['mono-latin']});font-weight:100 900}`;
const browser = await chromium.launch({ channel: 'chrome' });
const out = {};
for (const loc of ['en', 'ar', 'ru']) {
  const ctx = await browser.newContext({ viewport: { width: 1900, height: 900 }, bypassCSP: true });
  await ctx.addCookies([{ name: 'summit_design', value: '2', url: BASE }, { name: 'summit_qa', value: '1', url: BASE }]);
  const page = await ctx.newPage();
  await page.goto(`${BASE}/${loc}/practice-areas/labor-law/`, { waitUntil: 'networkidle' });
  out[loc] = await page.evaluate(async ({ SIM, faces }) => {
    const st = document.createElement('style');
    st.textContent = (SIM ? faces : '') + `.d2-header .d2-nav__item, .d2-header .d2-nav__more { display:flex !important }
      .d2-header .d2-brand__name { display:block !important }
      .d2-header .d2-header__cta-full, .d2-header .d2-header__cta-short { display:inline !important }
      .d2-header .d2-search__kbd { display:inline-flex !important }
      .d2-header .d2-search { inline-size:auto !important; padding-inline: 12px 8px !important }`;
    document.head.append(st);
    await document.fonts.ready;
    await new Promise((r) => setTimeout(r, 400));
    const w = (el) => (el ? Math.round(el.getBoundingClientRect().width * 10) / 10 : 0);
    const q = (s) => document.querySelector('.d2-header ' + s);
    const items = Object.fromEntries([...document.querySelectorAll('.d2-header .d2-nav__list > li')].map((li, i) => [li.classList.contains('d2-nav__more') ? 'more' : ['practice', 'resources', 'cases', 'faq', 'about', 'contact'][i], w(li)]));
    const kbd = w(q('.d2-search__kbd'));
    return {
      font: getComputedStyle(q('.d2-nav__link')).fontFamily.slice(0, 40),
      tile: w(q('.d2-brand__tile')), wordmark: w(q('.d2-brand__name')),
      items, searchCompact: w(q('.d2-search')), searchIcon: 44, pill: w(q('.langmenu')),
      ctaFull: w(q('.d2-header__cta-full')), ctaShort: w(q('.d2-header__cta-short')),
    };
  }, { SIM, faces });
  await ctx.close();
}
await browser.close();
if (args.out) fs.writeFileSync(String(args.out), JSON.stringify(out, null, 2));
// Layout model of header.css: brand (tile [+ 12 + wordmark]) + 8 margin, bar gap 16 between brand | nav | tools,
// tools gap 8 between search | pill | CTA. Content width = min(1280, viewport - 2 * pad).
const content = (vw) => Math.min(1280, vw - 2 * (vw >= 1280 ? 80 : vw >= 768 ? 32 : 24));
for (const [loc, m] of Object.entries(out)) {
  console.log(`\n${loc} (${m.font})  tile ${m.tile} wordmark ${m.wordmark} compactSearch ${m.searchCompact} pill ${m.pill} ctaFull ${m.ctaFull}+${CTA_PAD} ctaShort ${m.ctaShort}+${CTA_PAD}`);
  console.log('  nav', JSON.stringify(m.items));
  const all = ['practice', 'resources', 'cases', 'faq', 'about', 'contact'];
  const navW = (inline) => inline.reduce((s, k) => s + (m.items[k] || 0), 0) + (inline.length < 6 ? m.items.more : 0);
  for (const vw of [1024, 1280, 1440]) {
    const c = content(vw);
    const rows = [];
    for (const wordmark of [true, false]) for (const field of [false, true]) for (const full of [true, false]) for (let n = 6; n >= 1; n--) {
      const inline = all.slice(0, n);
      const brand = m.tile + (wordmark ? 12 + m.wordmark : 0);
      const search = field ? 280 : vw >= 1280 ? m.searchCompact : 44;
      const tools = search + 8 + m.pill + 8 + (full ? m.ctaFull : m.ctaShort) + CTA_PAD;
      const total = brand + 16 + navW(inline) + 16 + tools;
      rows.push({ wordmark, field, full, n, spare: Math.round(c - total) });
    }
    const fits = rows.filter((r) => r.spare >= 0);
    const best = (pred) => fits.filter(pred).sort((a, b) => b.n - a.n || b.spare - a.spare)[0];
    const show = (r) => (r ? `items=${r.n} spare=${r.spare}` : 'none');
    const spec = best((r) => !r.wordmark && !r.field && r.full === (vw >= 1280));
    console.log(`  ${vw} (content ${c}) CHOSEN tile+${vw >= 1280 ? 'full' : 'short'}: ${show(spec)} |`,
      'wordmark+compact+full:', show(best((r) => r.wordmark && !r.field && r.full)),
      '| tile+compact+full:', show(best((r) => !r.wordmark && !r.field && r.full)),
      '| tile+compact+short:', show(best((r) => !r.wordmark && !r.field && !r.full)),
      '| tile+field+full:', show(best((r) => !r.wordmark && r.field && r.full)),
      '| wordmark+field+full:', show(best((r) => r.wordmark && r.field && r.full)));
  }
}
