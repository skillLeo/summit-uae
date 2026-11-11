/**
 * Pixel-by-pixel image comparison on raw RGBA buffers (sharp). Used by the Design 1 visual proof
 * (scripts/qa/d1-visual.mjs, exact: tolerance 0), the reduced-motion hero check and the report's
 * "changed since the comparison image" figure.
 */
import { mkdirSync } from 'node:fs';
import path from 'node:path';
import sharp from 'sharp';

/** Decodes any image sharp reads (path or Buffer) into { data, width, height } RGBA. */
export async function rgba(input) {
    const { data, info } = await sharp(input).ensureAlpha().raw().toBuffer({ resolveWithObject: true });
    return { data, width: info.width, height: info.height };
}

/**
 * Pure comparison of two decoded RGBA images. A pixel differs when any channel differs by more than
 * `tolerance` (0 = exact). Pixels outside the common area (different sizes) all count as different.
 * With `withDiff`, also returns the diff image over the common area: unchanged pixels faded to light
 * grey, differing pixels magenta.
 */
export function compareRgba(a, b, { tolerance = 0, withDiff = false } = {}) {
    const width = Math.min(a.width, b.width);
    const height = Math.min(a.height, b.height);
    const sameSize = a.width === b.width && a.height === b.height;
    const union = Math.max(a.width, b.width) * Math.max(a.height, b.height);
    const outside = union - width * height;
    if (sameSize && Buffer.compare(a.data, b.data) === 0) {
        return { different: 0, total: union, ratio: 0, sameSize, width, height, sizeA: [a.width, a.height], sizeB: [b.width, b.height], diff: null };
    }
    const diff = withDiff ? Buffer.alloc(width * height * 4) : null;
    let different = 0;
    let minY = Infinity;
    let maxY = -1;
    for (let y = 0; y < height; y += 1) {
        let rowA = y * a.width * 4;
        let rowB = y * b.width * 4;
        let rowD = y * width * 4;
        for (let x = 0; x < width; x += 1, rowA += 4, rowB += 4, rowD += 4) {
            const d = Math.max(
                Math.abs(a.data[rowA] - b.data[rowB]),
                Math.abs(a.data[rowA + 1] - b.data[rowB + 1]),
                Math.abs(a.data[rowA + 2] - b.data[rowB + 2]),
                Math.abs(a.data[rowA + 3] - b.data[rowB + 3]),
            );
            if (d > tolerance) {
                different += 1;
                if (y < minY) minY = y;
                if (y > maxY) maxY = y;
                if (diff) {
                    diff[rowD] = 255;
                    diff[rowD + 1] = 0;
                    diff[rowD + 2] = 200;
                    diff[rowD + 3] = 255;
                }
            } else if (diff) {
                const lum = 0.299 * b.data[rowB] + 0.587 * b.data[rowB + 1] + 0.114 * b.data[rowB + 2];
                const v = Math.round(255 - (255 - lum) * 0.25);
                diff[rowD] = v;
                diff[rowD + 1] = v;
                diff[rowD + 2] = v;
                diff[rowD + 3] = 255;
            }
        }
    }
    different += outside;
    return {
        different,
        total: union,
        ratio: union ? different / union : 0,
        sameSize,
        width,
        height,
        sizeA: [a.width, a.height],
        sizeB: [b.width, b.height],
        rows: maxY >= 0 ? [minY, maxY] : null,
        diff,
    };
}

/** Compares two images (paths or Buffers); writes the diff PNG to `diffFile` when they differ. */
export async function compareImages(a, b, { tolerance = 0, diffFile = null } = {}) {
    const [ia, ib] = await Promise.all([rgba(a), rgba(b)]);
    const result = compareRgba(ia, ib, { tolerance, withDiff: Boolean(diffFile) });
    if (diffFile && result.different > 0 && result.diff) {
        mkdirSync(path.dirname(diffFile), { recursive: true });
        await sharp(result.diff, { raw: { width: result.width, height: result.height, channels: 4 } }).png().toFile(diffFile);
    }
    delete result.diff;
    return result;
}
