#!/usr/bin/env node
/**
 * Design 2.1 header lockup (brief §3.8, fix D13): the client's official lockup, shield plus "SUMMIT LEGAL
 * CONSULTANCY", exported for the header from the gold-on-transparent master
 * package/brand/logo-04-gold-on-light.png ("Summit Legal Consultancy Logo-04.png").
 *
 *   node scripts/d2/brand-lockup.mjs            # write public/images/d2/brand/* (deterministic)
 *   node scripts/d2/brand-lockup.mjs --check    # exit 1 when a file is missing or differs from a fresh export
 *
 * What it does, and only this (never redraw, recolour or rearrange the logo):
 *   1. checks the master is the gold lockup on transparency: RGBA, transparent corners, and every pixel that
 *      is not fully transparent is the brand gold or, on some anti-aliased edge pixels of the master, the gold
 *      tinted towards white (the master's own edge rendering, kept exactly as it is);
 *   2. trims the transparent padding: the crop is the bounding box of every pixel with alpha > 0, so no edge
 *      pixel is lost and no empty row or column is kept;
 *   3. scales the crop (Mitchell filter, alpha premultiplied, so the edges never pick up the white of the
 *      transparent pixels) to 56, 112 and 168 px tall: @1x, @2x and @3x of the 56 px header lockup
 *      (docs/d2.1/phase-1/tokens.md: --lockup-h). Widths follow the master's own aspect ratio. Mitchell, not
 *      Lanczos 3: Lanczos rings at the hairline serifs and lightens the logo's average colour by 4.6 levels
 *      at 56 px; Mitchell keeps it within 0.5 of the master's (measured, checked below);
 *   4. writes lossless WebP and PNG (RGBA, no palette: the colour stays exactly the master's) plus
 *      manifest.json (source hash, trim box, every file's size, bytes and SHA-256) next to them.
 *
 * Used by <x-d2.shell.header> (resources/views/components/d2/shell/header.blade.php), which renders a
 * <picture> with the WebP srcset and the PNG fallback, explicit width and height (no layout shift), and the
 * approved logo alt text as the brand link's name. A vector lockup from the client replaces these files.
 */
import { createHash } from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import sharp from 'sharp';

const ROOT = path.resolve(import.meta.dirname, '../..');
const SOURCE = path.join(ROOT, 'package/brand/logo-04-gold-on-light.png');
const OUT = path.join(ROOT, 'public/images/d2/brand');
const NAME = 'lockup';
const HEIGHTS = { '1x': 56, '2x': 112, '3x': 168 };
const GOLD = { r: 200, g: 169, b: 106 }; // the master's one fully covered colour (#C8A96A, docs/brand-assets.md)
const TOLERANCE = 2; // per channel (8-bit rounding of the master)

/**
 * How far a pixel's colour is from the gold-to-white line (the brand gold, or the gold tinted towards white
 * that some of the master's anti-aliased edge pixels carry), and the share of gold in it (1 = the gold).
 */
function goldLine(r, g, b) {
    const t = (255 - r) / (255 - GOLD.r);
    const off = Math.max(Math.abs(255 - t * (255 - GOLD.g) - g), Math.abs(255 - t * (255 - GOLD.b) - b));
    return { t, off };
}

const sha256 = (buffer) => createHash('sha256').update(buffer).digest('hex');
const rel = (file) => path.relative(ROOT, file).split(path.sep).join('/');
const fail = (message) => {
    console.error(`brand-lockup: ${message}`);
    process.exit(1);
};

/** Reads the master and returns its raw RGBA pixels after checking it is the gold lockup on transparency. */
async function master() {
    if (!fs.existsSync(SOURCE)) fail(`missing ${rel(SOURCE)}`);
    const bytes = fs.readFileSync(SOURCE);
    const meta = await sharp(bytes).metadata();
    if (meta.channels !== 4 || !meta.hasAlpha) fail(`${rel(SOURCE)} has no alpha channel (expected the gold lockup on transparency)`);
    const { data, info } = await sharp(bytes).ensureAlpha().raw().toBuffer({ resolveWithObject: true });
    const { width, height } = info;
    const at = (x, y) => (y * width + x) * 4;
    for (const [x, y] of [[0, 0], [width - 1, 0], [0, height - 1], [width - 1, height - 1]]) {
        if (data[at(x, y) + 3] !== 0) fail(`corner (${x}, ${y}) is not transparent`);
    }

    let left = width;
    let top = height;
    let right = -1;
    let bottom = -1;
    let inked = 0;
    let solid = 0;
    let tinted = 0;
    for (let y = 0; y < height; y += 1) {
        for (let x = 0; x < width; x += 1) {
            const i = at(x, y);
            const alpha = data[i + 3];
            if (alpha === 0) continue;
            inked += 1;
            if (alpha === 255) solid += 1;
            const { t, off } = goldLine(data[i], data[i + 1], data[i + 2]);
            if (off > TOLERANCE || t <= 0 || t > 1.02) fail(`pixel (${x}, ${y}) is rgb(${data[i]} ${data[i + 1]} ${data[i + 2]}), not the brand gold: is this the gold lockup?`);
            if (t < 0.97) tinted += 1;
            if (x < left) left = x;
            if (x > right) right = x;
            if (y < top) top = y;
            if (y > bottom) bottom = y;
        }
    }
    if (right < 0) fail('the master is empty');
    return { bytes, width, height, trim: { left, top, width: right - left + 1, height: bottom - top + 1 }, inked, solid, tinted };
}

/** Every output file as { file, buffer, width, height, format, density }. */
async function render(src) {
    const crop = await sharp(src.bytes).extract(src.trim).toBuffer();
    const out = [];
    for (const [density, h] of Object.entries(HEIGHTS)) {
        const w = Math.round((src.trim.width * h) / src.trim.height);
        // fit "fill" with the rounded width keeps the master's aspect ratio to within half a pixel.
        const scaled = sharp(crop).resize({ width: w, height: h, fit: 'fill', kernel: 'mitchell' });
        const webp = await scaled.clone().webp({ lossless: true, effort: 6, exact: true }).toBuffer();
        const png = await scaled.clone().png({ compressionLevel: 9, adaptiveFiltering: true, palette: false }).toBuffer();
        out.push({ file: `${NAME}@${density}.webp`, buffer: webp, width: w, height: h, format: 'webp', density });
        out.push({ file: `${NAME}@${density}.png`, buffer: png, width: w, height: h, format: 'png', density });
    }
    return out;
}

/** Alpha-weighted mean colour of an RGBA buffer (what the logo looks like on average, edges included). */
function meanColour(data) {
    let r = 0;
    let g = 0;
    let b = 0;
    let w = 0;
    for (let i = 0; i < data.length; i += 4) {
        const a = data[i + 3];
        if (a === 0) continue;
        r += data[i] * a;
        g += data[i + 1] * a;
        b += data[i + 2] * a;
        w += a;
    }
    return [r / w, g / w, b / w];
}

/**
 * The colour check on an output: its alpha-weighted mean colour equals the master's (within 1.5 of 255 per
 * channel) and every fully covered pixel stays within 12 levels of the gold (at 56 px the strokes are a pixel
 * or two wide, so few pixels are fully covered and the filter moves those by a few levels). Scaling changes
 * coverage at the edges, never the logo's colour.
 */
async function verifyColour(buffer, file, reference) {
    const { data } = await sharp(buffer).ensureAlpha().raw().toBuffer({ resolveWithObject: true });
    const mean = meanColour(data);
    const drift = Math.max(...mean.map((c, i) => Math.abs(c - reference[i])));
    let solidWorst = 0;
    for (let i = 0; i < data.length; i += 4) {
        if (data[i + 3] !== 255) continue;
        solidWorst = Math.max(solidWorst, Math.abs(data[i] - GOLD.r), Math.abs(data[i + 1] - GOLD.g), Math.abs(data[i + 2] - GOLD.b));
    }
    if (drift > 1.5) fail(`${file}: the mean colour drifts ${drift.toFixed(2)} from the master's`);
    if (solidWorst > 12) fail(`${file}: a fully covered pixel is ${solidWorst} levels off the brand gold`);
    return Math.round(drift * 100) / 100;
}

const check = process.argv.includes('--check');
const src = await master();
const reference = meanColour((await sharp(src.bytes).extract(src.trim).ensureAlpha().raw().toBuffer()));
const files = await render(src);
for (const f of files) f.drift = await verifyColour(f.buffer, f.file, reference);

const manifest = {
    source: rel(SOURCE),
    source_sha256: sha256(src.bytes),
    source_size: [src.width, src.height],
    trim: src.trim,
    gold: `#${[GOLD.r, GOLD.g, GOLD.b].map((c) => c.toString(16).padStart(2, '0')).join('').toUpperCase()}`,
    aspect: Math.round((src.trim.width / src.trim.height) * 10000) / 10000,
    css_height: HEIGHTS['1x'],
    files: Object.fromEntries(files.map((f) => [f.file, { width: f.width, height: f.height, density: f.density, bytes: f.buffer.length, sha256: sha256(f.buffer) }])),
    generator: 'scripts/d2/brand-lockup.mjs',
};
const manifestJson = `${JSON.stringify(manifest, null, 4)}\n`;

if (check) {
    const stale = [];
    for (const f of files) {
        const target = path.join(OUT, f.file);
        if (!fs.existsSync(target) || !fs.readFileSync(target).equals(f.buffer)) stale.push(f.file);
    }
    const manifestFile = path.join(OUT, 'manifest.json');
    if (!fs.existsSync(manifestFile) || fs.readFileSync(manifestFile, 'utf8') !== manifestJson) stale.push('manifest.json');
    if (stale.length) fail(`stale: ${stale.join(', ')} (run node scripts/d2/brand-lockup.mjs)`);
    console.log(`brand-lockup: ${files.length} files and the manifest are up to date`);
    process.exit(0);
}

fs.mkdirSync(OUT, { recursive: true });
for (const f of files) fs.writeFileSync(path.join(OUT, f.file), f.buffer);
fs.writeFileSync(path.join(OUT, 'manifest.json'), manifestJson);
console.log(`brand-lockup: trimmed ${src.width}×${src.height} to ${src.trim.width}×${src.trim.height} at (${src.trim.left}, ${src.trim.top}); ${src.inked} inked pixels, ${src.solid} solid, ${src.tinted} tinted edge pixels`);
for (const f of files) console.log(`  ${rel(path.join(OUT, f.file))}  ${f.width}×${f.height}  ${f.buffer.length} B  mean colour drift ${f.drift}`);
