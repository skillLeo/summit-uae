#!/usr/bin/env node
/**
 * SEO head check over the sitemap (client PDF items 21, 22, 25, 26, 27).
 *
 *   QA_BASE_URL=http://127.0.0.1:8045 node scripts/qa/seo.mjs [--json=report.json] [--verbose]
 *
 * For every <loc> of <base>/sitemap.xml (fetched on the base by path, server-rendered HTML):
 *   title, description  exactly one, not empty; unique across all pages; in the page language (Arabic pages
 *                       carry Arabic letters and no Cyrillic, Russian pages Cyrillic and no Arabic, English pages
 *                       neither); the brand is "Summit Legal Consultancy" (never "Summit Legal Consultants");
 *                       a title over 65 or a description over 165 characters is a warning (search engines cut
 *                       them; the approved copy is never shortened here)
 *   robots              no noindex (QA_EXPECT_NOINDEX=1 inverts this for the test site)
 *   canonical           exactly one, = the sitemap <loc> (production host, folder URL, no query)
 *   hreflang            en, ar, ru, x-default, each = the equivalent page's <loc>, reciprocal
 *   Open Graph          og:title, og:description, og:url, og:image (absolute https, with width and height),
 *                       og:type, og:locale (+ alternates), og:site_name, twitter:card summary_large_image;
 *                       og:image answers 200 with an image type
 *   JSON-LD             one block, valid JSON, a @graph with the organisation, the website and the page; every
 *                       FAQPage question, Article headline and BreadcrumbList name appears in the page's visible
 *                       text; no address, rating, review or opening-hours property anywhere
 *   headings            exactly one <h1>
 *
 * Exit 0: no failure (warnings allowed). 1: a failure. 2: the site or its sitemap cannot be read.
 */
import { writeFileSync } from 'node:fs';

const BASE = (process.env.QA_BASE_URL || 'http://127.0.0.1:8045').replace(/\/+$/, '');
const BASE_ORIGIN = new URL(BASE).origin;
const CANONICAL_ORIGIN = new URL(process.env.SUMMIT_CANONICAL_HOST || 'https://www.summitlegaluae.com').origin;
const EXPECT_NOINDEX = process.env.QA_EXPECT_NOINDEX === '1';
const CONCURRENCY = Number(process.env.QA_SEO_CONCURRENCY || 4);
const args = process.argv.slice(2);
const option = (name) => args.find((a) => a.startsWith(`--${name}=`))?.split('=').slice(1).join('=');
const JSON_OUT = option('json');
const VERBOSE = args.includes('--verbose');
const MAX_TITLE = 65;
const MAX_DESCRIPTION = 165;
const FORBIDDEN_PROPS = ['address', 'aggregateRating', 'review', 'reviews', 'openingHours', 'openingHoursSpecification', 'priceRange', 'foundingDate', 'award', 'geo', 'hasMap'];

const ENTITIES = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ' };
const decode = (t) => String(t).replace(/&(#x[0-9a-f]+|#\d+|[a-z]+);/gi, (all, c) => {
    if (c[0] === '#') {
        const n = c[1].toLowerCase() === 'x' ? parseInt(c.slice(2), 16) : parseInt(c.slice(1), 10);
        return Number.isFinite(n) ? String.fromCodePoint(n) : all;
    }
    return ENTITIES[c.toLowerCase()] ?? all;
});
const norm = (t) => decode(String(t)).replace(/[⁦-⁩‎‏]/g, '').replace(/\s+/g, ' ').trim();
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

async function get(url, accept = 'text/html') {
    const res = await fetch(url, { redirect: 'manual', headers: { accept, 'user-agent': 'summit-seo/1.0' }, signal: AbortSignal.timeout(120_000) });
    const type = res.headers.get('content-type') || '';
    const body = /text|xml|json/.test(type) ? await res.text() : (await res.body?.cancel(), null);
    return { status: res.status, type, body, headers: res.headers };
}

async function pool(items, worker) {
    const queue = [...items];
    await Promise.all(Array.from({ length: Math.min(CONCURRENCY, queue.length) }, async () => {
        while (queue.length) await worker(queue.shift());
    }));
}

function head(html) {
    const h = html.slice(0, html.search(/<\/head>/i) + 7);
    const metas = [...h.matchAll(/<meta\b([^>]*)>/gi)].map((m) => attributes(m[1]));
    const links = [...h.matchAll(/<link\b([^>]*)>/gi)].map((m) => attributes(m[1]));
    const titles = [...h.matchAll(/<title>([\s\S]*?)<\/title>/gi)].map((m) => norm(m[1]));
    const ld = [...html.matchAll(/<script\b([^>]*)>([\s\S]*?)<\/script>/gi)]
        .filter(([, a]) => (attributes(a).type || '').toLowerCase() === 'application/ld+json')
        .map(([, a, body]) => ({ nonce: attributes(a).nonce, body }));
    const meta = (key, value) => metas.filter((m) => m[key] === value).map((m) => m.content ?? '');
    return { titles, metas, links, ld, meta };
}

/** The page's text: the body without scripts, styles and templates (visually hidden text counts: it is read). */
function visibleText(html) {
    const body = html.slice(html.search(/<body\b/i));
    return norm(body
        .replace(/<!--[\s\S]*?-->/g, ' ')
        .replace(/<(script|style|template|noscript)\b[\s\S]*?<\/\1>/gi, ' ')
        // Inline isolation (Bidi::isolate: <bdi>, <span dir>) sits inside words ("وTxID"): no space for it.
        .replace(/<\/?(?:bdi|bdo)\b[^>]*>/gi, '')
        .replace(/<[^>]+>/g, ' '));
}

function walk(value, fn, path = []) {
    if (Array.isArray(value)) value.forEach((v, i) => walk(v, fn, [...path, i]));
    else if (value && typeof value === 'object') for (const [k, v] of Object.entries(value)) { fn(k, v, path); walk(v, fn, [...path, k]); }
}

const script = (text) => ({ arabic: /\p{Script=Arabic}/u.test(text), cyrillic: /\p{Script=Cyrillic}/u.test(text) });
function languageProblem(locale, text) {
    const s = script(text);
    if (locale === 'ar' && (!s.arabic || s.cyrillic)) return 'not Arabic';
    if (locale === 'ru' && (!s.cyrillic || s.arabic)) return 'not Russian';
    if (locale === 'en' && (s.arabic || s.cyrillic)) return 'not English';
    return null;
}

// ------------------------------------------------------------------------------------------- run

const started = Date.now();
const sitemap = await get(`${BASE}/sitemap.xml`, 'application/xml').catch((e) => ({ status: 0, error: e.message }));
if (sitemap.status !== 200 || !sitemap.body) {
    console.error(`seo: cannot read ${BASE}/sitemap.xml (${sitemap.status || sitemap.error}).`);
    process.exit(2);
}
const entries = [...sitemap.body.matchAll(/<url>([\s\S]*?)<\/url>/g)].map((m) => ({
    loc: decode((m[1].match(/<loc>([^<]+)<\/loc>/) || [])[1] || '').trim(),
    alternates: Object.fromEntries([...m[1].matchAll(/hreflang="([^"]+)" href="([^"]+)"/g)].map((a) => [a[1], decode(a[2])])),
}));

const failures = [];
const warnings = [];
const fail = (page, msg) => failures.push(`${page}: ${msg}`);
const warn = (page, msg) => warnings.push(`${page}: ${msg}`);
const results = [];
const images = new Set();

process.stdout.write(`SEO head check · ${BASE} · ${entries.length} sitemap URLs\n`);
await pool(entries, async (entry) => {
    const path = new URL(entry.loc).pathname;
    const locale = path.split('/')[1];
    const res = await get(`${BASE_ORIGIN}${path}`).catch((e) => ({ status: 0, error: e.message }));
    if (res.status !== 200 || !res.body) {
        fail(path, `answered ${res.status || res.error}`);
        return;
    }
    const html = res.body;
    const h = head(html);
    const row = { path, locale };
    results.push(row);

    // <html lang>
    const htmlTag = attributes((html.match(/<html\b([^>]*)>/i) || [])[1] || '');
    if (htmlTag.lang !== locale) fail(path, `<html lang="${htmlTag.lang}">, expected ${locale}`);
    if ((locale === 'ar') !== (htmlTag.dir === 'rtl')) fail(path, `<html dir="${htmlTag.dir}">`);

    // title + description
    if (h.titles.length !== 1 || !h.titles[0]) fail(path, `${h.titles.length} <title> elements`);
    const descriptions = h.meta('name', 'description');
    if (descriptions.length !== 1 || !norm(descriptions[0])) fail(path, `${descriptions.length} meta descriptions`);
    row.title = h.titles[0] || '';
    row.description = norm(descriptions[0] || '');
    for (const [label, text] of [['title', row.title], ['description', row.description]]) {
        const problem = text && languageProblem(locale, text.replace(/Summit Legal Consultancy/g, ''));
        if (problem) fail(path, `${label} is ${problem}: "${text}"`);
        if (/Summit Legal Consultants\b/i.test(text)) fail(path, `${label} uses "Summit Legal Consultants"`);
    }
    if (row.title.length > MAX_TITLE) warn(path, `title ${row.title.length} characters (> ${MAX_TITLE})`);
    if (row.description.length > MAX_DESCRIPTION) warn(path, `description ${row.description.length} characters (> ${MAX_DESCRIPTION})`);

    // robots
    const robots = h.meta('name', 'robots').join(',');
    const header = res.headers.get('x-robots-tag') || '';
    const noindex = /noindex/i.test(`${robots},${header}`);
    if (noindex !== EXPECT_NOINDEX) fail(path, EXPECT_NOINDEX ? 'indexable on a site that must be noindex' : `noindex (meta "${robots}", header "${header}")`);

    // canonical
    const canonicals = h.links.filter((l) => l.rel === 'canonical').map((l) => l.href);
    if (canonicals.length !== 1 || canonicals[0] !== entry.loc) fail(path, `canonical ${JSON.stringify(canonicals)}, expected ${entry.loc}`);

    // hreflang = the sitemap's alternates
    const alternates = Object.fromEntries(h.links.filter((l) => l.rel === 'alternate' && l.hreflang).map((l) => [l.hreflang, l.href]));
    if (JSON.stringify(alternates) !== JSON.stringify(entry.alternates)) fail(path, `hreflang ${JSON.stringify(alternates)} ≠ sitemap ${JSON.stringify(entry.alternates)}`);
    for (const lang of ['en', 'ar', 'ru', 'x-default']) if (!alternates[lang]) warn(path, `no hreflang ${lang}`);

    // Open Graph
    const og = (p) => h.meta('property', p);
    for (const p of ['og:title', 'og:description', 'og:url', 'og:image', 'og:image:width', 'og:image:height', 'og:type', 'og:locale', 'og:site_name']) {
        if (og(p).length !== 1 || !norm(og(p)[0])) fail(path, `${og(p).length} ${p}`);
    }
    if (og('og:title')[0] && norm(og('og:title')[0]) !== row.title) fail(path, 'og:title differs from the title');
    if (og('og:description')[0] && norm(og('og:description')[0]) !== row.description) fail(path, 'og:description differs from the description');
    const image = og('og:image')[0] || '';
    if (!/^https:\/\//.test(image) && !image.startsWith(BASE_ORIGIN)) fail(path, `og:image is not an absolute https URL: ${image}`);
    if (image) images.add(image);
    const ogUrl = og('og:url')[0] || '';
    if (ogUrl && new URL(ogUrl).pathname !== path) fail(path, `og:url ${ogUrl} is not this page`);
    if (h.meta('name', 'twitter:card')[0] !== 'summary_large_image') fail(path, 'twitter:card is not summary_large_image');
    const ogLocale = og('og:locale')[0] || '';
    if (!ogLocale.startsWith(`${locale}_`)) fail(path, `og:locale ${ogLocale}`);

    // JSON-LD
    if (h.ld.length !== 1) fail(path, `${h.ld.length} JSON-LD blocks`);
    const text = visibleText(html);
    for (const block of h.ld) {
        if (!block.nonce) fail(path, 'JSON-LD without the CSP nonce');
        let data;
        try {
            data = JSON.parse(block.body);
        } catch (e) {
            fail(path, `JSON-LD does not parse: ${e.message}`);
            continue;
        }
        const graph = data['@graph'] || [];
        const types = graph.flatMap((n) => [].concat(n['@type'] || []));
        row.schema = [...new Set(types)].join(' ');
        for (const t of ['WebSite']) if (!types.includes(t)) fail(path, `JSON-LD has no ${t}`);
        if (!graph.some((n) => n['@id'] === `${entry.loc}#webpage`)) fail(path, 'JSON-LD has no #webpage node for this canonical');
        walk(data, (key, value) => {
            if (FORBIDDEN_PROPS.includes(key)) fail(path, `JSON-LD has "${key}"`);
            if (key === '@type' && [].concat(value).some((t) => /^(?:Review|AggregateRating|Rating|PostalAddress|GeoCoordinates|OpeningHoursSpecification|LocalBusiness)$/.test(t))) fail(path, `JSON-LD type ${value}`);
            if (typeof value === 'string' && /Summit Legal Consultants\b/i.test(value)) fail(path, 'JSON-LD uses "Summit Legal Consultants"');
            if (typeof value === 'string' && /practice-areas\/legal-translation\//i.test(value)) fail(path, 'JSON-LD names the removed Legal Translation service');
        });
        for (const node of graph) {
            const nodeTypes = [].concat(node['@type'] || []);
            if (nodeTypes.includes('FAQPage')) {
                for (const q of node.mainEntity || []) {
                    if (!text.includes(norm(q.name))) fail(path, `FAQ question not visible: "${q.name}"`);
                    const firstLine = norm(String(q.acceptedAnswer?.text || '').split('\n')[0]);
                    if (firstLine && !text.includes(firstLine)) fail(path, `FAQ answer not visible: "${firstLine.slice(0, 60)}…"`);
                }
            }
            if (nodeTypes.includes('Article') && !text.includes(norm(node.headline))) fail(path, `Article headline not visible: "${node.headline}"`);
            if (nodeTypes.includes('BreadcrumbList')) {
                for (const item of node.itemListElement || []) if (!text.includes(norm(item.name))) fail(path, `breadcrumb not visible: "${item.name}"`);
            }
            if (nodeTypes.includes('LegalService') && node.telephone && node.telephone !== '+971524666191') fail(path, `telephone ${node.telephone}`);
        }
    }

    // headings
    const h1 = (html.match(/<h1[\s>]/gi) || []).length;
    if (h1 !== 1) fail(path, `${h1} <h1> elements`);
    if (VERBOSE) process.stdout.write(`  ${path}  ${row.schema || ''}\n`);
});

// uniqueness
for (const key of ['title', 'description']) {
    const by = new Map();
    for (const r of results) if (r[key]) by.set(r[key], [...(by.get(r[key]) || []), r.path]);
    for (const [value, paths] of by) if (paths.length > 1) failures.push(`the same ${key} on ${paths.join(', ')}: "${value}"`);
}
// og:image reachable (on the base when it is on the production host)
for (const image of images) {
    const u = new URL(image);
    const res = await fetch(u.origin === CANONICAL_ORIGIN ? `${BASE_ORIGIN}${u.pathname}` : image, { method: 'GET' }).catch(() => null);
    const type = res?.headers.get('content-type') || '';
    if (!res || res.status !== 200 || !/^image\//.test(type)) failures.push(`og:image ${image} answered ${res?.status} ${type}`);
    await res?.body?.cancel();
}

const print = (title, list) => {
    if (!list.length) return;
    process.stdout.write(`\n${title} (${list.length})\n`);
    for (const row of list.sort()) process.stdout.write(`  ${row}\n`);
};
print('Failures', [...new Set(failures)]);
print('Warnings', [...new Set(warnings)]);
const summary = {
    base: BASE,
    seconds: Math.round((Date.now() - started) / 100) / 10,
    pages: results.length,
    uniqueTitles: new Set(results.map((r) => r.title)).size,
    uniqueDescriptions: new Set(results.map((r) => r.description)).size,
    failures: new Set(failures).size,
    warnings: new Set(warnings).size,
};
process.stdout.write(`\n${JSON.stringify(summary)}\n`);
if (JSON_OUT) writeFileSync(JSON_OUT, JSON.stringify({ summary, failures, warnings, pages: results }, null, 2));
process.stdout.write(failures.length ? 'FAIL (see above).\n' : 'OK.\n');
process.exit(failures.length ? 1 : 0);
