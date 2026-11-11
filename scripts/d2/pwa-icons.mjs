#!/usr/bin/env node
/**
 * Design 2 PWA files (upgrade spec §5.5 and §12 E; docs/d2/engineering.md): the web app manifests
 * public/d2/manifest-{locale}.webmanifest and their icons public/d2/icons/*.png, generated from the
 * official brand artwork in package/brand/. Linked only from the Design 2 layout
 * (resources/views/designs/d2/layouts/app.blade.php); Design 1's /{locale}/manifest.webmanifest route and
 * its /icons/*.png stay untouched.
 *
 *   node scripts/d2/pwa-icons.mjs           write the icons and the three manifests
 *   node scripts/d2/pwa-icons.mjs --check   write nothing; exit 1 when a file on disk is missing or differs
 *
 * Icons: the official gold emblem, cut out of the transparent lockup logo-04-gold-on-light.png (the
 * wordmark is left out: it is unreadable at icon size), never redrawn or recoloured, centred on the night
 * colour (tokens.css --night, the spec's theme colour).
 *   icon-{192,512}.png       purpose "any": the emblem fills 62 % of the height.
 *   maskable-{192,512}.png   purpose "maskable": the emblem fills 50 % of the height, so even its corners
 *                            stay inside the central 80 % circle that every launcher mask keeps.
 * Manifest strings: "name" = the approved brand name (database/content/ui_strings.json brand.name),
 * "description" = the approved home meta description (database/content/pages.json P01.meta_description),
 * "short_name" = proposed copy (lang/{locale}/proposed-d2/pwa.php). Colours come from tokens.css
 * (--night theme, --canvas background). tests/Feature/Design/D2/PwaTest.php fails when the files drift
 * from these sources: re-run this script after a copy or token change.
 */
import sharp from 'sharp';
import { execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync, existsSync } from 'node:fs';
import { dirname, join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const SOURCE = join(ROOT, 'package/brand/logo-04-gold-on-light.png');
const OUT = join(ROOT, 'public/d2');
const ICONS = join(OUT, 'icons');
const CHECK = process.argv.includes('--check');

const ICON_SET = [
    { file: 'icon-192.png', size: 192, purpose: 'any', fill: 0.62 },
    { file: 'icon-512.png', size: 512, purpose: 'any', fill: 0.62 },
    { file: 'maskable-192.png', size: 192, purpose: 'maskable', fill: 0.5 },
    { file: 'maskable-512.png', size: 512, purpose: 'maskable', fill: 0.5 },
];

// ------------------------------------------------------------------------------------------ sources

function token(name) {
    const css = readFileSync(join(ROOT, 'resources/css/d2/tokens.css'), 'utf8');
    const match = css.match(new RegExp(`--${name}:\\s*(#[0-9A-Fa-f]{6})\\b`));
    if (!match) throw new Error(`tokens.css has no --${name} colour`);
    return match[1].toUpperCase();
}

function php(code) {
    return JSON.parse(execFileSync('php', ['-r', code], { cwd: ROOT, encoding: 'utf8' }));
}

const content = (file) => JSON.parse(readFileSync(join(ROOT, 'database/content', file), 'utf8'));

function strings() {
    const locales = php('require "vendor/autoload.php"; echo json_encode((require "config/summit.php")["locales"]);');
    const ui = content('ui_strings.json');
    const pages = content('pages.json');
    const out = {};
    for (const [locale, meta] of Object.entries(locales)) {
        const proposed = php(`echo json_encode(require "lang/${locale}/proposed-d2/pwa.php", JSON_UNESCAPED_UNICODE);`);
        const name = ui['brand.name']?.[locale];
        const description = pages.P01?.meta_description?.[locale];
        if (!name || !description || !proposed.short_name) throw new Error(`missing manifest copy for ${locale}`);
        out[locale] = { dir: meta.dir, name, short_name: proposed.short_name, description };
    }
    return out;
}

// ------------------------------------------------------------------------------------------ emblem

/** Bounding box of the emblem: the first run of inked columns of the lockup, before the wordmark gap. */
async function emblemBox() {
    const { data, info } = await sharp(SOURCE).ensureAlpha().raw().toBuffer({ resolveWithObject: true });
    const { width, height, channels } = info;
    const inked = (x, y) => data[(y * width + x) * channels + 3] > 8;

    const columns = new Uint32Array(width);
    let top = height;
    let bottom = -1;
    for (let y = 0; y < height; y += 1) {
        for (let x = 0; x < width; x += 1) {
            if (inked(x, y)) {
                columns[x] += 1;
                if (y < top) top = y;
                if (y > bottom) bottom = y;
            }
        }
    }
    const left = columns.findIndex((count) => count > 0);
    if (left < 0) throw new Error('the brand file has no visible pixels');

    // The emblem ends where a gap of at least 2 % of the artwork width (the space before the wordmark) begins.
    const gap = Math.round(width * 0.02);
    let right = left;
    for (let x = left, empty = 0; x < width; x += 1) {
        if (columns[x] > 0) {
            right = x;
            empty = 0;
        } else if (++empty >= gap) {
            break;
        }
    }

    // Rows of the emblem alone (the wordmark is shorter, so the lockup's rows may differ).
    let eTop = bottom;
    let eBottom = top;
    for (let y = top; y <= bottom; y += 1) {
        for (let x = left; x <= right; x += 1) {
            if (inked(x, y)) {
                if (y < eTop) eTop = y;
                if (y > eBottom) eBottom = y;
                break;
            }
        }
    }
    return { left, top: eTop, width: right - left + 1, height: eBottom - eTop + 1 };
}

async function icon(box, { size, fill }, background) {
    const height = Math.round(size * fill);
    const emblem = await sharp(SOURCE)
        .extract(box)
        .resize({ height, kernel: 'lanczos3' })
        .png()
        .toBuffer({ resolveWithObject: true });
    const { width } = emblem.info;
    return sharp({ create: { width: size, height: size, channels: 4, background } })
        .composite([{ input: emblem.data, left: Math.round((size - width) / 2), top: Math.round((size - height) / 2) }])
        .flatten({ background })
        .png({ compressionLevel: 9, palette: true, quality: 100, effort: 10 })
        .toBuffer();
}

// ------------------------------------------------------------------------------------------ manifest

function manifest(locale, copy, colours) {
    const base = `/${locale}/`;
    return `${JSON.stringify(
        {
            // One app per language, whatever the design: the same identity as Design 1's manifest
            // (its implicit id is its start_url, /{locale}/).
            id: base,
            name: copy.name,
            short_name: copy.short_name,
            description: copy.description,
            lang: locale,
            dir: copy.dir,
            start_url: base,
            scope: base,
            display: 'standalone',
            background_color: colours.background,
            theme_color: colours.theme,
            icons: ICON_SET.map(({ file, size, purpose }) => ({
                src: `/d2/icons/${file}`,
                sizes: `${size}x${size}`,
                type: 'image/png',
                purpose,
            })),
        },
        null,
        4,
    )}\n`;
}

// ------------------------------------------------------------------------------------------ run

const colours = { theme: token('night'), background: token('canvas') };
const files = new Map();

const box = await emblemBox();
for (const entry of ICON_SET) {
    files.set(join(ICONS, entry.file), await icon(box, entry, colours.theme));
}
for (const [locale, copy] of Object.entries(strings())) {
    files.set(join(OUT, `manifest-${locale}.webmanifest`), Buffer.from(manifest(locale, copy, colours)));
}

let stale = 0;
for (const [path, buffer] of files) {
    const name = relative(ROOT, path);
    const same = existsSync(path) && Buffer.compare(readFileSync(path), buffer) === 0;
    if (CHECK) {
        if (!same) {
            stale += 1;
            console.error(`stale: ${name}`);
        }
        continue;
    }
    mkdirSync(dirname(path), { recursive: true });
    if (!same) writeFileSync(path, buffer);
    console.log(`${same ? 'unchanged' : 'wrote'}  ${name}  ${(buffer.length / 1024).toFixed(1)} KB`);
}
console.log(`emblem ${box.width}×${box.height} at ${box.left},${box.top} · theme ${colours.theme} · background ${colours.background}`);
if (CHECK && stale > 0) process.exit(1);
