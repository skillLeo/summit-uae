#!/usr/bin/env node
/**
 * Publishes the official WhatsApp logo files (brief §3.8, D10; App\Design\D2\WhatsAppLogo) UNCHANGED.
 *
 * The owner drops Meta's files (https://www.meta.com/brand/resources/whatsapp/whatsapp-brand/, Meta's brand
 * guidelines allow no other source and no modification) into resources/brand/whatsapp/:
 *   whatsapp-logo-light.svg   Meta's logo for light backgrounds (or .png / .webp)
 *   whatsapp-logo-dark.svg    Meta's logo for dark backgrounds (or .png / .webp)
 * This script copies each one byte for byte to public/d2/brand/whatsapp/{name}.{8-hex sha256}.{ext} and removes
 * older copies, so the pages link a cacheable file whose name changes with its content. It never opens, parses,
 * optimises or converts a file. Until it runs, the pages inline the same bytes as a data: URI (the switch to the
 * official logo never waits for it); tests/Feature/Design/D2/WhatsAppLogoTest.php fails until it has run.
 *
 *   node scripts/d2/whatsapp-logo.mjs           copy the files, delete stale copies
 *   node scripts/d2/whatsapp-logo.mjs --check   change nothing; exit 1 when a copy is missing or stale
 *   --source <dir> --public <dir>               other folders (tests, a scratch run)
 */
import { createHash } from 'node:crypto';
import { copyFileSync, existsSync, mkdirSync, readFileSync, readdirSync, statSync, unlinkSync } from 'node:fs';
import { dirname, extname, join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const arg = (name, fallback) => {
    const i = process.argv.indexOf(name);
    return i > -1 && process.argv[i + 1] ? process.argv[i + 1] : fallback;
};
const SOURCE = arg('--source', join(ROOT, 'resources/brand/whatsapp'));
const PUBLIC = arg('--public', join(ROOT, 'public/d2/brand/whatsapp'));
const CHECK = process.argv.includes('--check');

// Same rules as App\Design\D2\WhatsAppLogo: two variants, svg preferred, then png, then webp.
const VARIANTS = ['light', 'dark'];
const FORMATS = ['svg', 'png', 'webp'];
const BASENAME = 'whatsapp-logo-';

const sha256 = (file) => createHash('sha256').update(readFileSync(file)).digest('hex');
const rel = (file) => relative(ROOT, file) || file;

function source(variant) {
    for (const ext of FORMATS) {
        const file = join(SOURCE, `${BASENAME}${variant}.${ext}`);
        if (existsSync(file) && statSync(file).isFile() && statSync(file).size > 0) return file;
    }
    return null;
}

const wanted = new Map(); // published name => source file
for (const variant of VARIANTS) {
    const file = source(variant);
    if (!file) {
        console.log(`${variant}: no ${BASENAME}${variant}.{${FORMATS.join(',')}} in ${rel(SOURCE)} (the placeholder stays)`);
        continue;
    }
    const hash = sha256(file);
    const name = `${BASENAME}${variant}.${hash.slice(0, 8)}${extname(file).toLowerCase()}`;
    wanted.set(name, { file, hash });
}

const existing = existsSync(PUBLIC) ? readdirSync(PUBLIC).filter((name) => name.startsWith(BASENAME)) : [];
const stale = existing.filter((name) => !wanted.has(name));
const missing = [...wanted].filter(([name, { hash }]) => {
    const copy = join(PUBLIC, name);
    return !existsSync(copy) || sha256(copy) !== hash;
});

if (CHECK) {
    for (const [name] of missing) console.log(`missing or different: ${rel(join(PUBLIC, name))}`);
    for (const name of stale) console.log(`stale: ${rel(join(PUBLIC, name))}`);
    if (missing.length || stale.length) {
        console.log('Run: node scripts/d2/whatsapp-logo.mjs');
        process.exit(1);
    }
    console.log(`up to date (${wanted.size} file${wanted.size === 1 ? '' : 's'})`);
    process.exit(0);
}

if (wanted.size) mkdirSync(PUBLIC, { recursive: true });
for (const [name, { file, hash }] of missing) {
    const copy = join(PUBLIC, name);
    copyFileSync(file, copy);
    if (sha256(copy) !== hash) throw new Error(`${rel(copy)}: the copy differs from ${rel(file)}`);
    console.log(`published ${rel(file)} -> ${rel(copy)}`);
}
for (const name of stale) {
    unlinkSync(join(PUBLIC, name));
    console.log(`removed ${rel(join(PUBLIC, name))}`);
}
console.log(`${wanted.size} official file${wanted.size === 1 ? '' : 's'} published, ${stale.length} removed`);
