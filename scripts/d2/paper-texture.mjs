#!/usr/bin/env node
/**
 * Design 2 paper texture: public/images/d2/paper-512.avif (upgrade spec §4.4, §12 B2).
 *
 * A seamless 512×512 grey tile of paper fibre and grain, very low contrast, multiplied into the sample
 * documents' paper colour (resources/css/d2/components/doc.css, .doc__sheet::before). Generated, not
 * scanned (no third-party image): a deterministic PRNG, so every run writes the same file.
 *
 *   node scripts/d2/paper-texture.mjs            write the tile (≤ 12 KB) and print its size and contrast
 *   node scripts/d2/paper-texture.mjs --preview  also write a contrast-stretched PNG for inspection to
 *                                                storage/app/d2/docs/paper-preview.png (gitignored)
 *
 * Seamless by construction: every sample wraps modulo 512 (fibres crossing an edge continue on the
 * opposite side) and the cloud layer uses whole-period sine waves.
 */
import sharp from 'sharp';
import { mkdirSync, statSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const OUT = join(ROOT, 'public/images/d2/paper-512.avif');
const PREVIEW = join(ROOT, 'storage/app/d2/docs/paper-preview.png');
const N = 512;
const MAX_BYTES = 12 * 1024;

// Levels out of 255. The tile is multiplied into the paper colour, so its mean sets how much the
// texture darkens the paper (~1 %); the grain is about ±1.5 % (spec "about 1.5 % noise").
const BASE = 252.5;
const GRAIN = 1.5; // standard deviation of the grain (levels)
const GRAIN_BLUR = 0.75; // px: softens the grain into paper tooth instead of digital static
const CLOUD = 1.4; // amplitude of the soft, large-scale unevenness
const FIBRES = 230; // number of fibres
const FIBRE_DARK = [2, 5.5]; // how much a fibre darkens the paper at its centre line
const FIBRE_LIGHT_SHARE = 0.25; // share of fibres that are lighter than the paper (glints)

function mulberry32(seed) {
    let a = seed >>> 0;
    return () => {
        a = (a + 0x6d2b79f5) >>> 0;
        let t = a;
        t = Math.imul(t ^ (t >>> 15), t | 1);
        t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

const rand = mulberry32(20261110);
const between = (lo, hi) => lo + (hi - lo) * rand();
const gauss = () => {
    // Box–Muller
    const u = Math.max(rand(), 1e-9);
    const v = rand();
    return Math.sqrt(-2 * Math.log(u)) * Math.cos(2 * Math.PI * v);
};
const wrap = (i) => ((i % N) + N) % N;

const field = new Float32Array(N * N).fill(BASE);

// 1. Cloud: a few whole-period waves (seamless), summed and normalised.
const waves = Array.from({ length: 7 }, () => ({
    fx: Math.floor(between(1, 5)),
    fy: Math.floor(between(1, 5)),
    phase: between(0, Math.PI * 2),
    weight: between(0.4, 1),
}));
const totalWeight = waves.reduce((sum, w) => sum + w.weight, 0);
for (let y = 0; y < N; y++) {
    for (let x = 0; x < N; x++) {
        let v = 0;
        for (const w of waves) {
            v += w.weight * Math.sin((2 * Math.PI * (w.fx * x + w.fy * y)) / N + w.phase);
        }
        field[y * N + x] += (CLOUD * v) / totalWeight;
    }
}

// 2. Fibres: thin quadratic curves, splatted with a soft 1 px kernel, wrapping at the edges.
function splat(cx, cy, amount, radius) {
    const r = Math.ceil(radius * 2);
    for (let dy = -r; dy <= r; dy++) {
        for (let dx = -r; dx <= r; dx++) {
            const px = Math.floor(cx) + dx;
            const py = Math.floor(cy) + dy;
            const d2 = (px + 0.5 - cx) ** 2 + (py + 0.5 - cy) ** 2;
            const w = Math.exp(-d2 / (2 * radius * radius));
            if (w < 0.02) continue;
            field[wrap(py) * N + wrap(px)] += amount * w;
        }
    }
}

for (let i = 0; i < FIBRES; i++) {
    const length = between(10, 70);
    const angle = between(0, Math.PI);
    const x0 = between(0, N);
    const y0 = between(0, N);
    const x2 = x0 + Math.cos(angle) * length;
    const y2 = y0 + Math.sin(angle) * length;
    const bend = between(-0.35, 0.35) * length;
    const x1 = (x0 + x2) / 2 - Math.sin(angle) * bend;
    const y1 = (y0 + y2) / 2 + Math.cos(angle) * bend;
    const light = rand() < FIBRE_LIGHT_SHARE;
    const strength = light ? between(1, 2.2) : -between(FIBRE_DARK[0], FIBRE_DARK[1]);
    const radius = between(0.45, 0.85);
    const steps = Math.ceil(length * 2.2);
    for (let s = 0; s <= steps; s++) {
        const t = s / steps;
        const taper = Math.sin(Math.PI * t) ** 0.6; // thin ends
        const x = (1 - t) ** 2 * x0 + 2 * (1 - t) * t * x1 + t * t * x2;
        const y = (1 - t) ** 2 * y0 + 2 * (1 - t) * t * y1 + t * t * y2;
        splat(x, y, (strength * taper) / 2.2, radius);
    }
}

// 3. Grain: white noise softened by a small Gaussian blur (wrapping, so still seamless). Real paper
// grain is not per-pixel static, and the softened grain survives AVIF encoding inside the budget.
const grain = new Float32Array(N * N);
for (let i = 0; i < grain.length; i++) {
    grain[i] = gauss();
}
const kernel = (() => {
    const sigma = GRAIN_BLUR;
    const r = Math.ceil(sigma * 3);
    const k = [];
    for (let i = -r; i <= r; i++) k.push(Math.exp(-(i * i) / (2 * sigma * sigma)));
    const total = k.reduce((a, b) => a + b, 0);
    return { r, k: k.map((v) => v / total) };
})();
const pass = (src, horizontal) => {
    const dst = new Float32Array(N * N);
    for (let y = 0; y < N; y++) {
        for (let x = 0; x < N; x++) {
            let v = 0;
            for (let i = -kernel.r; i <= kernel.r; i++) {
                const sx = horizontal ? wrap(x + i) : x;
                const sy = horizontal ? y : wrap(y + i);
                v += src[sy * N + sx] * kernel.k[i + kernel.r];
            }
            dst[y * N + x] = v;
        }
    }
    return dst;
};
const soft = pass(pass(grain, true), false);
let g2 = 0;
for (const v of soft) g2 += v * v;
const gsd = Math.sqrt(g2 / soft.length);
for (let i = 0; i < field.length; i++) {
    field[i] += (GRAIN * soft[i]) / gsd;
}

const pixels = Buffer.alloc(N * N);
let sum = 0;
let sum2 = 0;
let min = 255;
for (let i = 0; i < field.length; i++) {
    const v = Math.max(0, Math.min(255, Math.round(field[i])));
    pixels[i] = v;
    sum += v;
    sum2 += v * v;
    min = Math.min(min, v);
}
const mean = sum / field.length;
const sd = Math.sqrt(sum2 / field.length - mean * mean);

mkdirSync(dirname(OUT), { recursive: true });
const raw = { raw: { width: N, height: N, channels: 1 } };

// Highest quality that fits the budget (noise is costly to encode; quality keeps the grain).
let written = null;
for (const quality of [90, 88, 87, 86, 85, 84, 83, 82, 80, 76, 72, 68, 64, 60, 56, 52, 48]) {
    const buffer = await sharp(pixels, raw).avif({ quality, effort: 9, chromaSubsampling: '4:2:0' }).toBuffer();
    if (process.argv.includes('--verbose')) console.log(`quality ${quality}: ${buffer.length} bytes`);
    if (buffer.length <= MAX_BYTES) {
        writeFileSync(OUT, buffer);
        written = { quality, bytes: buffer.length };
        break;
    }
}
if (!written) {
    throw new Error(`paper texture: no quality fits ${MAX_BYTES} bytes`);
}

// Contrast that survived the encoder (decoded back from the file).
const decoded = await sharp(OUT).greyscale().raw().toBuffer();
let dsum = 0;
let dsum2 = 0;
for (const v of decoded) {
    dsum += v;
    dsum2 += v * v;
}
const dmean = dsum / decoded.length;
const dsd = Math.sqrt(dsum2 / decoded.length - dmean * dmean);

if (process.argv.includes('--preview')) {
    mkdirSync(dirname(PREVIEW), { recursive: true });
    // ×12 contrast around the mean, tiled 2×2 so seams would show.
    const stretched = Buffer.alloc(N * N);
    for (let i = 0; i < decoded.length; i++) {
        stretched[i] = Math.max(0, Math.min(255, Math.round(128 + (decoded[i] - dmean) * 12)));
    }
    const tile = await sharp(stretched, raw).png().toBuffer();
    await sharp({ create: { width: N * 2, height: N * 2, channels: 3, background: { r: 0, g: 0, b: 0 } } })
        .composite([
            { input: tile, left: 0, top: 0 },
            { input: tile, left: N, top: 0 },
            { input: tile, left: 0, top: N },
            { input: tile, left: N, top: N },
        ])
        .png()
        .toFile(PREVIEW);
}

console.log(
    JSON.stringify({
        file: 'public/images/d2/paper-512.avif',
        bytes: statSync(OUT).size,
        quality: written.quality,
        source: { mean: +mean.toFixed(2), sd: +sd.toFixed(2), min },
        decoded: { mean: +dmean.toFixed(2), sd: +dsd.toFixed(2), contrastPct: +((dsd / 255) * 100).toFixed(2) },
    }),
);
