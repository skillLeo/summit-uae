#!/usr/bin/env node
/**
 * Design 2 team portraits (upgrade spec §5.5 / §10 / §12 C8): 4:5 crops of the client's photos in
 * package/portraits/, written as AVIF and WebP at two widths to public/images/d2/team/.
 *
 *   node scripts/d2/portraits.mjs
 *
 * Output: public/images/d2/team/<slug>-<width>.{avif,webp} for every width in WIDTHS (480 × 600 and
 * 800 × 1000). <x-d2.team.portrait> renders them with explicit width/height, lazily, and falls back to
 * the monogram when a file is missing (Ms Sarah Zaki until her portrait is supplied).
 *
 * Crop: centred on the face axis (midpoint of the pupils) with the top of the head 10 % of the crop
 * height below the top edge, so both heads sit at the same height and scale on the cards. The face
 * measurements are in source pixels of the PNGs. Pixels are only cropped and downscaled (Lanczos 3):
 * no colour grade, retouching or upscaling, and no metadata (sharp drops EXIF/XMP by default).
 * To add a portrait: put <slug>.png in package/portraits/, measure faceAxis / headTop, add a row
 * below, run the script, and set the member's `portrait` field to the slug in the content import.
 */
import fs from 'node:fs';
import path from 'node:path';
import sharp from 'sharp';

const ROOT = path.resolve(import.meta.dirname, '../..');
const SOURCE = path.join(ROOT, 'package/portraits');
const OUT = path.join(ROOT, 'public/images/d2/team');

const HEADROOM = 0.1;
const WIDTHS = [480, 800];
const PEOPLE = [
    // cropWidth: the widest 4:5 crop centred on the face axis that the photo can supply; the two
    // values give the two heads the same scale on the card.
    { slug: 'ahmed-mohammadi', faceAxis: 477, headTop: 208, cropWidth: 952 },
    { slug: 'dr-mohamed-raouf', faceAxis: 461, headTop: 220, cropWidth: 852 },
];
const QUALITY = {
    avif: { quality: 58, effort: 9, chromaSubsampling: '4:4:4' },
    webp: { quality: 84, effort: 6, smartSubsample: true },
};
const BUDGET = 180 * 1024; // spec §10: ≤ 180 KB each at 800 w

fs.mkdirSync(OUT, { recursive: true });
let failed = false;

for (const person of PEOPLE) {
    const src = path.join(SOURCE, `${person.slug}.png`);
    const meta = await sharp(src).metadata();
    const w = person.cropWidth;
    const h = Math.round((w * 5) / 4);
    const left = Math.round(person.faceAxis - w / 2);
    const top = Math.round(person.headTop - HEADROOM * h);
    if (left < 0 || top < 0 || left + w > meta.width || top + h > meta.height) {
        throw new Error(`${person.slug}: crop ${w}x${h}+${left}+${top} does not fit ${meta.width}x${meta.height}`);
    }
    const crop = await sharp(src).extract({ left, top, width: w, height: h }).toBuffer();
    for (const width of WIDTHS) {
        if (width > w) throw new Error(`${person.slug}: ${width} px would upscale a ${w} px crop`);
        const height = Math.round((width * 5) / 4);
        const resized = sharp(crop).resize(width, height, { kernel: 'lanczos3' });
        for (const [ext, options] of Object.entries(QUALITY)) {
            const file = path.join(OUT, `${person.slug}-${width}.${ext}`);
            const pipeline = resized.clone();
            if (ext === 'avif') pipeline.avif(options);
            else pipeline.webp(options);
            await pipeline.toFile(file);
            const bytes = fs.statSync(file).size;
            const over = bytes > BUDGET;
            failed ||= over;
            console.log(`${path.relative(ROOT, file).padEnd(52)} ${`${width}x${height}`.padEnd(10)} ${String(bytes).padStart(7)} B${over ? '  OVER BUDGET' : ''}`);
        }
    }
}

if (failed) {
    console.error('A portrait is over the 180 KB budget: lower the quality in QUALITY.');
    process.exit(1);
}
