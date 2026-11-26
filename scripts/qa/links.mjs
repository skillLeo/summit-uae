#!/usr/bin/env node
/**
 * Internal link check over the sitemap (client PDF item 28).
 *
 *   QA_BASE_URL=http://127.0.0.1:8045 node scripts/qa/links.mjs [--json=report.json] [--verbose]
 *
 * 1. Reads <base>/sitemap.xml: every current page in EN, AR and RU (its <loc> URLs are on the production host
 *    https://www.summitlegaluae.com; they are fetched on the base by path). The sitemap itself is checked: no
 *    duplicate <loc>, folder URLs only, no 404/offline/styleguide/calculators/admin/preview address, reciprocal
 *    xhtml:link alternates.
 * 2. Fetches every page (no JavaScript: the server-rendered HTML) and collects every internal reference:
 *    <a href>, <area href>, <link href> (canonical, alternate, manifest, icons, preloads) and <img src>, plus the
 *    URLs in the page's JSON-LD.
 * 3. Checks each internal target: it must answer 200, or a deliberate redirect (a 301/302 that ends on a 200 page,
 *    for example the Legal Translation address that the owner sends to the Practice Areas page with a 302);
 *    every #fragment must exist on the target page (an element with that id, or <a name>).
 * 4. Reports, as failures: broken targets (4xx/5xx, redirect loops, chains over 5), missing anchors, and any
 *    internal link to an old numbered or WordPress-style address (*.html, /wp-content/, /wp-admin/, /wp-json/,
 *    ?p=123, ?page_id=, /category/, /tag/, /feed/, /author/, /YYYY/MM/ dates, /index.php) or to the removed Legal
 *    Translation service (practice-areas/legal-translation/; the guide #legal-translation on the Resources page is a
 *    guide and stays, PDF item 10). Redirecting internal links are listed
 *    as warnings (link the final address instead). External links are listed, never fetched.
 *
 * Exit 0: no failure. 1: a failure. 2: the site or its sitemap cannot be read.
 */
import { writeFileSync } from 'node:fs';

const BASE = (process.env.QA_BASE_URL || 'http://127.0.0.1:8045').replace(/\/+$/, '');
const BASE_ORIGIN = new URL(BASE).origin;
const CANONICAL_ORIGIN = new URL(process.env.SUMMIT_CANONICAL_HOST || 'https://www.summitlegaluae.com').origin;
const CONCURRENCY = Number(process.env.QA_LINKS_CONCURRENCY || 4);
const TIMEOUT_MS = 120_000;
const args = process.argv.slice(2);
const option = (name) => args.find((a) => a.startsWith(`--${name}=`))?.split('=').slice(1).join('=');
const JSON_OUT = option('json');
const VERBOSE = args.includes('--verbose');

/** Old addresses that must never be linked (numbered .html pages, WordPress). */
const LEGACY = [
    [/\.html?(?:$|[?#])/i, 'numbered/.html address'],
    [/\/wp-(?:content|admin|includes|json|login)\b/i, 'WordPress address'],
    [/[?&](?:p|page_id|cat|attachment_id)=\d+/i, 'WordPress query address'],
    [/\/(?:category|tag|author|feed)\//i, 'WordPress archive address'],
    [/\/(?:19|20)\d{2}\/(?:0[1-9]|1[0-2])\//, 'WordPress dated address'],
    [/\/index\.php(?:$|[/?#])/i, 'front controller address'],
];
/** The removed service (owner decision 6). */
// (The Resources page's guide G08, #legal-translation, is a guide, not the service: kept, PDF item 10.)
const REMOVED = [[/practice-areas\/legal-translation(?:\/|[?#]|$)/i, 'link to the removed Legal Translation service']];
/** Never in the sitemap. */
const NOT_IN_SITEMAP = /\/(?:styleguide|offline|calculators|admin|preview|no-such-page[^/]*|404)\/|\/(?:manifest\.webmanifest|sitemap\.xml)$/i;

// ------------------------------------------------------------------------------------------- HTML parsing

const ENTITIES = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ' };
const decode = (text) => String(text).replace(/&(#x[0-9a-f]+|#\d+|[a-z]+);/gi, (all, code) => {
    if (code[0] === '#') {
        const n = code[1].toLowerCase() === 'x' ? parseInt(code.slice(2), 16) : parseInt(code.slice(1), 10);
        return Number.isFinite(n) ? String.fromCodePoint(n) : all;
    }
    return ENTITIES[code.toLowerCase()] ?? all;
});

function attributes(source = '') {
    const out = {};
    const re = /([^\s"'<>/=]+)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s"'=<>`]+)))?/g;
    let m;
    while ((m = re.exec(source))) {
        const name = m[1].toLowerCase();
        if (!(name in out)) out[name] = decode(m[2] ?? m[3] ?? m[4] ?? '');
    }
    return out;
}

const textOf = (html) => decode(html.replace(/<[^>]*>/g, ' ')).replace(/\s+/g, ' ').trim().slice(0, 60);

function jsonUrls(value, out = []) {
    if (typeof value === 'string') {
        if (/^https?:\/\//.test(value) || /^\/(?:en|ar|ru)(?:\/|$)/.test(value)) out.push(value);
    } else if (Array.isArray(value)) value.forEach((v) => jsonUrls(v, out));
    // "@id" names a node of the graph (…/#organization), not a link to follow.
    else if (value && typeof value === 'object') Object.entries(value).forEach(([k, v]) => { if (k !== '@context' && k !== '@id') jsonUrls(v, out); });
    return out;
}

function parse(html) {
    const doc = { ids: new Set(), links: [] };
    for (const [, attrSource, body] of html.matchAll(/<script\b([^>]*)>([\s\S]*?)<\/script>/gi)) {
        const type = (attributes(attrSource).type || '').toLowerCase();
        if (type !== 'application/ld+json') continue;
        try {
            for (const href of jsonUrls(JSON.parse(body))) doc.links.push({ href, tag: 'json-ld', text: '' });
        } catch {
            doc.links.push({ href: '', tag: 'json-ld', text: '', error: 'invalid JSON-LD' });
        }
    }
    const clean = html.replace(/<!--[\s\S]*?-->/g, '').replace(/<script\b[\s\S]*?<\/script>/gi, '').replace(/<style\b[\s\S]*?<\/style>/gi, '');
    for (const [, tag, attrSource] of clean.matchAll(/<([a-zA-Z][a-zA-Z0-9-]*)(\s[^>]*)?>/g)) {
        const name = tag.toLowerCase();
        const a = attributes(attrSource);
        if (a.id !== undefined) doc.ids.add(a.id);
        if (name === 'a' && a.name) doc.ids.add(a.name);
        if ((name === 'link' || name === 'area') && a.href !== undefined) doc.links.push({ href: a.href, tag: name === 'link' ? `link[rel=${a.rel || ''}]` : 'area', text: '' });
        if (name === 'img' && a.src) doc.links.push({ href: a.src, tag: 'img', text: a.alt || '' });
    }
    for (const [, attrSource, inner] of clean.matchAll(/<a\b([^>]*)>([\s\S]*?)<\/a>/gi)) {
        const a = attributes(attrSource);
        if (a.href !== undefined) doc.links.push({ href: a.href, tag: 'a', text: textOf(inner) || a['aria-label'] || '' });
    }
    return doc;
}

// ------------------------------------------------------------------------------------------- fetching

const cache = new Map();
async function request(url) {
    try {
        const res = await fetch(url, { redirect: 'manual', headers: { accept: 'text/html,application/xml;q=0.9,*/*;q=0.8', 'user-agent': 'summit-links/1.0' }, signal: AbortSignal.timeout(TIMEOUT_MS) });
        const type = res.headers.get('content-type') || '';
        const location = res.headers.get('location');
        let body = null;
        if (/text\/html|xml/.test(type)) body = await res.text();
        else await res.body?.cancel();
        return { status: res.status, type, location: location ? new URL(location, url).href : null, body };
    } catch (error) {
        return { status: 0, type: '', location: null, body: null, error: error?.name === 'TimeoutError' ? 'timeout' : String(error?.message || error) };
    }
}

function load(url) {
    if (!cache.has(url)) {
        cache.set(url, (async () => {
            const chain = [];
            let current = url;
            let res = await request(current);
            while (res.status >= 300 && res.status < 400 && res.location && chain.length <= 5) {
                if (chain.some((c) => c.from === res.location) || res.location === url) {
                    chain.push({ from: current, status: res.status, to: res.location });
                    return { url, final: res.location, status: 'loop', chain, doc: null };
                }
                chain.push({ from: current, status: res.status, to: res.location });
                current = res.location;
                res = await request(current);
            }
            const html = res.body && /text\/html/.test(res.type) ? res.body : null;
            return { url, final: current, status: res.status, error: res.error, chain, doc: html ? parse(html) : null, body: res.body };
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

/** internal (on the base or the production host) | external | skip, with the URL on the base. */
function classify(href, pageUrl) {
    const raw = (href ?? '').trim();
    if (raw === '') return { kind: 'broken', reason: 'empty href' };
    if (/^javascript:/i.test(raw)) return { kind: 'broken', reason: 'javascript: URL' };
    if (raw === '#') return { kind: 'broken', reason: 'empty fragment "#"' };
    if (/^(?:mailto|tel|sms|data):/i.test(raw)) return { kind: 'external', url: raw };
    let url;
    try {
        url = new URL(raw, pageUrl);
    } catch {
        return { kind: 'broken', reason: 'invalid URL' };
    }
    if (!/^https?:$/.test(url.protocol)) return { kind: 'external', url: url.href };
    if (url.origin === BASE_ORIGIN) return { kind: 'internal', url };
    if (url.origin === CANONICAL_ORIGIN) return { kind: 'internal', url: new URL(url.pathname + url.search + url.hash, BASE_ORIGIN) };
    return { kind: 'external', url: url.href };
}

// ------------------------------------------------------------------------------------------- run

const started = Date.now();
const problems = { sitemap: [], pages: [], broken: [], anchors: [], legacy: [], removed: [], redirects: [] };
const external = new Map();

const sitemap = await request(`${BASE}/sitemap.xml`);
if (sitemap.status !== 200 || !sitemap.body) {
    console.error(`links: cannot read ${BASE}/sitemap.xml (${sitemap.status || sitemap.error}). Start the site or set QA_BASE_URL.`);
    process.exit(2);
}
const locs = [...sitemap.body.matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => decode(m[1]).trim());
const seen = new Set();
for (const loc of locs) {
    if (seen.has(loc)) problems.sitemap.push(`duplicate <loc> ${loc}`);
    seen.add(loc);
    if (!loc.startsWith(`${CANONICAL_ORIGIN}/`)) problems.sitemap.push(`<loc> not on ${CANONICAL_ORIGIN}: ${loc}`);
    if (!/^https:\/\/[^/]+\/(?:en|ar|ru)\/(?:[a-z0-9-]+\/)*$/.test(loc)) problems.sitemap.push(`<loc> is not a folder URL: ${loc}`);
    if (NOT_IN_SITEMAP.test(loc)) problems.sitemap.push(`<loc> must not be in the sitemap: ${loc}`);
    for (const [re, why] of [...LEGACY, ...REMOVED]) if (re.test(loc)) problems.sitemap.push(`${why} in the sitemap: ${loc}`);
}
// Reciprocal alternates: every URL a <url> names as an alternate is itself a <loc> that names the same set.
const blocks = [...sitemap.body.matchAll(/<url>([\s\S]*?)<\/url>/g)].map((m) => ({
    loc: decode((m[1].match(/<loc>([^<]+)<\/loc>/) || [])[1] || '').trim(),
    alternates: Object.fromEntries([...m[1].matchAll(/hreflang="([^"]+)" href="([^"]+)"/g)].map((a) => [a[1], decode(a[2])])),
}));
const byLoc = new Map(blocks.map((b) => [b.loc, b]));
for (const block of blocks) {
    for (const [lang, href] of Object.entries(block.alternates)) {
        if (lang === 'x-default') continue;
        const other = byLoc.get(href);
        if (!other) problems.sitemap.push(`${block.loc}: alternate ${lang} ${href} is not in the sitemap`);
        else if (JSON.stringify(other.alternates) !== JSON.stringify(block.alternates)) problems.sitemap.push(`${block.loc} ↔ ${href}: the alternates differ`);
    }
}

const pages = locs.map((loc) => new URL(new URL(loc).pathname, BASE_ORIGIN).href);
process.stdout.write(`Link check · ${BASE} · sitemap: ${locs.length} URLs\n`);
const occurrences = [];
await pool(pages, async (url) => {
    const page = await load(url);
    if (page.status !== 200 || page.chain.length) {
        problems.pages.push(`${url} answered ${page.chain.map((c) => c.status).join(' → ')}${page.chain.length ? ' → ' : ''}${page.status || page.error} (a sitemap page must answer 200 itself)`);
    }
    if (VERBOSE) process.stdout.write(`  ${page.status} ${url}\n`);
    if (!page.doc) return;
    for (const link of page.doc.links) {
        if (link.error) {
            problems.broken.push({ page: url, href: '', reason: link.error, element: link.tag });
            continue;
        }
        for (const [re, why] of LEGACY) if (re.test(link.href)) problems.legacy.push({ page: url, href: link.href, reason: why, element: `${link.tag} "${link.text}"` });
        for (const [re, why] of REMOVED) if (re.test(link.href)) problems.removed.push({ page: url, href: link.href, reason: why, element: `${link.tag} "${link.text}"` });
        occurrences.push({ page: url, ...link, resolved: classify(link.href, url) });
    }
});

const internal = occurrences.filter((o) => o.resolved.kind === 'internal');
const targets = [...new Set(internal.map((o) => { const u = new URL(o.resolved.url); u.hash = ''; return u.href; }))];
await pool(targets, (u) => load(u));

for (const o of occurrences) {
    const r = o.resolved;
    if (r.kind === 'broken') {
        problems.broken.push({ page: o.page, href: o.href, reason: r.reason, element: `${o.tag} "${o.text}"` });
        continue;
    }
    if (r.kind === 'external') {
        const e = external.get(r.url) || { count: 0, pages: new Set() };
        e.count += 1;
        e.pages.add(o.page);
        external.set(r.url, e);
        continue;
    }
    const url = new URL(r.url);
    const fragment = url.hash.slice(1);
    url.hash = '';
    const samePage = o.href.trim().startsWith('#');
    const target = await load(samePage ? o.page : url.href);
    const element = `${o.tag} "${o.text}"`;
    // An endpoint named in a JSON payload or a form action may refuse GET (405): it exists.
    if (!samePage && target.status === 405) continue;
    if (!samePage && target.status !== 200) {
        problems.broken.push({ page: o.page, href: o.href, reason: `${target.status || target.error}${target.chain.length ? ` after ${target.chain.map((c) => `${c.status} → ${c.to}`).join(', ')}` : ''}`, element });
        continue;
    }
    if (!samePage && target.chain.length) problems.redirects.push(`${o.href} → ${target.final} (${target.chain.map((c) => c.status).join(', ')}) on ${o.page} ${element}`);
    if (fragment && !fragment.startsWith(':~:') && !fragment.startsWith('navigator=') && fragment !== 'top' && target.doc && !target.doc.ids.has(decodeURIComponent(fragment))) {
        problems.anchors.push({ page: o.page, href: o.href, reason: `no element with id="${fragment}" on the target`, element });
    }
}

// ------------------------------------------------------------------------------------------- report

const uniq = (list) => [...new Map(list.map((p) => [`${p.page}|${p.href}|${p.reason}`, p])).values()];
for (const key of ['broken', 'anchors', 'legacy', 'removed']) problems[key] = uniq(problems[key]);
problems.redirects = [...new Set(problems.redirects)];
const short = (href) => { try { const u = new URL(href, BASE); return u.origin === BASE_ORIGIN ? u.pathname + u.search + u.hash : href; } catch { return href; } };
const rows = (list) => list.map((p) => `${p.reason}  ${p.href ? short(p.href) : '(none)'}  ← ${short(p.page)}  ${p.element}`);
const print = (title, list) => {
    if (!list.length) return;
    process.stdout.write(`\n${title} (${list.length})\n`);
    for (const row of list) process.stdout.write(`  ${row}\n`);
};
print('Sitemap problems', problems.sitemap);
print('Sitemap pages that did not answer 200', problems.pages);
print('Broken internal links', rows(problems.broken));
print('Broken anchors', rows(problems.anchors));
print('Links to old .html / WordPress addresses', rows(problems.legacy));
print('Links to the removed Legal Translation service', rows(problems.removed));
print('Redirecting internal links (deliberate redirects are allowed; link the final address where possible)', problems.redirects.map(short));
if (VERBOSE) print('External links (listed, not fetched)', [...external.entries()].sort().map(([u, e]) => `${u}  (${e.count}× on ${e.pages.size} pages)`));

const summary = {
    base: BASE,
    seconds: Math.round((Date.now() - started) / 100) / 10,
    sitemapUrls: locs.length,
    pagesCrawled: pages.length,
    internalLinks: internal.length,
    uniqueTargets: targets.length,
    externalLinks: external.size,
    sitemapProblems: problems.sitemap.length,
    pageProblems: problems.pages.length,
    broken: problems.broken.length,
    brokenAnchors: problems.anchors.length,
    legacy: problems.legacy.length,
    legalTranslation: problems.removed.length,
    redirects: problems.redirects.length,
};
process.stdout.write(`\n${JSON.stringify(summary)}\n`);
if (JSON_OUT) writeFileSync(JSON_OUT, JSON.stringify({ summary, ...problems, external: [...external.keys()] }, null, 2));
const failed = ['sitemap', 'pages', 'broken', 'anchors', 'legacy', 'removed'].some((k) => problems[k].length > 0);
process.stdout.write(failed ? 'FAIL (see above).\n' : 'OK: every internal link and anchor resolves.\n');
process.exit(failed ? 1 : 0);
