#!/usr/bin/env node
/**
 * Hero callout fields (owner: hero; Design 2.1 brief D16) → resources/js/d2/hero/callout-layout.json, and a check of
 * the callouts as the page draws them.
 *
 * The hero lays its callouts out on the server (App\Design\D2\HeroStage): a dot at the edge of each key field, one
 * leader through the empty paper beside it, the label in the clear margin on the inline-end side of the pile. That
 * needs the key fields' boxes and the labels' heights, which only a browser can measure (fonts, Arabic line heights):
 * this script opens the home page in every language, brings every sample document to the top of the pile in turn
 * (rotation 0) and stores every [data-field] box in natural document px, x from the document's inline-start edge, and
 * every label's height (canvas px at 1440 px, the smallest wide canvas scale), with a hash of the documents' copy in
 * that language (HeroStage::copyHash()) and the label margin it was measured in (HeroStage::margin('wide')).
 * HeroStage ignores a language whose copy changed (its callouts then sit at the rough Docs::CALLOUTS anchors) and
 * tests/Feature/Design/D2/HeroTest.php fails until this script runs again.
 *
 * Run after any change to the sample documents (components/d2/doc/*, components/doc.css), their copy
 * (lang/*\/proposed-d2/docs.php) or the pile's geometry (HeroStage), with the Design 2 dev server (npm run dev:d2,
 * public/hot-d2) and a PHP server for this working tree:
 *   node scripts/d2/callout-layout.mjs [--base=http://127.0.0.1:8042]      measure, write, then check
 *   node scripts/d2/callout-layout.mjs --check                             check only
 * (Label heights feed the layout of the labels: after a geometry change run it twice, the second run measures the
 * labels in their new margin.)
 *
 * The check (every language, every document on top of the pile, 1440, 1280 and 768 px, and the phone layout at
 * 390 px) fails when:
 *   - a dot covers text on its document (its box against the document's text boxes);
 *   - a leader crosses text on its document;
 *   - a label (or a phone marker) leaves the stage, covers any paper (any sheet of the pile, at its box), comes
 *     within 12 px of another label, or (from 1280 px) of the H1's text;
 *   - a sheet behind the top one shows ink (D15: its text must be transparent).
 */
import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';
import { chromium } from '@playwright/test';

const args = Object.fromEntries(process.argv.slice(2).map((a) => { const [k, v] = a.replace(/^--/, '').split(/=(.*)/s); return [k, v ?? true]; }));
const BASE = args.base || 'http://127.0.0.1:8042';
const ROOT = new URL('../../', import.meta.url).pathname;
const OUT = `${ROOT}resources/js/d2/hero/callout-layout.json`;
const LOCALES = ['en', 'ar', 'ru'];
const HMR_STUB = 'export const createHotContext=()=>({data:{},accept(){},acceptExports(){},dispose(){},prune(){},invalidate(){},decline(){},on(){},off(){},send(){}});export const updateStyle=()=>{};export const removeStyle=()=>{};export const injectQuery=(u)=>u;export class ErrorOverlay{}';

const browser = await chromium.launch({ channel: 'chrome', headless: true });

async function open(width, locale) {
    const context = await browser.newContext({ viewport: { width, height: 900 }, reducedMotion: 'reduce', locale });
    await context.addCookies([{ name: 'summit_design', value: '2', url: BASE }, { name: 'summit_qa', value: '1', url: BASE }]);
    await context.route(/\/@vite\/client(?:\?.*)?$/, (route) => route.fulfill({ status: 200, contentType: 'text/javascript', body: HMR_STUB }));
    const page = await context.newPage();
    await page.goto(`${BASE}/${locale}/`, { waitUntil: 'load' });
    await page.evaluate(() => document.fonts.ready);
    await page.waitForTimeout(300);
    return { context, page };
}

/** Every [data-field] box of every hero document (on top of the pile), natural px, x from the inline-start edge, and the labels' heights (canvas px). */
function measure() {
    // Bring a document to the top of the pile (as the switch leaves it, without motion).
    const topOf = (stage, type) => {
        const all = [...stage.querySelectorAll('[data-hero-doc]')];
        const order = [type, ...all.map((d) => d.dataset.heroDoc).filter((t) => t !== type)];
        for (const d of all) {
            d.dataset.depth = String(order.indexOf(d.dataset.heroDoc));
            d.classList.toggle('is-active', d.dataset.heroDoc === type);
        }
        stage.dataset.active = type;
    };
    const stage = document.querySelector('.hero__visual [data-hero-stage]');
    const was = stage.dataset.active;
    const fields = {};
    const labels = {};
    for (const wrap of stage.querySelectorAll('[data-hero-doc]')) {
        const type = wrap.dataset.heroDoc;
        topOf(stage, type);
        const doc = wrap.querySelector('.doc');
        const b = doc.getBoundingClientRect();
        const k = b.width / doc.offsetWidth;
        const rtl = getComputedStyle(doc).direction === 'rtl';
        const r1 = (v) => Math.round(v * 10) / 10;
        const out = {};
        for (const f of doc.querySelectorAll('[data-field]')) {
            const r = f.getBoundingClientRect();
            const x = rtl ? b.right - r.right : r.left - b.left;
            out[f.dataset.field] = [r1(x / k), r1((r.top - b.top) / k), r1(r.width / k), r1(r.height / k)];
        }
        fields[type] = out;
        const canvas = stage.querySelector('[data-canvas]');
        const kc = canvas.getBoundingClientRect().width / canvas.offsetWidth;
        labels[type] = Object.fromEntries([...wrap.querySelectorAll('.hero-callout')].map((el) => [el.dataset.callout, r1(el.getBoundingClientRect().height / kc)]));
    }
    topOf(stage, was);
    return { fields, labels };
}

/** The callouts of every document as drawn (all of them made visible in turn), with the problems found. */
function inspect({ h1 }) {
    // Bring a document to the top of the pile (as the switch leaves it, without motion).
    const topOf = (stage, type) => {
        const all = [...stage.querySelectorAll('[data-hero-doc]')];
        const order = [type, ...all.map((d) => d.dataset.heroDoc).filter((t) => t !== type)];
        for (const d of all) {
            d.dataset.depth = String(order.indexOf(d.dataset.heroDoc));
            d.classList.toggle('is-active', d.dataset.heroDoc === type);
        }
        stage.dataset.active = type;
    };
    const problems = [];
    const stage = document.querySelector('.hero__visual [data-hero-stage]');
    const s = stage.getBoundingClientRect();
    const shown0 = stage.dataset.active;
    const inter = (a, b, pad = 0) => a.left < b.right + pad && b.left < a.right + pad && a.top < b.bottom + pad && b.top < a.bottom + pad;
    const shown = (el) => el.getClientRects().length > 0 && getComputedStyle(el).display !== 'none';
    const title = document.querySelector('#hero-title');
    const range = document.createRange();
    range.selectNodeContents(title);
    const h1Rects = h1 ? [...range.getClientRects()] : [];
    for (const wrap of stage.querySelectorAll('[data-hero-doc]')) {
        const type = wrap.dataset.heroDoc;
        topOf(stage, type);
        // Every sheet of the pile (its document box, rotation included), the one on top among them.
        const sheets = [...stage.querySelectorAll('[data-hero-doc] .doc')].map((el) => el.getBoundingClientRect());
        const doc = wrap.querySelector('.doc');
        const paper = [...doc.querySelectorAll('.doc__sheet')].map((el) => el.getBoundingClientRect());
        // D15: no ink on the sheets behind the top one.
        for (const other of stage.querySelectorAll('[data-hero-doc]:not(.is-active)')) {
            const inked = [...other.querySelectorAll('.doc__sheet > :not(.cheque__waves):not(.doc__fold), .doc__wm')].filter((el) => parseFloat(getComputedStyle(el).opacity) > 0.01);
            if (inked.length) problems.push(`${type} on top: ${other.dataset.heroDoc} behind it shows ink (${inked.length} parts)`);
        }
        const texts = [];
        const walker = document.createTreeWalker(doc.querySelector('.doc__body'), NodeFilter.SHOW_TEXT);
        for (let n = walker.nextNode(); n; n = walker.nextNode()) {
            if (!n.textContent.trim() || n.parentElement.closest('.doc__wm')) continue;
            const r = document.createRange();
            r.selectNodeContents(n);
            texts.push(...[...r.getClientRects()].filter((x) => x.width > 1));
        }
        const labels = [...wrap.querySelectorAll('.hero-callout')].filter(shown).map((el) => ({ id: el.dataset.callout, r: el.getBoundingClientRect() }));
        for (const dot of [...wrap.querySelectorAll('.hero-dot')].filter(shown)) {
            const r = dot.getBoundingClientRect();
            if (texts.some((t) => inter(r, t))) problems.push(`${type} ${dot.dataset.callout}: the dot covers text`);
        }
        for (const line of [...wrap.querySelectorAll('.hero-line')].filter(shown)) {
            // Each straight segment of the leader (M x y H x1, then V y2 H x3 for a moved label), on screen.
            const ctm = line.getScreenCTM();
            const pt = (x, y) => ({ x: ctm.a * x + ctm.c * y + ctm.e, y: ctm.b * x + ctm.d * y + ctm.f });
            const tokens = (line.getAttribute('d') || '').match(/[MHV]|-?[\d.]+/g) || [];
            let x = 0;
            let y = 0;
            const segs = [];
            for (let i = 0; i < tokens.length; i++) {
                const c = tokens[i];
                if (c === 'M') { x = Number(tokens[++i]); y = Number(tokens[++i]); }
                else if (c === 'H') { const x1 = Number(tokens[++i]); segs.push([pt(x, y), pt(x1, y)]); x = x1; }
                else if (c === 'V') { const y1 = Number(tokens[++i]); segs.push([pt(x, y), pt(x, y1)]); y = y1; }
            }
            for (const [a, b] of segs) {
                const thin = { left: Math.min(a.x, b.x) - 0.5, right: Math.max(a.x, b.x) + 0.5, top: Math.min(a.y, b.y) - 0.5, bottom: Math.max(a.y, b.y) + 0.5 };
                if (texts.some((t) => inter(thin, t))) {
                    problems.push(`${type} ${line.dataset.callout}: the leader crosses text`);
                    break;
                }
            }
        }
        labels.forEach(({ id, r }, i) => {
            if (r.left < s.left - 0.5 || r.right > s.right + 0.5 || r.top < s.top - 0.5 || r.bottom > s.bottom + 0.5) problems.push(`${type} ${id}: the label leaves the stage`);
            if ([...paper, ...sheets].some((p) => inter(r, p))) problems.push(`${type} ${id}: the label covers paper`);
            for (const o of labels.slice(i + 1)) if (inter(r, o.r, 12 - 0.5)) problems.push(`${type} ${id}/${o.id}: labels closer than 12 px`);
            if (h1Rects.some((t) => inter(r, t, 12))) problems.push(`${type} ${id}: the label comes within 12 px of the H1`);
        });
    }
    topOf(stage, shown0);
    return problems;
}

if (!args.check) {
    const hashes = JSON.parse(execFileSync('php', ['artisan', 'tinker', '--execute', "echo json_encode(array_combine(['en','ar','ru'], array_map(fn ($l) => App\\Design\\D2\\HeroStage::copyHash($l), ['en','ar','ru'])));"], { cwd: ROOT, encoding: 'utf8' }).trim().split('\n').pop());
    const room = Number(execFileSync('php', ['artisan', 'tinker', '--execute', 'echo App\\Design\\D2\\HeroStage::margin("wide")[1];'], { cwd: ROOT, encoding: 'utf8' }).trim().split('\n').pop());
    const fields = {};
    const labels = {};
    for (const locale of LOCALES) {
        const { context, page } = await open(1440, locale);
        const m = await page.evaluate(measure);
        fields[locale] = m.fields;
        labels[locale] = m.labels;
        await context.close();
    }
    const table = {
        generator: 'scripts/d2/callout-layout.mjs',
        note: 'Key field boxes of the hero sample documents (natural px; x from the inline-start edge) and their callout labels\' heights (canvas px at 1440 px, in the label margin `room`), per language, with a hash of the documents\' copy (App\\Design\\D2\\HeroStage::copyHash). Generated: do not edit.',
        hash: hashes,
        room,
        fields,
        labels,
    };
    writeFileSync(OUT, `${JSON.stringify(table, null, 1)}\n`);
    console.log(`wrote ${OUT.replace(ROOT, '')}`);
}

let failures = 0;
for (const locale of LOCALES) {
    for (const width of [1440, 1280, 768, 390]) {
        const { context, page } = await open(width, locale);
        const problems = await page.evaluate(inspect, { h1: width >= 1280 });
        await context.close();
        failures += problems.length;
        console.log(`${locale} ${width}: ${problems.length ? problems.join('; ') : 'clean'}`);
    }
}
await browser.close();
JSON.parse(readFileSync(OUT, 'utf8'));
process.exit(failures ? 1 : 0);
