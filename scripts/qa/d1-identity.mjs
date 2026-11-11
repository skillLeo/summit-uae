#!/usr/bin/env node
/**
 * Proves Design 1 is unchanged (docs/upgrade-plan.md, Phase G): every Design 1 route in EN/AR/RU is
 * fetched from a server running the git tag `design-1.1` (QA_D1_BASE, default http://127.0.0.1:8041) and
 * from this branch (QA_BASE_URL, default http://127.0.0.1:8040; sent with summit_design=1 and the QA
 * cookie so the switcher is not injected), normalised (CSP nonce, CSRF token, idempotency UUID, origin)
 * and compared byte for byte. Also compares the Design 1 build output (public/build, minus manifest
 * source keys) when QA_D1_BUILD points at the baseline's public/build.
 *
 *   node scripts/qa/d1-identity.mjs            exit 1 on any difference
 */
import { createHash } from 'node:crypto';
import { readFileSync, readdirSync, statSync, mkdirSync, writeFileSync, existsSync } from 'node:fs';
import { join, relative } from 'node:path';

const BASE = process.env.QA_D1_BASE || 'http://127.0.0.1:8041';
const BRANCH = process.env.QA_BASE_URL || 'http://127.0.0.1:8040';
const OUT = 'storage/app/qa/d1-identity';
const LOCALES = ['en', 'ar', 'ru'];

const pages = ['', 'practice-areas/', 'resources/', 'case-studies/', 'faq/', 'about/', 'contact/', 'legal-disclaimer/', 'privacy-policy/', 'terms-and-conditions/', 'styleguide/', 'offline/', 'no-such-page-qa/'];
const services = ['debt-recovery-cheque-cases', 'criminal-cases', 'crypto-digital-currency-cases', 'corporate-commercial-law', 'real-estate-rental-disputes', 'labor-law', 'tax-disputes-compliance', 'family-law-wills', 'compliance-regulatory', 'legal-translation', 'business-services'];
const paths = ['/', '/sitemap.xml'];
for (const l of LOCALES) {
    for (const p of pages) paths.push(`/${l}/${p}`);
    for (const s of services) paths.push(`/${l}/practice-areas/${s}/`);
    paths.push(`/${l}/?navigator=S05-2`, `/${l}/manifest.webmanifest`);
}

const normalise = (html, origin) =>
    html
        .replaceAll(origin, 'ORIGIN')
        .replace(/nonce="[^"]+"/g, 'nonce=""')
        .replace(/name="_token" value="[^"]+"/g, 'name="_token" value=""')
        .replace(/<meta name="csrf-token" content="[^"]+">/g, '<meta name="csrf-token" content="">')
        .replace(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/g, 'uuid');

async function get(base, path, cookie) {
    const res = await fetch(base + path, { redirect: 'manual', headers: cookie ? { cookie } : {} });
    const body = await res.text();
    return { status: res.status, location: res.headers.get('location')?.replace(base, 'ORIGIN') ?? null, body: normalise(body, base) };
}

const sha = (s) => createHash('sha256').update(s).digest('hex').slice(0, 16);
mkdirSync(OUT, { recursive: true });

let diffs = 0;
const rows = [];
for (const path of paths) {
    const [a, b, c] = await Promise.all([
        get(BASE, path, 'summit_qa=1'),
        get(BRANCH, path, 'summit_qa=1'),
        get(BRANCH, path, 'summit_qa=1; summit_design=1'),
    ]);
    const same = a.status === b.status && a.location === b.location && a.body === b.body && b.body === c.body && b.status === c.status;
    if (!same) {
        diffs++;
        const name = path.replace(/[^a-z0-9]+/gi, '_');
        writeFileSync(join(OUT, `${name}.baseline.html`), a.body);
        writeFileSync(join(OUT, `${name}.branch.html`), b.body);
    }
    rows.push(`${same ? 'SAME' : 'DIFF'}  ${String(a.status).padEnd(3)} ${path}  ${sha(a.body)} ${sha(b.body)} ${sha(c.body)}`);
}

function hashTree(dir) {
    const out = {};
    const walk = (d) => {
        for (const f of readdirSync(d)) {
            const p = join(d, f);
            if (statSync(p).isDirectory()) walk(p);
            else if (f !== 'manifest.json') out[relative(dir, p)] = createHash('sha256').update(readFileSync(p)).digest('hex');
        }
    };
    walk(dir);
    return out;
}

let buildLine = 'build: skipped (set QA_D1_BUILD to the baseline public/build)';
if (process.env.QA_D1_BUILD && existsSync(process.env.QA_D1_BUILD)) {
    const a = hashTree(process.env.QA_D1_BUILD);
    const b = hashTree('public/build');
    const keys = new Set([...Object.keys(a), ...Object.keys(b)]);
    const changed = [...keys].filter((k) => a[k] !== b[k]);
    buildLine = changed.length === 0 ? `build: ${keys.size} Design 1 asset files byte-identical` : `build: DIFF ${changed.join(', ')}`;
    if (changed.length) diffs++;
}

const report = [`Design 1 identity: ${paths.length} URLs, ${diffs} difference(s)`, buildLine, '', ...rows].join('\n');
writeFileSync(join(OUT, 'report.txt'), report + '\n');
console.log(report);
process.exit(diffs === 0 ? 0 : 1);
