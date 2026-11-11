/**
 * Screenshot and result files for the Design 2 harness. Each capture overwrites its own files in the run
 * folder; the version it replaces moves to `prev/` beside it, so the report can show "previous run" next to
 * the current one even after partial re-runs on the same day.
 */
import { existsSync, mkdirSync, renameSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import sharp from 'sharp';

/** Moves an existing file to <dir>/prev/<name> (replacing an older previous version). */
export function archive(file) {
    if (!existsSync(file)) return;
    const prevDir = path.join(path.dirname(file), 'prev');
    mkdirSync(prevDir, { recursive: true });
    renameSync(file, path.join(prevDir, path.basename(file)));
}

export function writeJson(file, data, { keepPrevious = true } = {}) {
    mkdirSync(path.dirname(file), { recursive: true });
    if (keepPrevious) archive(file);
    writeFileSync(file, `${JSON.stringify(data, null, 2)}\n`);
}

export async function writePng(file, buffer) {
    mkdirSync(path.dirname(file), { recursive: true });
    archive(file);
    writeFileSync(file, buffer);
}

const SEGMENT = 4096;

/**
 * Full page at CSS-pixel scale, as a PNG Buffer. Chrome wraps captures taller than 16 384 output pixels
 * (the bottom repeats the top), so tall pages are captured in 4 096 px clipped segments and stitched.
 * `animations`: Playwright's mode ('disabled' finishes / cancels animations before every segment; the
 * Design 2 specs freeze the page once themselves, runtime.mjs freezeAnimations, and pass 'allow').
 */
export async function fullPageBuffer(page, { animations = 'disabled' } = {}) {
    const shot = { scale: 'css', animations, caret: 'hide' };
    const { width, height } = await page.evaluate(() => ({
        width: document.documentElement.clientWidth || window.innerWidth,
        height: Math.max(document.documentElement.scrollHeight, document.body ? document.body.scrollHeight : 0),
    }));
    if (height <= SEGMENT) {
        return { buffer: await page.screenshot({ ...shot, fullPage: true }), width, height, segments: 1 };
    }
    const parts = [];
    for (let top = 0; top < height; top += SEGMENT) {
        const clip = { x: 0, y: top, width, height: Math.min(SEGMENT, height - top) };
        parts.push({ input: await page.screenshot({ ...shot, fullPage: true, clip }), top, left: 0 });
    }
    const buffer = await sharp({ create: { width, height, channels: 4, background: '#ffffff' } }).composite(parts).png().toBuffer();
    return { buffer, width, height, segments: parts.length };
}

export async function fullPageShot(page, file, options = {}) {
    const shot = await fullPageBuffer(page, options);
    await writePng(file, shot.buffer);
    return { width: shot.width, height: shot.height, segments: shot.segments };
}

/**
 * One image with several frames (hero storyboard): landscape frames in a 3-column grid, portrait frames
 * in one row, each scaled to `cell` px on its long side, captioned with its label.
 */
export async function filmstrip(frames, file, { cell = 640, gap = 12, caption = 28 } = {}) {
    if (!frames.length) return null;
    const metas = await Promise.all(frames.map((f) => sharp(f.buffer).metadata()));
    const landscape = metas[0].width >= metas[0].height;
    const scale = cell / Math.max(metas[0].width, metas[0].height);
    const w = Math.round(metas[0].width * scale);
    const h = Math.round(metas[0].height * scale);
    const cols = landscape ? Math.min(3, frames.length) : frames.length;
    const rows = Math.ceil(frames.length / cols);
    const width = cols * w + (cols + 1) * gap;
    const height = rows * (h + caption) + (rows + 1) * gap;
    const layers = [];
    for (const [i, f] of frames.entries()) {
        const x = gap + (i % cols) * (w + gap);
        const y = gap + Math.floor(i / cols) * (h + caption + gap);
        const label = String(f.label).replace(/[<&>]/g, '');
        layers.push({
            input: Buffer.from(
                `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${caption}"><text x="2" y="${caption - 9}" font-family="Helvetica, Arial, sans-serif" font-size="18" fill="#3A3A3F">${label}</text></svg>`,
            ),
            left: x,
            top: y,
        });
        layers.push({ input: await sharp(f.buffer).resize(w, h, { fit: 'fill' }).png().toBuffer(), left: x, top: y + caption });
    }
    const buffer = await sharp({ create: { width, height, channels: 4, background: '#F7F6F3' } }).composite(layers).png().toBuffer();
    await writePng(file, buffer);
    return { width, height };
}
