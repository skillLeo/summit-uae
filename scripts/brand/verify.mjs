#!/usr/bin/env node
// Fidelity check for the traced brand marks (run by scripts/brand/trace.sh, or on its own).
//
// Each SVG is rendered with librsvg (sharp) at the master's own pixel size, aligned to the
// master's pixel grid (positions from storage/app/qa/brand/trace-boxes.json), and compared with
// the master's gold coverage (alpha of package/brand/logo-04-gold-on-light.png):
//   iou      |A∩B| / |A∪B| of the 1-bit shapes (coverage >= 50 %)   <- the reported metric
//   dice     2|A∩B| / (|A|+|B|)
//   softIoU  Σmin / Σmax of the anti-aliased coverage (stricter: penalises sub-pixel drift)
// Also checks structure (one <path>, fill="currentColor", viewBox only), that nothing is drawn
// outside the viewBox, the 6 KB emblem budget, and writes renders at 64 px and 400 px height
// plus diff maps (red = master only, blue = trace only) to storage/app/qa/brand/.
import fs from 'node:fs';
import path from 'node:path';
import sharp from 'sharp';

const ROOT = path.resolve(import.meta.dirname, '../..');
const MASTER = path.join(ROOT, 'package/brand/logo-04-gold-on-light.png');
const BRAND = path.join(ROOT, 'resources/brand');
const QA = path.join(ROOT, 'storage/app/qa/brand');
const boxes = JSON.parse(fs.readFileSync(path.join(QA, 'trace-boxes.json'), 'utf8'));
const G = boxes.unitsPerMasterPx;
const GOLD = '#C9A96B';
const MIN_IOU = 99;
const EMBLEM_MAX_BYTES = 6 * 1024;

const masterMeta = await sharp(MASTER).metadata();
const masterAlpha = await sharp(MASTER).ensureAlpha().extractChannel(3).raw().toBuffer();

function alphaCrop(x, y, w, h) {
  const out = Buffer.alloc(w * h);
  for (let j = 0; j < h; j++) {
    const sy = y + j;
    if (sy < 0 || sy >= masterMeta.height) continue;
    for (let i = 0; i < w; i++) {
      const sx = x + i;
      if (sx >= 0 && sx < masterMeta.width) out[j * w + i] = masterAlpha[sy * masterMeta.width + sx];
    }
  }
  return out;
}

async function renderAlpha(svg, w, h) {
  const { data, info } = await sharp(Buffer.from(svg), { density: 72 })
    .ensureAlpha().extractChannel(3).raw().toBuffer({ resolveWithObject: true });
  if (info.width !== w || info.height !== h) throw new Error(`render ${info.width}x${info.height}, expected ${w}x${h}`);
  return data;
}

function parseSvg(text) {
  const root = text.match(/<svg\b([^>]*)>/);
  const vb = root?.[1].match(/viewBox="0 0 (\d+) (\d+)"/);
  return {
    attrs: root?.[1] ?? '',
    vw: vb ? +vb[1] : NaN,
    vh: vb ? +vb[2] : NaN,
    inner: text.slice(text.indexOf('>', text.indexOf('<svg')) + 1, text.lastIndexOf('</svg>')),
    paths: (text.match(/<path\b/g) ?? []).length,
  };
}

const results = [];
let failed = false;
for (const name of ['emblem', 'wordmark', 'lockup']) {
  const file = path.join(BRAND, `${name}.svg`);
  const text = fs.readFileSync(file, 'utf8');
  const svg = parseSvg(text);
  const box = boxes[name];
  const problems = [];
  if (svg.vw !== box.w || svg.vh !== box.h) problems.push(`viewBox ${svg.vw}x${svg.vh} != traced box ${box.w}x${box.h}`);
  if (svg.paths !== 1) problems.push(`${svg.paths} <path> elements (expected 1)`);
  if (!/fill="currentColor"/.test(text)) problems.push('fill="currentColor" missing');
  if (/\b(width|height)="/.test(svg.attrs)) problems.push('root has width/height');
  if (/#[0-9a-f]{3,6}\b/i.test(text)) problems.push('hard-coded colour');

  // Master-pixel region around the box (2 px margin), aligned to the master grid.
  const px0 = Math.floor(box.x / G) - 2, py0 = Math.floor(box.y / G) - 2;
  const px1 = Math.ceil((box.x + box.w) / G) + 2, py1 = Math.ceil((box.y + box.h) / G) + 2;
  const W = px1 - px0, H = py1 - py0;
  const place = (overflow) => `<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}" `
    + `viewBox="${px0 * G} ${py0 * G} ${W * G} ${H * G}"><svg x="${box.x}" y="${box.y}" width="${box.w}" `
    + `height="${box.h}" viewBox="0 0 ${box.w} ${box.h}" overflow="${overflow}" ${svg.attrs.replace(/xmlns="[^"]*"|viewBox="[^"]*"/g, '')}>`
    + `${svg.inner}</svg></svg>`;

  const A = alphaCrop(px0, py0, W, H);
  const B = await renderAlpha(place('hidden'), W, H);
  let inter = 0, union = 0, a = 0, b = 0, smin = 0, smax = 0;
  const diff = Buffer.alloc(W * H * 3, 255);
  for (let i = 0; i < W * H; i++) {
    const ia = A[i] >= 128, ib = B[i] >= 128;
    if (ia && ib) inter++;
    if (ia || ib) union++;
    if (ia) a++;
    if (ib) b++;
    smin += Math.min(A[i], B[i]);
    smax += Math.max(A[i], B[i]);
    if (ia && ib) diff.set([170, 170, 170], i * 3);
    else if (ia) diff.set([220, 0, 0], i * 3);
    else if (ib) diff.set([0, 120, 220], i * 3);
  }
  await sharp(diff, { raw: { width: W, height: H, channels: 3 } }).png().toFile(path.join(QA, `${name}-diff.png`));

  // Anything drawn outside the viewBox? Render without clipping at 3x and look past the box.
  const S = 3;
  const big = place('visible').replace(`width="${W}" height="${H}"`, `width="${W * S}" height="${H * S}"`);
  const C = await renderAlpha(big, W * S, H * S);
  const bx0 = (box.x / G - px0) * S, by0 = (box.y / G - py0) * S;
  const bx1 = ((box.x + box.w) / G - px0) * S, by1 = ((box.y + box.h) / G - py0) * S;
  let outside = 0, minX = Infinity, minY = Infinity, maxX = -1, maxY = -1;
  for (let y = 0; y < H * S; y++) for (let x = 0; x < W * S; x++) {
    const v = C[y * W * S + x];
    if (v < 8) continue;
    if (x + 1 < bx0 - 0.01 || x > bx1 + 0.01 || y + 1 < by0 - 0.01 || y > by1 + 0.01) outside++;
    minX = Math.min(minX, x); minY = Math.min(minY, y); maxX = Math.max(maxX, x); maxY = Math.max(maxY, y);
  }
  if (outside) problems.push(`${outside} px drawn outside the viewBox`);
  // slack between drawn pixels and the viewBox edge, in master px (one render px = 1/3 master px)
  const slack = [minX - bx0, minY - by0, bx1 - (maxX + 1), by1 - (maxY + 1)].map((v) => +(v / S).toFixed(2));
  if (slack.some((v) => v > 0.5)) problems.push(`viewBox not tight: slack ${slack.join(', ')} px`);

  const bytes = Buffer.byteLength(text);
  if (name === 'emblem' && bytes > EMBLEM_MAX_BYTES) problems.push(`${bytes} bytes > ${EMBLEM_MAX_BYTES}`);
  const iou = (inter / union) * 100;
  if (iou < MIN_IOU) problems.push(`IoU ${iou.toFixed(2)} % < ${MIN_IOU} %`);

  // Renders to look at: 64 px and 400 px tall, gold on black and black (currentColor) on white.
  for (const h of [64, 400]) {
    const w = Math.round((h * box.w) / box.h);
    for (const [variant, colour, bg] of [['on-black', GOLD, '#000000'], ['on-white', '#000000', '#ffffff']]) {
      const sized = text.replace('<svg ', `<svg width="${w}" height="${h}" color="${colour}" `);
      await sharp(Buffer.from(sized)).flatten({ background: bg }).png()
        .toFile(path.join(QA, `${name}-${h}px-${variant}.png`));
    }
  }

  const r = {
    file: `resources/brand/${name}.svg`, bytes, viewBox: `0 0 ${box.w} ${box.h}`,
    masterBoxPx: [box.x / G, box.y / G, box.w / G, box.h / G].map((v) => +v.toFixed(3)),
    iou: +iou.toFixed(3), dice: +((2 * inter) / (a + b) * 100).toFixed(3), softIoU: +((smin / smax) * 100).toFixed(3),
    masterOnlyPx: a - inter, traceOnlyPx: b - inter, masterPx: a, edgeSlackPx: slack, problems,
  };
  results.push(r);
  if (problems.length) failed = true;
}

fs.writeFileSync(path.join(QA, 'fidelity.json'), `${JSON.stringify({ generated: new Date().toISOString(), minIoU: MIN_IOU, results }, null, 2)}\n`);
for (const r of results) {
  console.log(`${r.file.padEnd(28)} ${String(r.bytes).padStart(6)} B  IoU ${r.iou.toFixed(2)} %  Dice ${r.dice.toFixed(2)} %  soft IoU ${r.softIoU.toFixed(2)} %  ${r.problems.length ? `PROBLEMS: ${r.problems.join('; ')}` : 'ok'}`);
}
if (failed) process.exit(1);
