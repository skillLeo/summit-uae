#!/usr/bin/env node
// Raster brand assets and team portraits, built with sharp (librsvg renders the traced SVGs).
//
//   node scripts/images/build.mjs              everything
//   node scripts/images/build.mjs icons og     only some groups (icons | og | team)
//
// icons  public/favicon.ico (16/32/48), public/icon.svg, public/apple-touch-icon.png,
//        public/icons/icon-192.png, icon-512.png, maskable-512.png
// og     public/images/og-default.png (1200 x 630)
// team   public/images/team/<slug>-<width>.{avif,webp,jpg}
//
// Inputs: resources/brand/{emblem,lockup}.svg (run scripts/brand/trace.sh first) and
// package/portraits/*.png. A size report is written to storage/app/qa/images/build-report.json.
import fs from 'node:fs';
import path from 'node:path';
import sharp from 'sharp';
import { optimize } from 'svgo';
import svgoConfig from '../brand/svgo.config.mjs';

const ROOT = path.resolve(import.meta.dirname, '../..');
const BRAND = path.join(ROOT, 'resources/brand');
const PUBLIC = path.join(ROOT, 'public');
const QA = path.join(ROOT, 'storage/app/qa/images');
const GOLD = '#C9A96B'; // design token (master PNG measures #C8A96A, see docs/brand-assets.md)
const BLACK = '#000000';

const groups = process.argv.slice(2);
const want = (g) => groups.length === 0 || groups.includes(g);
const report = [];
const rel = (p) => path.relative(ROOT, p);

function record(file, extra = {}) {
  const entry = { file: rel(file), bytes: fs.statSync(file).size, ...extra };
  report.push(entry);
  const dims = extra.width ? ` ${extra.width}x${extra.height}` : '';
  console.log(`${entry.file.padEnd(46)}${dims.padEnd(11)} ${String(entry.bytes).padStart(7)} B`);
}

function readMark(name) {
  const text = fs.readFileSync(path.join(BRAND, `${name}.svg`), 'utf8');
  const [, vw, vh] = text.match(/viewBox="0 0 (\d+) (\d+)"/).map(Number);
  const paths = [...text.matchAll(/<path\b[^>]*\bd="([^"]+)"/g)].map((m) => m[1]);
  if (paths.length !== 1) throw new Error(`${name}.svg: expected one <path>, found ${paths.length}`);
  return { vw, vh, d: paths[0] };
}

// A W x H canvas filled with `bg`, the mark `markHeight` px tall, centred, in `fg`.
function placed(mark, W, H, markHeight, fg, bg) {
  const s = markHeight / mark.vh;
  const tx = (W - mark.vw * s) / 2;
  const ty = (H - markHeight) / 2;
  return `<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}" viewBox="0 0 ${W} ${H}">`
    + `<rect width="${W}" height="${H}" fill="${bg}"/>`
    + `<path fill="${fg}" transform="translate(${tx} ${ty}) scale(${s})" d="${mark.d}"/></svg>`;
}

// Rasterise a placed mark: render at K x (up to ~4096 px) and downscale in floating-point linear
// light (scRGB), so thin gold lines on black keep their true brightness at small sizes. Solid
// areas stay exactly GOLD and BLACK; only anti-aliased edge pixels differ from a direct render.
function raster(mark, W, H, markHeight, fg, bg) {
  const K = Math.max(1, Math.min(16, Math.floor(4096 / Math.max(W, H))));
  return sharp(Buffer.from(placed(mark, W * K, H * K, markHeight * K, fg, bg)))
    .pipelineColourspace('scrgb').resize(W, H, { kernel: 'cubic' }).toColourspace('srgb');
}
const png = (pipeline) => pipeline.png({ compressionLevel: 9, palette: true, quality: 100, dither: 0 });

// ICO container with PNG-compressed entries (supported by every current browser and Windows).
function ico(pngs) {
  const header = Buffer.alloc(6 + 16 * pngs.length);
  header.writeUInt16LE(0, 0);
  header.writeUInt16LE(1, 2);
  header.writeUInt16LE(pngs.length, 4);
  let offset = header.length;
  pngs.forEach(({ size, data }, i) => {
    const e = 6 + 16 * i;
    header.writeUInt8(size >= 256 ? 0 : size, e);
    header.writeUInt8(size >= 256 ? 0 : size, e + 1);
    header.writeUInt8(0, e + 2); // palette size
    header.writeUInt8(0, e + 3);
    header.writeUInt16LE(1, e + 4); // colour planes
    header.writeUInt16LE(32, e + 6); // bits per pixel
    header.writeUInt32LE(data.length, e + 8);
    header.writeUInt32LE(offset, e + 12);
    offset += data.length;
  });
  return Buffer.concat([header, ...pngs.map((p) => p.data)]);
}

if (want('icons')) {
  const emblem = readMark('emblem');
  fs.mkdirSync(path.join(PUBLIC, 'icons'), { recursive: true });

  // Favicon: emblem 7/8 of the square (1 px clear at 16 px), gold on black.
  const FAVICON_RATIO = 7 / 8;
  const pngs = [];
  for (const size of [16, 32, 48]) {
    const pixels = await raster(emblem, size, size, size * FAVICON_RATIO, GOLD, BLACK).png().toBuffer();
    // 32-bit RGBA PNG entries: the form Windows' ICO decoder expects
    pngs.push({ size, data: await sharp(pixels).ensureAlpha().png({ compressionLevel: 9 }).toBuffer() });
  }
  const icoFile = path.join(PUBLIC, 'favicon.ico');
  fs.writeFileSync(icoFile, ico(pngs));
  record(icoFile, { sizes: '16,32,48' });

  // icon.svg: same composition, in emblem units so every coordinate stays an integer.
  const side = Math.round(emblem.vh / FAVICON_RATIO);
  const ox = Math.round((side - emblem.vw) / 2), oy = Math.round((side - emblem.vh) / 2);
  const iconSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${side} ${side}">`
    + `<rect width="${side}" height="${side}" fill="${BLACK}"/>`
    + `<path fill="${GOLD}" transform="translate(${ox} ${oy})" d="${emblem.d}"/></svg>`;
  const iconFile = path.join(PUBLIC, 'icon.svg');
  fs.writeFileSync(iconFile, optimize(iconSvg, svgoConfig).data);
  record(iconFile, { viewBox: `0 0 ${side} ${side}` });

  // Touch / PWA icons. Ratios are emblem height / icon size.
  //   apple-touch 0.70 and "any" 0.70: clear of iOS / launcher corner rounding.
  //   maskable: the farthest emblem point (pommel top, = half its height) sits at 90 % of the
  //   radius of the 80 % safe circle: (0.4 * 0.9 * 2) = 0.72 of the size.
  const icons = [
    ['apple-touch-icon.png', 180, 0.70],
    ['icons/icon-192.png', 192, 0.70],
    ['icons/icon-512.png', 512, 0.70],
    ['icons/maskable-512.png', 512, 0.72],
  ];
  for (const [name, size, ratio] of icons) {
    const file = path.join(PUBLIC, name);
    await png(raster(emblem, size, size, Math.round(size * ratio), GOLD, BLACK)).toFile(file);
    record(file, { width: size, height: size, emblemHeight: Math.round(size * ratio) });
  }
}

if (want('og')) {
  // Social preview: black, gold lockup centred at a modest size (~53 % of the width).
  const lockup = readMark('lockup');
  const W = 1200, H = 630, LOCKUP_WIDTH = 640;
  const markHeight = (LOCKUP_WIDTH * lockup.vh) / lockup.vw;
  fs.mkdirSync(path.join(PUBLIC, 'images'), { recursive: true });
  const file = path.join(PUBLIC, 'images/og-default.png');
  await png(raster(lockup, W, H, markHeight, GOLD, BLACK)).toFile(file);
  record(file, { width: W, height: H, lockupWidth: LOCKUP_WIDTH, lockupHeight: +markHeight.toFixed(1) });
}

if (want('team')) {
  // 4:5 crops centred on the face axis (midpoint of the pupils), the top of the head 10 % of the
  // crop height below the top edge for both people. Widths are the largest 4:5 crop that is
  // still centred on that axis, which also gives the two heads matching scale. Pixels are only
  // cropped and downscaled (Lanczos 3); no colour, retouching or upscaling.
  const HEADROOM = 0.10;
  const people = [
    { slug: 'ahmed-mohammadi', faceAxis: 477, headTop: 208, cropWidth: 952 },
    { slug: 'dr-mohamed-raouf', faceAxis: 461, headTop: 220, cropWidth: 852 },
  ];
  const WIDTHS = [480, 800, 1200];
  // Tuned so the three formats look alike (SSIM within ~0.002 of each other at 800w) and stay
  // well under ~120 KB at 800w. The brief's starting points (AVIF 55 / WebP 78 / JPEG 82) left
  // WebP visibly softer than JPEG (skin, beard); see docs/brand-assets.md.
  const QUALITY = {
    avif: { quality: 60, effort: 9, chromaSubsampling: '4:4:4' },
    webp: { quality: 86, effort: 6, smartSubsample: true },
    jpg: { quality: 86, mozjpeg: true, progressive: true },
  };
  const dir = path.join(PUBLIC, 'images/team');
  fs.mkdirSync(dir, { recursive: true });
  for (const p of people) {
    const src = path.join(ROOT, `package/portraits/${p.slug}.png`);
    const meta = await sharp(src).metadata();
    const w = p.cropWidth, h = (w * 5) / 4;
    const left = Math.round(p.faceAxis - w / 2), top = Math.round(p.headTop - HEADROOM * h);
    if (!Number.isInteger(h) || left < 0 || top < 0 || left + w > meta.width || top + h > meta.height) {
      throw new Error(`${p.slug}: crop ${w}x${h}+${left}+${top} does not fit ${meta.width}x${meta.height}`);
    }
    // never upscale: keep the widths the crop can supply, and the crop's own width as the top size
    const widths = WIDTHS.filter((x) => x <= w);
    if (widths.length < WIDTHS.length) widths.push(w);
    const crop = await sharp(src).extract({ left, top, width: w, height: h }).toBuffer();
    for (const width of widths) {
      const height = Math.round((width * 5) / 4);
      const resized = width === w ? sharp(crop) : sharp(crop).resize(width, height, { kernel: 'lanczos3' });
      for (const [ext, opts] of Object.entries(QUALITY)) {
        const file = path.join(dir, `${p.slug}-${width}.${ext}`);
        const pipeline = resized.clone();
        if (ext === 'avif') pipeline.avif(opts);
        else if (ext === 'webp') pipeline.webp(opts);
        else pipeline.jpeg(opts);
        await pipeline.toFile(file);
        record(file, { width, height, crop: `${w}x${h}+${left}+${top}`, source: `${meta.width}x${meta.height}` });
      }
    }
  }
}

fs.mkdirSync(QA, { recursive: true });
let previous = [];
try { previous = JSON.parse(fs.readFileSync(path.join(QA, 'build-report.json'), 'utf8')).files ?? []; } catch { /* first run */ }
const merged = [...previous.filter((f) => !report.some((r) => r.file === f.file)), ...report].sort((a, b) => a.file.localeCompare(b.file));
fs.writeFileSync(path.join(QA, 'build-report.json'), `${JSON.stringify({ generated: new Date().toISOString(), files: merged }, null, 2)}\n`);
