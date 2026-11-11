#!/usr/bin/env node
/**
 * Design 2 link checker (upgrade spec §12 Phase D, ACCEPTANCE D: "every route renders in EN/AR/RU with no
 * broken anchors (run a link checker)"; owner: system-pages).
 *
 *   QA_BASE_URL=http://127.0.0.1:8042 node scripts/qa/d2-links.mjs [--locales=en,ar] [--json=report.json] [--verbose]
 *
 * Crawls every Design 2 route in EN, AR and RU (cookies summit_design=2 and summit_qa=1, so the floating
 * design switcher is never injected): home, styleguide, offline, P02-P08, L01, L02, the eleven services,
 * a 404 (an unknown path, expected to answer 404) and the root language chooser "/". On every page it reads
 * the server-rendered HTML (progressive enhancement: what works without JavaScript) and checks:
 *   - every internal link (<a>, <area>, <link>: alternates, canonical, manifest, icons, font preloads) and
 *     every internal <img src>: the status (2xx; a 3xx is reported as a redirect and followed);
 *   - every #fragment: an element with that id (or <a name>) on the target page, the Navigator deep link
 *     #navigator=S05-2 (format, the #navigator section and that situation's radio on the target page),
 *     and ?navigator=S05-2 references;
 *   - the URLs inside the page's JSON payloads (<script type="application/json">) and in the command
 *     palette index /search/{locale}.json, which become links once JavaScript renders them;
 *   - that each seed page is a Design 2 page (<html data-design="2">) with the expected status.
 * Links to the production host (config summit.canonical_host, https://www.summitlegaluae.com) are checked
 * on QA_BASE_URL by path. External links (other hosts, mailto:, tel:) are listed, never fetched. Vite dev
 * server assets are ignored. Prefill query codes (?service=, ?situation=, ?audience=, ?consultant=, ?tool=,
 * docs/d2/engineering.md §8) and duplicate ids are reported as warnings.
 *
 * Exit code 1 on any broken link, broken anchor, seed page with an unexpected status, or seed page that is
 * not Design 2; 0 otherwise. Paths mirror config/summit.php (pages, services): keep them in sync.
 */
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const BASE = (process.env.QA_BASE_URL || 'http://127.0.0.1:8042').replace(/\/+$/, '');
const BASE_ORIGIN = new URL(BASE).origin;
const CANONICAL_ORIGIN = new URL(process.env.SUMMIT_CANONICAL_HOST || 'https://www.summitlegaluae.com').origin;
const COOKIE = 'summit_design=2; summit_qa=1';
const CONCURRENCY = Number(process.env.QA_LINKS_CONCURRENCY || 4);
const TIMEOUT_MS = 90_000;

const args = process.argv.slice(2);
const option = (name) => args.find((a) => a.startsWith(`--${name}=`))?.split('=').slice(1).join('=');
const LOCALES = (option('locales') || 'en,ar,ru').split(',').filter(Boolean);
const JSON_OUT = option('json');
const VERBOSE = args.includes('--verbose');

// config/summit.php: 'pages' (P02-P08, L01, L02) and 'services' (S01-S11), plus the home, styleguide and
// offline routes of routes/web.php.
const PAGE_PATHS = {
    P01: '', styleguide: 'styleguide/', offline: 'offline/',
    P02: 'practice-areas/', P03: 'resources/', P04: 'case-studies/', P05: 'faq/', P06: 'about/', P07: 'contact/',
    P08: 'legal-disclaimer/', L01: 'privacy-policy/', L02: 'terms-and-conditions/',
};
const SERVICES = {
    S01: 'debt-recovery-cheque-cases', S02: 'criminal-cases', S03: 'crypto-digital-currency-cases',
    S04: 'corporate-commercial-law', S05: 'real-estate-rental-disputes', S06: 'labor-law',
    S07: 'tax-disputes-compliance', S08: 'family-law-wills', S09: 'compliance-regulatory',
    S10: 'legal-translation', S11: 'business-services',
};
const MISSING = 'd2-link-check-missing-page/';

// Prefill codes (docs/d2/engineering.md §8).
const PREFILL = {
    service: /^S(?:0[1-9]|1[01])$/,
    situation: /^S(?:0[1-9]|1[01])-[1-9]$/,
    audience: /^(?:individual|business|investor)$/,
    consultant: /^team-(?:ahmed|mohamed|sarah)$/,
    tool: /^(?:gratuity|rent)$/,
};
const NAV_REF = /^S(?:0[1-9]|1[01])-[1-9]$/;

// The Vite dev server origin (public/hot-d2, public/hot): its assets are not links.
const DEV_ORIGINS = new Set(['public/hot-d2', 'public/hot']
    .map((file) => join(ROOT, file))
    .filter((file) => existsSync(file))
    .map((file) => { try { return new URL(readFileSync(file, 'utf8').trim()).origin; } catch { return null; } })
    .filter(Boolean));

// ------------------------------------------------------------------------------------------- HTML parsing

const ENTITIES = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ' };
function decode(text) {
    return String(text).replace(/&(#x[0-9a-f]+|#\d+|[a-z]+);/gi, (all, code) => {
        if (code[0] === '#') {
            const n = code[1].toLowerCase() === 'x' ? parseInt(code.slice(2), 16) : parseInt(code.slice(1), 10);
            return Number.isFinite(n) ? String.fromCodePoint(n) : all;
        }
        return ENTITIES[code.toLowerCase()] ?? all;
    });
}

function parseAttributes(source = '') {
    const out = {};
    const re = /([^\s"'<>/=]+)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s"'=<>`]+)))?/g;
    let match;
    while ((match = re.exec(source))) {
        const name = match[1].toLowerCase();
        if (!(name in out)) out[name] = decode(match[2] ?? match[3] ?? match[4] ?? '');
    }
    return out;
}

const textOf = (html) => decode(html.replace(/<[^>]*>/g, ' ')).replace(/\s+/g, ' ').trim().slice(0, 70);

/** Strings in a JSON value that look like site URLs (absolute on the base or canonical host, or /xx/ paths). */
function urlsInJson(value, out = []) {
    if (typeof value === 'string') {
        if (/^\/(?:en|ar|ru)(?:\/|$)/.test(value) || value.startsWith(`${BASE_ORIGIN}/`) || value.startsWith(`${CANONICAL_ORIGIN}/`)) out.push(value);
    } else if (Array.isArray(value)) {
        value.forEach((item) => urlsInJson(item, out));
    } else if (value && typeof value === 'object') {
        Object.values(value).forEach((item) => urlsInJson(item, out));
    }
    return out;
}

function parseHtml(html) {
    const doc = { ids: new Map(), refs: new Set(), links: [], json: [], design2: /<html\b[^>]*\bdata-design="2"/i.test(html) };

    for (const [, attrSource, body] of html.matchAll(/<script\b([^>]*)>([\s\S]*?)<\/script>/gi)) {
        const attrs = parseAttributes(attrSource);
        if ((attrs.type || '').toLowerCase() !== 'application/json') continue;
        const label = attrs.id ? `#${attrs.id}` : Object.keys(attrs).find((name) => name.startsWith('data-')) || 'json';
        try {
            for (const url of urlsInJson(JSON.parse(body))) doc.json.push({ href: url, label });
        } catch {
            doc.json.push({ href: null, label, error: 'invalid JSON payload' });
        }
    }

    const clean = html
        .replace(/<!--[\s\S]*?-->/g, '')
        .replace(/<script\b[\s\S]*?<\/script>/gi, '')
        .replace(/<style\b[\s\S]*?<\/style>/gi, '');

    for (const [, tag, attrSource] of clean.matchAll(/<([a-zA-Z][a-zA-Z0-9-]*)(\s[^>]*)?>/g)) {
        const name = tag.toLowerCase();
        const attrs = parseAttributes(attrSource);
        if (attrs.id !== undefined) doc.ids.set(attrs.id, (doc.ids.get(attrs.id) || 0) + 1);
        if (name === 'a' && attrs.name) doc.ids.set(attrs.name, (doc.ids.get(attrs.name) || 0) + 1);
        if (name === 'input' && attrs.type === 'radio' && NAV_REF.test(attrs.value || '')) doc.refs.add(attrs.value);
        if ((name === 'link' || name === 'area') && attrs.href !== undefined) {
            doc.links.push({ href: attrs.href, tag: name, rel: attrs.rel || '', text: '', cls: attrs.class || '' });
        }
        if (name === 'img' && attrs.src) doc.links.push({ href: attrs.src, tag: 'img', rel: '', text: attrs.alt || '', cls: attrs.class || '' });
    }
    for (const [, attrSource, inner] of clean.matchAll(/<a\b([^>]*)>([\s\S]*?)<\/a>/gi)) {
        const attrs = parseAttributes(attrSource);
        if (attrs.href === undefined) continue;
        doc.links.push({ href: attrs.href, tag: 'a', rel: attrs.rel || '', text: textOf(inner) || attrs['aria-label'] || '', cls: attrs.class || '' });
    }
    return doc;
}

// ------------------------------------------------------------------------------------------- fetching

const cache = new Map();

async function request(url) {
    try {
        const res = await fetch(url, {
            redirect: 'manual',
            headers: { cookie: COOKIE, accept: 'text/html,application/json;q=0.9,*/*;q=0.8', 'user-agent': 'summit-d2-links/1.0' },
            signal: AbortSignal.timeout(TIMEOUT_MS),
        });
        const type = res.headers.get('content-type') || '';
        const location = res.headers.get('location');
        let body = null;
        if (/text\/html|json/.test(type)) body = await res.text();
        else await res.body?.cancel();
        return { status: res.status, type, location: location ? new URL(location, url).href : null, body };
    } catch (error) {
        return { status: 0, type: '', location: null, body: null, error: error?.name === 'TimeoutError' ? 'timeout' : String(error?.message || error) };
    }
}

/** Fetch once per URL (without fragment): status, redirect chain, final status and the parsed page. */
function load(url) {
    if (!cache.has(url)) {
        cache.set(url, (async () => {
            const chain = [];
            let current = url;
            let res = await request(current);
            while (res.status >= 300 && res.status < 400 && res.location && chain.length < 5) {
                chain.push({ from: current, status: res.status, to: res.location });
                current = res.location;
                res = await request(current);
            }
            const html = res.body && /text\/html/.test(res.type) ? res.body : null;
            const json = res.body && /json/.test(res.type) ? res.body : null;
            return { url, final: current, status: res.status, error: res.error, chain, type: res.type, doc: html ? parseHtml(html) : null, json };
        })());
    }
    return cache.get(url);
}

async function pool(items, worker) {
    const queue = [...items];
    await Promise.all(Array.from({ length: Math.min(CONCURRENCY, queue.length) }, async () => {
        while (queue.length) await worker(queue.shift());
    }));
}

// ------------------------------------------------------------------------------------------- classification

function classify(href, pageUrl) {
    const raw = (href ?? '').trim();
    if (raw === '') return { kind: 'broken', reason: 'empty href' };
    if (/^javascript:/i.test(raw)) return { kind: 'broken', reason: 'javascript: URL' };
    if (raw === '#') return { kind: 'broken', reason: 'empty fragment "#" (no target)' };
    if (/^(?:mailto|tel|sms):/i.test(raw)) return { kind: 'external', url: raw };
    let url;
    try {
        url = new URL(raw, pageUrl);
    } catch {
        return { kind: 'broken', reason: 'invalid URL' };
    }
    if (!/^https?:$/.test(url.protocol)) return { kind: 'external', url: url.href };
    if (DEV_ORIGINS.has(url.origin)) return { kind: 'dev' };
    if (url.origin === BASE_ORIGIN) return { kind: 'internal', url, via: null };
    if (url.origin === CANONICAL_ORIGIN) return { kind: 'internal', url: new URL(url.pathname + url.search + url.hash, BASE_ORIGIN), via: 'canonical host' };
    return { kind: 'external', url: url.href };
}

function fragmentProblem(fragment, target) {
    if (!fragment) return null;
    let id;
    try {
        id = decodeURIComponent(fragment);
    } catch {
        return `undecodable fragment "#${fragment}"`;
    }
    if (id.startsWith(':~:')) return null;
    if (!target.doc) return `cannot check "#${id}": the target is not an HTML page`;
    if (id.startsWith('navigator=')) {
        const ref = id.slice('navigator='.length);
        if (!NAV_REF.test(ref)) return `invalid Navigator deep link "#${id}" (expected #navigator=S05-2)`;
        if (!target.doc.ids.has('navigator')) return `Navigator deep link "#${id}": the target has no #navigator section`;
        if (!target.doc.refs.has(ref)) return `Navigator deep link "#${id}": situation ${ref} is not on the target page`;
        return null;
    }
    if (id === 'top' && !target.doc.ids.has('top')) return null; // HTML: "#top" scrolls to the top
    return target.doc.ids.has(id) ? null : `no element with id="${id}" on the target page`;
}

function queryWarnings(url) {
    const out = [];
    for (const [key, pattern] of Object.entries(PREFILL)) {
        const values = url.searchParams.getAll(key);
        if (values.length > 1) out.push(`?${key} appears ${values.length} times`);
        for (const value of values) if (!pattern.test(value)) out.push(`unknown ?${key}=${value}`);
    }
    return out;
}

// ------------------------------------------------------------------------------------------- crawl

const seeds = [{ url: `${BASE}/`, expect: 200, label: 'chooser' }];
for (const locale of LOCALES) {
    for (const [code, path] of Object.entries(PAGE_PATHS)) seeds.push({ url: `${BASE}/${locale}/${path}`, expect: 200, label: `${locale} ${code}` });
    for (const [code, slug] of Object.entries(SERVICES)) seeds.push({ url: `${BASE}/${locale}/practice-areas/${slug}/`, expect: 200, label: `${locale} ${code}` });
    seeds.push({ url: `${BASE}/${locale}/${MISSING}`, expect: 404, label: `${locale} 404` });
}

const started = Date.now();
const problems = { pages: [], broken: [], anchors: [], warnings: [], redirects: [] };
const external = new Map();
const occurrences = [];

process.stdout.write(`Design 2 link check · ${BASE} · ${seeds.length} pages · ${LOCALES.join(', ')}\n`);
await pool(seeds, async (seed) => {
    const page = await load(seed.url);
    const design = page.doc?.design2;
    if (page.status !== seed.expect) problems.pages.push(`${seed.label}: ${seed.url} answered ${page.status || page.error}${page.chain.length ? ` after ${page.chain.map((c) => c.status).join(' → ')}` : ''}, expected ${seed.expect}`);
    else if (page.doc && !design) problems.pages.push(`${seed.label}: ${seed.url} is not a Design 2 page (no <html data-design="2">)`);
    if (VERBOSE) process.stdout.write(`  ${String(page.status).padEnd(3)} ${seed.label.padEnd(9)} ${seed.url}\n`);
    if (!page.doc) return;

    for (const [id, count] of page.doc.ids) if (count > 1) problems.warnings.push(`duplicate id="${id}" (${count}×) on ${seed.url}`);

    const sources = [
        ...page.doc.links.map((link) => ({ ...link, source: link.tag })),
        ...page.doc.json.map((item) => ({ href: item.href, tag: 'json', rel: '', text: item.label, cls: '', source: `json ${item.label}`, error: item.error })),
    ];
    for (const link of sources) {
        if (link.error) {
            problems.broken.push({ page: seed.url, href: '', reason: link.error, element: link.source });
            continue;
        }
        if (link.tag === 'link' && /\b(?:stylesheet|modulepreload)\b/.test(link.rel)) {
            const kind = classify(link.href, page.final).kind;
            if (kind === 'dev' || kind === 'external') continue;
        }
        occurrences.push({ page: seed.url, ...link, resolved: classify(link.href, page.final) });
    }
});

// The command palette index (public/search/{locale}.json): every result URL is a link once it is shown.
for (const locale of LOCALES) {
    const index = await load(`${BASE}/search/${locale}.json`);
    if (index.status !== 200 || !index.json) {
        problems.warnings.push(`palette index /search/${locale}.json answered ${index.status || index.error}`);
        continue;
    }
    try {
        for (const href of urlsInJson(JSON.parse(index.json))) {
            occurrences.push({ page: `${BASE}/search/${locale}.json`, href, tag: 'json', rel: '', text: 'palette index', cls: '', source: 'palette index', resolved: classify(href, `${BASE}/`) });
        }
    } catch {
        problems.broken.push({ page: `${BASE}/search/${locale}.json`, href: '', reason: 'invalid JSON', element: 'palette index' });
    }
}

const internal = occurrences.filter((o) => o.resolved.kind === 'internal');
const targets = [...new Set(internal.map((o) => { const u = new URL(o.resolved.url); u.hash = ''; return u.href; }))];
await pool(targets, (url) => load(url));

const describe = (o) => `${o.source}${o.cls ? `.${o.cls.split(/\s+/)[0]}` : ''}${o.text ? ` "${o.text}"` : ''}`;
for (const o of occurrences) {
    const r = o.resolved;
    if (r.kind === 'broken') {
        problems.broken.push({ page: o.page, href: o.href, reason: r.reason, element: describe(o) });
        continue;
    }
    if (r.kind === 'external') {
        const entry = external.get(r.url) || { count: 0, pages: new Set() };
        entry.count += 1;
        entry.pages.add(o.page);
        external.set(r.url, entry);
        continue;
    }
    if (r.kind !== 'internal') continue;
    const url = new URL(r.url);
    const fragment = url.hash.slice(1);
    url.hash = '';
    // "#id" points into the page itself, whatever its status (the skip link and no-JS targets of a 404).
    const samePage = o.tag !== 'json' && (o.href || '').trim().startsWith('#');
    const target = await load(samePage ? o.page : url.href);
    if (samePage) {
        const problem = fragmentProblem(fragment, target);
        if (problem) problems.anchors.push({ page: o.page, href: o.href, reason: problem, element: describe(o) });
        continue;
    }
    // A JSON payload may name an endpoint (the wizard's POST /xx/enquiries): 405 on GET means it exists.
    if (o.tag === 'json' && target.status === 405) continue;
    const ok = target.status >= 200 && target.status < 300;
    if (!ok) {
        problems.broken.push({ page: o.page, href: o.href, reason: `${target.status || target.error}${target.chain.length ? ` (after ${target.chain.map((c) => `${c.status} → ${c.to}`).join(', ')})` : ''}${r.via ? ` [${r.via}]` : ''}`, element: describe(o) });
        continue;
    }
    if (target.chain.length) problems.redirects.push(`${o.href} → ${target.final} (${target.chain.map((c) => c.status).join(', ')}) on ${o.page} ${describe(o)}`);
    const nav = url.searchParams.get('navigator');
    if (nav !== null && (!NAV_REF.test(nav) || (target.doc && !target.doc.refs.has(nav)))) {
        problems.anchors.push({ page: o.page, href: o.href, reason: `?navigator=${nav} is not a known situation (S01-1 … S11-4)`, element: describe(o) });
    }
    for (const warning of queryWarnings(url)) problems.warnings.push(`${warning}: ${o.href} on ${o.page} ${describe(o)}`);
    const problem = fragmentProblem(fragment, target);
    if (problem) problems.anchors.push({ page: o.page, href: o.href, reason: problem, element: describe(o) });
}

// ------------------------------------------------------------------------------------------- report

const uniq = (list) => [...new Map(list.map((p) => [`${p.page}|${p.href}|${p.reason}`, p])).values()];
problems.broken = uniq(problems.broken);
problems.anchors = uniq(problems.anchors);
problems.warnings = [...new Set(problems.warnings)];
problems.redirects = [...new Set(problems.redirects)];

const path = (href) => { try { const u = new URL(href, BASE); return u.origin === BASE_ORIGIN ? u.pathname + u.search + u.hash : href; } catch { return href; } };
const print = (title, rows) => {
    if (!rows.length) return;
    process.stdout.write(`\n${title} (${rows.length})\n`);
    for (const row of rows) process.stdout.write(`  ${row}\n`);
};

const resolvedPath = (p) => { try { return path(new URL(p.href, p.page).href); } catch { return p.href; } };
const linkRows = (rows) => rows.map((p) => `${p.reason}  ${p.href ? resolvedPath(p) : '(none)'}  ← ${path(p.page)}  ${p.element}`);
print('Pages that did not render as expected', problems.pages);
print('Broken links', linkRows(problems.broken));
print('Broken anchors', linkRows(problems.anchors));
print('Redirecting internal links (not broken; link the final URL instead)', problems.redirects.map(path));
print('Warnings', problems.warnings);
print('External links (listed, not fetched)', [...external.entries()].sort((a, b) => a[0].localeCompare(b[0])).map(([url, e]) => `${url}  (${e.count}× on ${e.pages.size} page${e.pages.size === 1 ? '' : 's'})`));

const checkedLinks = internal.length;
const summary = {
    base: BASE,
    seconds: Math.round((Date.now() - started) / 100) / 10,
    pages: seeds.length,
    internalLinks: checkedLinks,
    uniqueTargets: targets.length,
    externalLinks: external.size,
    pageProblems: problems.pages.length,
    broken: problems.broken.length,
    brokenAnchors: problems.anchors.length,
    redirects: problems.redirects.length,
    warnings: problems.warnings.length,
};
process.stdout.write(`\n${JSON.stringify(summary)}\n`);
if (JSON_OUT) {
    writeFileSync(JSON_OUT, JSON.stringify({ summary, ...problems, external: [...external.keys()] }, null, 2));
}
const failed = problems.pages.length + problems.broken.length + problems.anchors.length > 0;
process.stdout.write(failed ? 'FAIL: broken links or anchors (see above).\n' : 'OK: no broken links or anchors.\n');
process.exit(failed ? 1 : 0);
