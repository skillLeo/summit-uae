#!/usr/bin/env node
/**
 * Design 2 QA report (upgrade spec §9 "screenshot QA loop" step 2, §12 G2): qa/report.html from a run folder
 * qa/shots/<date>/ written by playwright.d2.config.mjs, scripts/qa/d1-visual.mjs and lighthouse-d2.mjs.
 *
 *   node scripts/qa/d2-report.mjs                    the run of today (else the latest run)
 *   node scripts/qa/d2-report.mjs --run=2026-11-10   a given run
 *   node scripts/qa/d2-report.mjs --diff             exact changed-pixel counts against the comparison images
 *   node scripts/qa/d2-report.mjs --tiles[=failing]  readable JPEG tiles of the full-page shots (<run>/tiles/)
 *   node scripts/qa/d2-report.mjs --init-findings    also create qa/findings.md from its template (never overwrites)
 *
 * Every screenshot is grouped by page, width and language and shown next to its comparison image:
 * qa/reference/<file> (or qa/reference/<visual|hero>/<file>) when an approved reference exists, else the
 * previous capture (<run>/<kind>/prev/<file>, then the same file in the latest earlier run). Design 1 pages
 * are shown as baseline (git tag design-1) next to the branch. Each capture lists its measured checks.
 * Also writes <run>/findings-auto.md: every failing check as a row for qa/findings.md.
 */
import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, readdirSync, readFileSync, statSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import sharp from 'sharp';
import { LOCALES, PAGES, QA_DIR, REFERENCE_DIR, RUN, SHOTS_DIR, WIDTHS } from '../../qa/lib/config.mjs';
import { FINDINGS_TEMPLATE } from '../../qa/lib/findings-template.mjs';
import { compareImages } from '../../qa/lib/pixels.mjs';
import { failingChecks, warningChecks } from '../../qa/lib/verdict.mjs';

const args = Object.fromEntries(
    process.argv.slice(2).map((a) => {
        const [k, ...v] = a.replace(/^--/, '').split('=');
        return [k, v.length ? v.join('=') : 'true'];
    }),
);
if (args.help) {
    console.log('Usage: node scripts/qa/d2-report.mjs [--run=<folder>] [--diff] [--tiles[=failing]] [--init-findings] [--out=qa/report.html]');
    process.exit(0);
}
if (args['init-findings']) {
    const file = path.join(QA_DIR, 'findings.md');
    if (existsSync(file)) console.log('qa/findings.md exists: left unchanged.');
    else {
        writeFileSync(file, FINDINGS_TEMPLATE);
        console.log('Created qa/findings.md from qa/lib/findings-template.mjs.');
    }
}

// ------------------------------------------------------------------------------------ run folders
const isRun = (d) => ['visual', 'hero', 'd1-visual', 'lighthouse'].some((k) => existsSync(path.join(SHOTS_DIR, d, k)));
const runs = existsSync(SHOTS_DIR)
    ? readdirSync(SHOTS_DIR)
          .filter((d) => statSync(path.join(SHOTS_DIR, d)).isDirectory() && isRun(d))
          .map((d) => ({ name: d, mtime: statSync(path.join(SHOTS_DIR, d)).mtimeMs }))
          .sort((a, b) => b.mtime - a.mtime)
    : [];
const current = args.run || (runs.some((r) => r.name === RUN) ? RUN : runs[0]?.name);
if (!current || !existsSync(path.join(SHOTS_DIR, current))) {
    console.error(`No QA run found under ${path.relative(process.cwd(), SHOTS_DIR)}/ (run npm run qa:d2 first).`);
    process.exit(2);
}
const RUN_DIR = path.join(SHOTS_DIR, current);
const earlier = runs.filter((r) => r.name !== current).map((r) => r.name);
const OUT = path.resolve(args.out || path.join(QA_DIR, 'report.html'));
const rel = (abs) => path.relative(path.dirname(OUT), abs).split(path.sep).join('/');

const readJson = (file) => {
    try {
        return JSON.parse(readFileSync(file, 'utf8'));
    } catch {
        return null;
    }
};
const listJson = (dir) => (existsSync(dir) ? readdirSync(dir).filter((f) => f.endsWith('.json')).map((f) => readJson(path.join(dir, f))).filter(Boolean) : []);
const sha = (file) => createHash('sha1').update(readFileSync(file)).digest('hex');

/** The image to compare a capture with: an approved reference, else the previous capture of it. */
function comparisonFor(kind, file) {
    const name = path.basename(file);
    for (const ref of [path.join(REFERENCE_DIR, name), path.join(REFERENCE_DIR, kind, name)]) {
        if (existsSync(ref)) return { file: ref, label: 'reference', kind: 'reference' };
    }
    const prev = path.join(RUN_DIR, kind, 'prev', name);
    if (existsSync(prev)) return { file: prev, label: `previous capture (${current})`, kind: 'previous' };
    for (const run of earlier) {
        const f = path.join(SHOTS_DIR, run, kind, name);
        if (existsSync(f)) return { file: f, label: `previous run (${run})`, kind: 'previous' };
    }
    return null;
}

async function change(currentFile, cmp) {
    if (!cmp || !existsSync(currentFile)) return null;
    if (sha(currentFile) === sha(cmp.file)) return { same: true, text: 'identical' };
    if (!args.diff) return { same: false, text: 'changed' };
    const r = await compareImages(currentFile, cmp.file);
    return { same: r.different === 0, text: r.different === 0 ? 'identical pixels' : `${(r.ratio * 100).toFixed(2)} % of pixels changed${r.sameSize ? '' : ` (size ${r.sizeA.join('×')} → ${r.sizeB.join('×')})`}` };
}

// ------------------------------------------------------------------------------------ data
const visual = listJson(path.join(RUN_DIR, 'visual'));
const hero = listJson(path.join(RUN_DIR, 'hero'));
const d1 = readJson(path.join(RUN_DIR, 'd1-visual', 'summary.json'));
const lighthouse = readJson(path.join(QA_DIR, 'lighthouse-d2.json'));
const runInfo = readJson(path.join(RUN_DIR, 'run.json')) ?? {};
const verdict = readJson(path.join(RUN_DIR, 'verdict.json'));

const pageOrder = PAGES.map((p) => p.key);
const variantOrder = ['default', 'reduced', 'nojs'];
const localeOrder = Object.keys(LOCALES);
visual.sort(
    (a, b) =>
        pageOrder.indexOf(a.page) - pageOrder.indexOf(b.page) ||
        WIDTHS.indexOf(a.width) - WIDTHS.indexOf(b.width) ||
        variantOrder.indexOf(a.variant) - variantOrder.indexOf(b.variant) ||
        localeOrder.indexOf(a.locale) - localeOrder.indexOf(b.locale),
);
hero.sort((a, b) => WIDTHS.indexOf(a.width) - WIDTHS.indexOf(b.width) || a.motion.localeCompare(b.motion) || localeOrder.indexOf(a.locale) - localeOrder.indexOf(b.locale));

// ------------------------------------------------------------------------------------ html helpers
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const SEVERITY = {
    status: 'high', langDir: 'high', design2: 'high', overflow: 'high', console: 'high', axe: 'high', h1: 'high', missingCopy: 'high', watermark: 'high',
    bands: 'medium', smallText: 'medium', duplicateIds: 'medium', headings: 'low', underlinedLinks: 'low', banned: 'medium',
    screencast: 'high', stage: 'high', h1Visible: 'high', h1NotAnimated: 'high', lcpIsH1: 'medium', cls: 'medium', docsRestBy2000: 'medium', calloutsBy3000: 'medium', entrySide: 'medium', finalAtFirstFrame: 'medium', noMotion: 'medium',
};

/** One-line summary of a check. */
function brief(id, c) {
    switch (id) {
        case 'status': return `HTTP ${c.actual} (expected ${c.expected})`;
        case 'langDir': return `lang ${c.actual?.lang} dir ${c.actual?.dir}`;
        case 'design2': return c.ok ? 'Design 2' : `not Design 2 (data-design=${c.actual})`;
        case 'overflow': return c.ok ? 'no horizontal overflow' : `overflow ${c.px}px: ${(c.culprits ?? []).slice(0, 2).map((x) => x.el).join('; ')}`;
        case 'smallText': return c.ok ? `no text < ${c.min}px` : `${c.count} under ${c.min}px: ${(c.samples ?? []).slice(0, 3).map((s) => `${s.size}px “${s.text}”`).join('; ')}`;
        case 'h1': return c.ok ? 'one H1' : `${c.count} H1 (${c.visible} visible)`;
        case 'duplicateIds': return c.ok ? 'ids unique' : `duplicate ids: ${c.list.slice(0, 5).map((d) => `${d.id}×${d.n}`).join(', ')}`;
        case 'console': return c.ok ? 'console clean' : `${c.count} error(s): ${(c.errors ?? [])[0]?.slice(0, 160) ?? ''}`;
        case 'bands': return c.ok ? `no band > ${c.max}px${c.largest ? ` (largest gap ${c.largest.height}px @${c.largest.top})` : ''}` : `${c.list.length} band(s): ${c.list.slice(0, 3).map((b) => `${b.height}px @${b.top}`).join(', ')}`;
        case 'axe': return c.skipped ? `skipped (${c.skipped})` : c.ok ? `0 serious/critical${c.other ? `, ${c.other} moderate/minor` : ''}${c.incidental ? `, ${c.incidental} incidental (sample art)` : ''}${c.passed !== undefined ? ` · ${c.passed} rules passed` : ''}` : `${c.violations.filter((v) => ['serious', 'critical'].includes(v.impact)).map((v) => `${v.id}×${v.nodes}`).join(', ')}`;
        case 'missingCopy': return c.ok ? 'no missing copy' : `missing copy: ${c.list.slice(0, 2).join('; ')}`;
        case 'watermark': return c.ok ? `${c.docs} sample doc(s) watermarked` : `${c.without.length} doc(s) without watermark`;
        case 'headings': return c.ok ? 'heading order' : `heading skips: ${(c.skips ?? []).slice(0, 3).map((s) => `${s.from}→${s.to}`).join(', ')}${c.first && c.first !== 'h1' ? `; first heading ${c.first}` : ''}`;
        case 'underlinedLinks': return c.ok ? 'no stray underlines' : `${c.list.length} underlined link(s) outside running text`;
        case 'banned': return c.ok ? 'no banned phrase' : `phrases: ${c.list.slice(0, 3).map((b) => `${b.id} “${b.context}”`).join('; ')}`;
        case 'proposed': return `${c.count} proposed-copy element(s)`;
        case 'screencast': return `${c.frames} screencast frame(s)`;
        case 'stage': return c.ok ? `stage: ${c.docs} docs (${c.canvas})` : 'no hero stage';
        case 'h1Visible': return `H1 opacity ${c.opacity} at first paint`;
        case 'h1NotAnimated': return c.ok ? 'H1 never animated' : `H1 animated: ${c.animations.join(', ')}`;
        case 'lcpIsH1': return c.ok ? `LCP = H1 (${c.at} ms)` : `LCP = ${c.element ?? 'n/a'} (${c.at ?? '?'} ms)`;
        case 'cls': return `CLS ${c.value} (max ${c.max})`;
        case 'docsRestBy2000': return c.ok ? 'documents at rest by 2000 ms' : `not at rest by 2000 ms: ${(c.docs ?? []).filter((d) => !d.rests || !d.visible).map((d) => `${d.type}${d.visible ? '' : ' (hidden)'}`).join(', ') || 'no documents'}`;
        case 'calloutsBy3000': return `${c.visible} callout(s) visible at 3000 ms`;
        case 'entrySide': return c.warn ? 'entry side not measurable (no document in flight)' : c.ok ? `enters ${c.expected}` : `wrong side: ${c.wrongSide.join(', ')}`;
        case 'finalAtFirstFrame': return c.ok ? 'final composition at first paint' : `not final at first paint (${c.callouts} callouts)`;
        case 'noMotion': return c.different === null || c.different === undefined ? 'stage frames not comparable' : c.ok ? 'stage still (600 = 3000 ms)' : `stage changes after first paint: ${c.different} px (${c.framesAt?.join(' vs ')} ms)`;
        default: return JSON.stringify(c).slice(0, 120);
    }
}

function checkList(checks = {}) {
    const items = Object.entries(checks).map(([id, c]) => {
        const state = c.info ? 'info' : c.ok ? 'ok' : c.warn ? 'warn' : 'fail';
        return `<li class="c-${state}" title="${esc(JSON.stringify(c).slice(0, 1500))}"><b>${esc(id)}</b> ${esc(brief(id, c))}</li>`;
    });
    return `<ul class="checks">${items.join('')}</ul>`;
}

const stateOf = (record) => (record.error ? 'error' : failingChecks(record.checks).length ? 'fail' : warningChecks(record.checks).length ? 'warn' : 'pass');

function pair(currentFile, cmp, changeInfo, alt) {
    const cur = existsSync(currentFile)
        ? `<a href="${esc(rel(currentFile))}" target="_blank" rel="noopener"><img loading="lazy" src="${esc(rel(currentFile))}" alt="${esc(alt)}"></a>`
        : '<p class="none">no screenshot</p>';
    const other = cmp
        ? `<a href="${esc(rel(cmp.file))}" target="_blank" rel="noopener"><img loading="lazy" src="${esc(rel(cmp.file))}" alt="${esc(cmp.label)}"></a>`
        : '<p class="none">no reference image (add qa/reference/' + esc(path.basename(currentFile)) + ') and no previous capture</p>';
    return `<div class="pair">
  <figure><figcaption>this run</figcaption><div class="pane" data-sync>${cur}</div></figure>
  <figure><figcaption>${esc(cmp?.label ?? 'comparison')}${changeInfo ? ` · <span class="${changeInfo.same ? 'same' : 'changed'}">${esc(changeInfo.text)}</span>` : ''}</figcaption><div class="pane" data-sync>${other}</div></figure>
</div>`;
}

// ------------------------------------------------------------------------------------ tiles
async function tiles(record) {
    const file = path.join(RUN_DIR, record.file);
    if (!existsSync(file)) return [];
    // Decode once; every slice is cut from the raw pixels (re-decoding a 30 000 px PNG per slice is slow).
    const { data, info: meta } = await sharp(file).removeAlpha().raw().toBuffer({ resolveWithObject: true });
    const cols = meta.width >= 1024 ? 1 : meta.width >= 700 ? 2 : 4;
    const sliceH = cols === 1 ? Math.round(meta.width * 1.1) : 1500;
    const gap = 12;
    const header = 34;
    const slices = Math.ceil(meta.height / sliceH);
    const dir = path.join(RUN_DIR, 'tiles', record.name);
    mkdirSync(dir, { recursive: true });
    const out = [];
    for (let t = 0; t * cols < slices; t += 1) {
        const parts = [];
        let maxH = 0;
        for (let c = 0; c < cols; c += 1) {
            const i = t * cols + c;
            if (i >= slices) break;
            const top = i * sliceH;
            const h = Math.min(sliceH, meta.height - top);
            maxH = Math.max(maxH, h);
            const slice = data.subarray(top * meta.width * meta.channels, (top + h) * meta.width * meta.channels);
            parts.push({ input: slice, raw: { width: meta.width, height: h, channels: meta.channels }, left: c * (meta.width + gap), top: header });
        }
        const width = parts.length * meta.width + (parts.length - 1) * gap;
        const y0 = t * cols * sliceH;
        const y1 = Math.min(meta.height, (t * cols + parts.length) * sliceH);
        const label = `${record.name} · y ${y0}–${y1} of ${meta.height} px · tile ${t + 1}/${Math.ceil(slices / cols)}${cols > 1 ? ' · columns read top to bottom, left to right' : ''}`;
        parts.unshift({ input: Buffer.from(`<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${header}"><rect width="100%" height="100%" fill="#0B0B0C"/><text x="10" y="23" font-family="Helvetica, Arial, sans-serif" font-size="17" fill="#F2F0EA">${esc(label)}</text></svg>`), left: 0, top: 0 });
        const target = path.join(dir, `${String(t + 1).padStart(2, '0')}.jpg`);
        await sharp({ create: { width, height: header + maxH, channels: 3, background: '#8A8A8A' } }).composite(parts).jpeg({ quality: 82 }).toFile(target);
        out.push(target);
    }
    return out;
}

// ------------------------------------------------------------------------------------ sections
async function visualSection() {
    if (!visual.length) return '<section id="visual"><h2>Full-page screenshots</h2><p class="none">No visual captures in this run (npm run qa:d2:visual).</p></section>';
    const byPage = new Map();
    for (const r of visual) {
        if (!byPage.has(r.page)) byPage.set(r.page, []);
        byPage.get(r.page).push(r);
    }
    const parts = [];
    for (const [pageKey, records] of byPage) {
        const label = PAGES.find((p) => p.key === pageKey)?.label ?? pageKey;
        const rows = [];
        const groups = new Map();
        for (const r of records) {
            const key = `${r.width}|${r.variant}`;
            if (!groups.has(key)) groups.set(key, []);
            groups.get(key).push(r);
        }
        for (const [key, list] of groups) {
            const [width, variant] = key.split('|');
            const cards = [];
            for (const r of list) {
                const file = path.join(RUN_DIR, r.file);
                const cmp = comparisonFor('visual', file);
                const ch = await change(file, cmp);
                const state = stateOf(r);
                cards.push(`<article class="card s-${state}" id="${esc(r.name)}" data-state="${state}">
  <header><h4>${esc(r.locale.toUpperCase())} · ${esc(width)} px${variant !== 'default' ? ` · ${esc(variant)}` : ''}</h4><span class="badge b-${state}">${esc(state)}</span></header>
  <p class="meta"><a href="${esc(r.url)}">${esc(r.url.replace(/^https?:\/\/[^/]+/, ''))}</a> · ${esc(r.size?.height ?? '?')} px tall · ${esc(Math.round((r.ms ?? 0) / 100) / 10)} s${r.fold && existsSync(path.join(RUN_DIR, r.fold)) ? ` · <a href="${esc(rel(path.join(RUN_DIR, r.fold)))}" target="_blank" rel="noopener">first screen</a>` : ''}${r.error ? ` · <span class="err">${esc(r.error)}</span>` : ''}</p>
  ${pair(file, cmp, ch, r.name)}
  ${checkList(r.checks)}
</article>`);
            }
            rows.push(`<div class="row"><h3>${esc(label)} · ${esc(width)} px${variant !== 'default' ? ` · ${esc(variant)}` : ''}</h3><div class="cards">${cards.join('\n')}</div></div>`);
        }
        parts.push(`<section class="page" id="page-${esc(pageKey)}"><h2>${esc(label)}</h2>${rows.join('\n')}</section>`);
    }
    return parts.join('\n');
}

function matrix() {
    if (!visual.length) return '';
    const variants = [...new Set(visual.map((r) => r.variant))].sort((a, b) => variantOrder.indexOf(a) - variantOrder.indexOf(b));
    const cols = [];
    for (const v of variants) for (const w of WIDTHS) for (const l of localeOrder) if (visual.some((r) => r.variant === v && r.width === w && r.locale === l)) cols.push({ v, w, l });
    const head = cols.map((c) => `<th>${c.w}<br>${c.l.toUpperCase()}${c.v !== 'default' ? `<br>${c.v}` : ''}</th>`).join('');
    const rows = pageOrder
        .filter((p) => visual.some((r) => r.page === p))
        .map((p) => {
            const cells = cols.map((c) => {
                const r = visual.find((x) => x.page === p && x.width === c.w && x.locale === c.l && x.variant === c.v);
                if (!r) return '<td class="m-none">·</td>';
                const state = stateOf(r);
                const fails = failingChecks(r.checks).map(([id]) => id);
                return `<td class="m-${state}"><a href="#${esc(r.name)}" title="${esc(fails.join(', ') || state)}">${fails.length || (state === 'error' ? '!' : '✓')}</a></td>`;
            });
            return `<tr><th>${esc(PAGES.find((x) => x.key === p)?.label ?? p)}</th>${cells.join('')}</tr>`;
        });
    return `<table class="matrix"><thead><tr><th>Page</th>${head}</tr></thead><tbody>${rows.join('')}</tbody></table><p class="hint">Number = failing checks in that capture (hover for their names; click to jump). ✓ = all pass.</p>`;
}

async function heroSection() {
    if (!hero.length) return '<section id="hero"><h2>Hero frames</h2><p class="none">No hero frames in this run (npm run qa:d2:hero).</p></section>';
    const cards = [];
    for (const r of hero) {
        const state = stateOf(r);
        const frames = (r.frames ?? [])
            .map((f) => {
                if (!f.file) return `<figure><figcaption>${esc(f.label ?? f.target)}</figcaption><p class="none">no frame</p></figure>`;
                const file = path.join(RUN_DIR, f.file);
                const cmp = comparisonFor('hero', file);
                return `<figure><figcaption>${esc(f.label ?? `${f.target} ms`)}</figcaption><a href="${esc(rel(file))}" target="_blank" rel="noopener"><img loading="lazy" src="${esc(rel(file))}" alt="${esc(f.label ?? '')}"></a>${cmp ? `<a class="cmp" href="${esc(rel(cmp.file))}" target="_blank" rel="noopener">${esc(cmp.label)}</a>` : ''}</figure>`;
            })
            .join('');
        cards.push(`<article class="card hero s-${state}" id="${esc(r.name)}" data-state="${state}">
  <header><h4>${esc(r.locale.toUpperCase())} · ${esc(r.width)} px · ${esc(r.motion === 'reduce' ? 'reduced motion' : 'motion')}</h4><span class="badge b-${state}">${esc(state)}</span></header>
  <p class="meta">clock: ${esc(r.clock ?? '?')} · first paint ${esc(r.firstPaint ?? '?')} ms · ${esc(r.screencastFrames ?? 0)} screencast frames${r.error ? ` · <span class="err">${esc(r.error)}</span>` : ''}</p>
  <div class="frames">${frames}</div>
  ${checkList(r.checks)}
</article>`);
    }
    return `<section id="hero"><h2>Hero frames (0 / 600 / 1200 / 2000 / 3000 ms)</h2><p class="hint">Each frame is the screen at that time (DevTools screencast, not timed screenshots). Filmstrips: <code>hero/hero-&lt;locale&gt;-&lt;width&gt;-&lt;motion&gt;-strip.png</code>.</p><div class="cards hero-cards">${cards.join('\n')}</div></section>`;
}

function d1Section() {
    if (!d1) return '<section id="d1"><h2>Design 1 visual proof</h2><p class="none">Not run (npm run qa:d2:d1-visual).</p></section>';
    const diffs = d1.rows.filter((r) => !r.same);
    const fig = (file, label) => (file ? `<figure><figcaption>${esc(label)}</figcaption><div class="pane" data-sync><a href="${esc(rel(path.join(RUN_DIR, file)))}" target="_blank" rel="noopener"><img loading="lazy" src="${esc(rel(path.join(RUN_DIR, file)))}" alt="${esc(label)}"></a></div></figure>` : '');
    const diffCards = diffs.map(
        (r) => `<article class="card s-fail"><header><h4>${esc(r.path)} · ${esc(r.width)} px</h4><span class="badge b-fail">${r.error ? 'error' : `${esc(r.different)} px`}</span></header>
  ${r.error ? `<p class="err">${esc(r.error)}</p>` : `<div class="pair three">${fig(r.files?.baseline, 'baseline (design-1 tag)')}${fig(r.files?.branch, 'branch')}${fig(r.files?.diff, 'diff (magenta)')}</div>`}</article>`,
    );
    const thumbs = d1.rows
        .filter((r) => r.thumb)
        .map((r) => `<figure class="thumb ${r.same ? '' : 'bad'}"><a href="${esc(rel(path.join(RUN_DIR, r.thumb)))}" target="_blank" rel="noopener"><img loading="lazy" src="${esc(rel(path.join(RUN_DIR, r.thumb)))}" alt="${esc(r.path)}"></a><figcaption>${esc(r.path)} · ${esc(r.width)}${r.same ? ' · identical' : ' · DIFF'}</figcaption></figure>`);
    return `<section id="d1"><h2>Design 1 visual proof</h2>
<p class="${diffs.length ? 'err' : 'okline'}">${esc(d1.comparisons)} comparisons (${esc(d1.pages)} pages × ${esc(d1.widths.join(' / '))} px): ${diffs.length ? `${diffs.length} different` : 'every page pixel-identical to the design-1 baseline'} · baseline ${esc(d1.baseline)} · branch ${esc(d1.branch)} · ${esc(d1.generatedAt)}</p>
${diffCards.join('\n')}
<details><summary>All ${esc(d1.rows.length)} branch captures (thumbnails)</summary><div class="thumbs">${thumbs.join('')}</div></details></section>`;
}

function lighthouseSection() {
    if (!lighthouse) return '<section id="lighthouse"><h2>Lighthouse (production build)</h2><p class="none">Not run (npm run qa:d2:lighthouse).</p></section>';
    const kb = (b) => (b === null || b === undefined ? 'n/a' : `${(b / 1024).toFixed(1)} KB`);
    const ms = (v) => (v === null || v === undefined ? 'n/a' : v >= 1000 ? `${(v / 1000).toFixed(2)} s` : `${Math.round(v)} ms`);
    const rows = lighthouse.rows.map((r) => {
        const s = (id, min) => {
            const v = id === 'seo' && r.noindex ? r.seoAdjusted : r.scores[id];
            const raw = id === 'seo' && r.noindex ? `${r.scores.seo}→${r.seoAdjusted}*` : r.scores[id];
            return `<td class="${v !== null && v >= min ? 'm-pass' : 'm-fail'}">${esc(raw ?? 'n/a')}</td>`;
        };
        return `<tr><th><a href="${esc(rel(path.join(QA_DIR, lighthouse.reports, `${r.page}.report.html`)))}" target="_blank" rel="noopener">${esc(r.page)}</a></th>${s('performance', 90)}${s('accessibility', 100)}${s('best-practices', 95)}${s('seo', 100)}<td>${esc(ms(r.lcpMs))}</td><td>${esc(r.cls?.toFixed(3) ?? 'n/a')}</td><td>${esc(ms(r.tbtMs))}</td><td>${esc(kb(r.firstPartyJsBytes))}</td><td>${r.page.startsWith('home-') ? esc(kb(r.heroJsBytes)) : '–'}</td><td>${esc(r.fontPreloads)}</td><td>${esc(r.lcpElement ? (r.lcpElement.isH1 ? 'h1' : r.lcpElement.selector ?? '') : 'n/a')}</td><td class="gates">${r.gateFailures?.length ? r.gateFailures.map((g) => `<div>${esc(g)}</div>`).join('') : '<span class="okline">pass</span>'}</td></tr>`;
    });
    return `<section id="lighthouse"><h2>Lighthouse (mobile, production build)</h2>
<p class="meta">${esc(lighthouse.generatedAt)} · ${esc(lighthouse.server)} (${esc(lighthouse.build?.dir)}) · gates: performance ≥ 90, accessibility 100, best practices ≥ 95, SEO 100 (* noindex by the app: without is-crawlable), LCP ≤ 2.0 s, CLS ≤ 0.02, home JS ≤ 90 KB, hero JS ≤ 25 KB gzip, ≤ 2 font preloads</p>
<table class="lh"><thead><tr><th>Page</th><th>Perf</th><th>A11y</th><th>BP</th><th>SEO</th><th>LCP</th><th>CLS</th><th>TBT</th><th>JS</th><th>Hero JS</th><th>Fonts</th><th>LCP el.</th><th>Gates</th></tr></thead><tbody>${rows.join('')}</tbody></table></section>`;
}

// ------------------------------------------------------------------------------------ findings-auto.md
function findingsTable() {
    const rows = [];
    const cell = (s) => String(s).replace(/\|/g, '\\|').replace(/\s+/g, ' ').trim();
    for (const r of visual) {
        for (const [id, c] of [...failingChecks(r.checks), ...warningChecks(r.checks)]) {
            rows.push([PAGES.find((p) => p.key === r.page)?.label ?? r.page, `${r.width}${r.variant !== 'default' ? ` ${r.variant}` : ''}`, r.locale.toUpperCase(), `${id}: ${brief(id, c)}`, c.warn ? 'low' : SEVERITY[id] ?? 'medium', 'open']);
        }
        if (r.error) rows.push([r.page, r.width, r.locale.toUpperCase(), `capture error: ${r.error}`, 'high', 'open']);
    }
    for (const r of hero) {
        for (const [id, c] of [...failingChecks(r.checks), ...warningChecks(r.checks)]) {
            rows.push(['Hero', `${r.width} ${r.motion}`, r.locale.toUpperCase(), `${id}: ${brief(id, c)}`, c.warn ? 'low' : SEVERITY[id] ?? 'medium', 'open']);
        }
    }
    for (const r of d1?.rows ?? []) if (!r.same) rows.push(['Design 1 ' + r.path, r.width, '–', r.error ? `capture error: ${r.error}` : `${r.different} pixels differ from the design-1 baseline`, 'critical', 'open']);
    for (const r of lighthouse?.rows ?? []) for (const g of r.gateFailures ?? []) rows.push([`Lighthouse ${r.page}`, 'mobile', r.page.split('-').pop().toUpperCase(), g, /accessibility|seo/.test(g) ? 'high' : 'medium', 'open']);
    const order = { critical: 0, high: 1, medium: 2, low: 3 };
    rows.sort((a, b) => order[a[4]] - order[b[4]]);
    return [
        `# Automated findings: run ${current}`,
        '',
        `Generated by scripts/qa/d2-report.mjs on ${new Date().toISOString()} from qa/shots/${current}/. Copy the rows you confirm into qa/findings.md.`,
        '',
        '| Page | Width | Language | Finding | Severity | Status |',
        '|---|---|---|---|---|---|',
        ...rows.map((r) => `| ${r.map(cell).join(' | ')} |`),
        '',
    ].join('\n');
}

// ------------------------------------------------------------------------------------ page
const CSS = `
:root{--canvas:#F7F6F3;--surface:#FFFFFF;--ink:#0B0B0C;--ink2:#3A3A3F;--ink3:#66666C;--line:#E4E1DA;--pass:#1E7A4F;--fail:#B42318;--warn:#9A5B00;--info:#2E5AAC;color-scheme:light}
*{box-sizing:border-box}body{margin:0;background:var(--canvas);color:var(--ink);font:15px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Helvetica,Arial,sans-serif}
header.top{position:sticky;top:0;z-index:5;background:var(--ink);color:#F2F0EA;padding:12px 24px;display:flex;gap:24px;align-items:center;flex-wrap:wrap}
header.top h1{font-size:18px;margin:0}header.top nav a{color:#E2CF9F;margin-inline-end:14px;text-decoration:none}header.top label{margin-inline-start:auto}
main{padding:24px;max-width:2400px;margin:0 auto}h2{font-size:24px;margin:40px 0 12px}h3{font-size:17px;margin:24px 0 8px;color:var(--ink2)}h4{margin:0;font-size:15px}
.summary{display:flex;gap:16px;flex-wrap:wrap}.stat{background:var(--surface);border:1px solid var(--line);border-radius:10px;padding:12px 16px;min-width:180px}.stat b{display:block;font-size:22px}
.matrix,.lh{border-collapse:collapse;background:var(--surface);font-size:13px}.matrix th,.matrix td,.lh th,.lh td{border:1px solid var(--line);padding:4px 7px;text-align:center}.matrix th:first-child,.lh th:first-child{text-align:start}
.m-pass{background:#E6F2EC}.m-fail{background:#F9E3E1}.m-warn{background:#FBEEDD}.m-error{background:#F2C9C4}.m-none{color:#bbb}.matrix a{color:inherit;text-decoration:none;display:block}
.cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.hero-cards{grid-template-columns:1fr}
.card{background:var(--surface);border:1px solid var(--line);border-radius:14px;padding:14px;min-width:0}.card header{display:flex;justify-content:space-between;align-items:center;gap:8px}
.s-fail,.s-error{border-color:#E8B4AE}.badge{font-size:12px;border-radius:999px;padding:2px 10px;color:#fff}.b-pass{background:var(--pass)}.b-fail,.b-error{background:var(--fail)}.b-warn{background:var(--warn)}
.meta{color:var(--ink3);font-size:13px;margin:6px 0 10px;overflow-wrap:anywhere}.meta a{color:var(--ink3)}
.pair{display:grid;grid-template-columns:1fr 1fr;gap:10px}.pair.three{grid-template-columns:1fr 1fr 1fr}figure{margin:0;min-width:0}figcaption{font-size:12px;color:var(--ink3);margin-bottom:4px}
.pane{height:560px;overflow:auto;border:1px solid var(--line);border-radius:8px;background:#ddd}.pane img{display:block;width:100%;height:auto}
.frames{display:grid;grid-template-columns:repeat(5,1fr);gap:8px}.frames img{width:100%;border:1px solid var(--line);border-radius:6px;display:block}.frames .cmp{font-size:12px;color:var(--ink3)}
.checks{list-style:none;padding:0;margin:10px 0 0;font-size:13px;columns:2;column-gap:18px}.checks li{break-inside:avoid;padding:2px 0 2px 18px;position:relative;overflow-wrap:anywhere}
.checks li::before{position:absolute;inset-inline-start:0}.c-ok::before{content:"✓";color:var(--pass)}.c-fail{color:var(--fail)}.c-fail::before{content:"✗"}.c-warn{color:var(--warn)}.c-warn::before{content:"!"}.c-info{color:var(--info)}.c-info::before{content:"i"}
.same{color:var(--pass)}.changed{color:var(--warn)}.none{color:var(--ink3);font-style:italic;padding:12px}.err{color:var(--fail)}.okline{color:var(--pass)}.hint{color:var(--ink3);font-size:13px}
.thumbs{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;margin-top:10px}.thumb img{width:100%;height:180px;object-fit:cover;object-position:top;border:1px solid var(--line);border-radius:6px}.thumb.bad img{outline:3px solid var(--fail)}.thumb figcaption{font-size:11px}
.lh td.gates{text-align:start;font-size:12px;color:var(--fail)}body.only-failing .card[data-state="pass"]{display:none}
@media (max-width:1700px){.checks{columns:1}}
@media (max-width:1300px){.cards{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:900px){.cards{grid-template-columns:1fr}.frames{grid-template-columns:repeat(3,1fr)}}`;

// Keeps the two panes of a pair at the same relative scroll position.
const JS = `
document.querySelectorAll('.pair').forEach(function(pair){var panes=pair.querySelectorAll('[data-sync]');var lock=false;panes.forEach(function(p){p.addEventListener('scroll',function(){if(lock)return;lock=true;var r=p.scrollTop/Math.max(1,p.scrollHeight-p.clientHeight);panes.forEach(function(o){if(o!==p)o.scrollTop=r*(o.scrollHeight-o.clientHeight);});requestAnimationFrame(function(){lock=false;});});});});
var box=document.getElementById('only-failing');if(box){box.addEventListener('change',function(){document.body.classList.toggle('only-failing',box.checked);});}`;

async function main() {
    const visualHtml = await visualSection();
    const heroHtml = await heroSection();
    const count = (list) => ({ total: list.length, pass: list.filter((r) => stateOf(r) === 'pass' || stateOf(r) === 'warn').length });
    const v = count(visual);
    const h = count(hero);
    const html = `<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Design 2 QA report ${esc(current)}</title><style>${CSS}</style></head>
<body><header class="top"><h1>Design 2 QA · ${esc(current)}</h1><nav><a href="#summary">Summary</a><a href="#visual">Pages</a><a href="#hero">Hero</a><a href="#d1">Design 1</a><a href="#lighthouse">Lighthouse</a></nav><label><input type="checkbox" id="only-failing"> only failing captures</label></header>
<main>
<section id="summary"><h2>Summary</h2>
<p class="meta">Site ${esc(runInfo.baseUrl ?? '?')} · ${runInfo.devServer ? 'Vite dev server (unbundled assets: timings indicative only)' : 'built assets'} · commit ${esc(runInfo.commit ?? '?')} · started ${esc(runInfo.startedAt ?? '?')} · report ${esc(new Date().toISOString())}${earlier.length ? ` · earlier runs: ${esc(earlier.slice(0, 5).join(', '))}` : ''}</p>
<div class="summary">
<div class="stat"><b>${v.pass} / ${v.total}</b>page captures pass</div>
<div class="stat"><b>${h.pass} / ${h.total}</b>hero captures pass</div>
<div class="stat"><b>${d1 ? (d1.different ? `${d1.different} diff` : '0 diff') : 'n/a'}</b>Design 1 pixel proof</div>
<div class="stat"><b>${lighthouse ? (lighthouse.pass ? 'pass' : `${lighthouse.rows.filter((r) => r.gateFailures?.length).length} fail`) : 'n/a'}</b>Lighthouse gates</div>
${verdict ? `<div class="stat"><b>${esc(Object.keys(verdict.byCheck ?? {}).length)}</b>failing check types: ${esc(Object.entries(verdict.byCheck ?? {}).map(([k, n]) => `${k} ×${n}`).join(', '))}</div>` : ''}
</div>
${matrix()}
<p class="hint">Comparison images: qa/reference/&lt;same file name&gt; when present, else the previous capture. Findings table: <code>qa/shots/${esc(current)}/findings-auto.md</code> → confirm into <code>qa/findings.md</code>. In a full-page capture, fixed bars (the mobile tab bar, a sticky CTA) appear once, at their place on the first screen: see each card's “first screen” shot for the real look.</p>
</section>
<section id="visual"><h2>Full-page screenshots (page · width · language)</h2></section>
${visualHtml}
${heroHtml}
${d1Section()}
${lighthouseSection()}
</main><script>${JS}</script></body></html>`;
    mkdirSync(path.dirname(OUT), { recursive: true });
    writeFileSync(OUT, html);
    writeFileSync(path.join(RUN_DIR, 'findings-auto.md'), findingsTable());
    let tileCount = 0;
    if (args.tiles) {
        const list = args.tiles === 'failing' ? visual.filter((r) => stateOf(r) !== 'pass') : visual;
        const index = [];
        for (const r of list) {
            const files = await tiles(r);
            tileCount += files.length;
            index.push(`${r.name}: ${files.map((f) => path.relative(process.cwd(), f)).join(' ')}`);
        }
        writeFileSync(path.join(RUN_DIR, 'tiles', 'index.txt'), `${index.join('\n')}\n`);
    }
    console.log(`Report: ${path.relative(process.cwd(), OUT)} (run ${current}: ${visual.length} page captures, ${hero.length} hero captures${d1 ? `, Design 1 proof ${d1.comparisons} comparisons` : ''}${lighthouse ? ', Lighthouse' : ''})`);
    console.log(`Findings: ${path.relative(process.cwd(), path.join(RUN_DIR, 'findings-auto.md'))}${args.tiles ? ` · ${tileCount} tiles in ${path.relative(process.cwd(), path.join(RUN_DIR, 'tiles'))}/ (index.txt)` : ''}`);
}

main().catch((error) => {
    console.error(`d2-report could not run: ${error?.stack || error}`);
    process.exit(2);
});
