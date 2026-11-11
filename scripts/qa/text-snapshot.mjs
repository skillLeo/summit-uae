#!/usr/bin/env node
/**
 * Text snapshots of every public page (docs/briefs/design-2.1-and-admin-cms.md §2 item 5). Phase 3 runs it
 * again before and after Design 2 switches to the CMS (§8.8): the text must stay identical.
 *
 * For every public route (routes/web.php + config/summit.php: the language chooser "/", the home, the
 * styleguide, the offline page, P02-P08, L01, L02, the eleven services S01-S11 and a 404 URL; not the web
 * manifest, the sitemap, the enquiry POST or the admin panel), every locale (en, ar, ru) and both designs,
 * the page is loaded in Google Chrome and the normalised visible text of its header, main and footer is
 * saved to
 *
 *     tests/Snapshots/text/{design}/{locale}/{route}.txt
 *
 *   node scripts/qa/text-snapshot.mjs            write the snapshots (npm run qa:text-snapshot)
 *   node scripts/qa/text-snapshot.mjs --check    compare the live site with them (npm run qa:text-snapshot:check)
 *
 * Options
 *   --check                capture again and compare with the stored files: a unified diff per changed file,
 *                          the missing files and (without filters) the stale ones; writes nothing; exit 1 on
 *                          any difference, unexpected status or failed capture
 *   --base=<url>           site under test, default http://127.0.0.1:8042 (a server of this working tree)
 *   --design=1|2|both      default both (also "1,2")
 *   --locales=<list>       en, ar, ru and "root" (the chooser), default all
 *   --routes=<list>        route slugs (below), "*" is a wildcard: --routes=home,s0*,404
 *   --dir=<folder>         snapshot folder, default tests/Snapshots/text (e.g. a copy to test --check)
 *   --concurrency=<n>      parallel captures, default 4
 *   --verbose              --check: also list the pages that are the same
 *
 * Without --check the selected snapshots are written (a file is rewritten only when its text changed), and
 * so is manifest.json; without filters, *.txt snapshots of routes that no longer exist are deleted. A page
 * that fails to load, loads without its stylesheets or answers 5xx is tried again (3 attempts in all); a page
 * that still fails, serves the wrong design or answers with an unexpected status is reported and never
 * written. Exit 0 = clean, 1 = a difference (--check), an unexpected status or a failed capture, 2 = bad
 * arguments or the site (or its stylesheets) cannot be reached.
 *
 * Layout and route slugs
 *   {design} is 1 or 2. Route slugs: home, p02-practice-areas, p03-resources, p04-case-studies, p05-faq,
 *   p06-about, p07-contact, p08-legal-disclaimer, l01-privacy-policy, l02-terms-and-conditions,
 *   s01-debt-recovery-cheque-cases ... s11-business-services (code + slug from config/summit.php),
 *   calculators (/{locale}/calculators/, Design 2 only: brief 2.1 §5.1 moved the calculators to their own page,
 *   which renders while pending copy is shown; Design 1 has none and redirects it to its home page, so the route
 *   is not captured for Design 1), styleguide, offline and 404 (/{locale}/no-such-page-qa/, expected status 404;
 *   neither design echoes the address). The chooser "/" has no locale: it is stored once per design as {design}/root/chooser.txt
 *   ("root" is a pseudo-locale for the site root, also accepted by --locales). manifest.json beside the design
 *   folders records the capture rules, the routes, the file counts and a hash of the text sources (sha256
 *   of database/content/*.json and of lang/**\/*.php), nothing volatile (no time, host or commit); --check
 *   reports, without failing, when those sources changed since the snapshots were written.
 *
 * Capture (deterministic)
 *   Google Chrome (channel 'chrome'), headless, one fresh context per page with the cookies
 *   summit_design=<1|2> and summit_qa=1 (no floating design switcher). JavaScript disabled: the snapshot is
 *   the server-rendered text, which is what the CMS will produce and what every visitor receives before any
 *   script runs (no live office-hours status, no carousel or tab state). prefers-reduced-motion: reduce,
 *   light colour scheme, time zone Asia/Dubai, browser locale of the page language (en-GB, ar-AE, ru-RU;
 *   en-GB for the chooser). Viewport 1440 x 900 at device scale 1: responsive CSS decides which navigation
 *   items, sample-document variants and panels are rendered, so the width is fixed (the desktop reference of
 *   the screenshot matrix). Images, fonts, media and scripts are not fetched (they cannot change the text
 *   with JavaScript off) and neither is anything on another host. Every stylesheet must load (a Design 2
 *   page without its Vite dev server would show its hidden panels) and the html element must carry the
 *   requested design (data-design="2" for Design 2, none for Design 1).
 *
 * What counts as visible text (decisions)
 *   - innerText of three landmarks: the first <header> and the first <footer> that are not inside article,
 *     aside, main, nav or section (the banner and contentinfo; section heads and card footers are part of
 *     main) and the first <main>. Parts are written in document order; "(no header element on this page)" /
 *     "(no footer element on this page)" when there is none (the chooser has only a main), "(no visible
 *     text)" when the element renders no text, "(first of N)" in the marker when there are several.
 *   - The browser's rendering rule decides: an element counts when it is rendered (not display: none, not
 *     inside a closed or content-visibility: hidden subtree) and its text when its computed visibility is
 *     visible. Opacity, clipping, overlap and scroll position are paint, not text, and exclude nothing.
 *     So visually hidden text (.sr-only, e.g. "(opens in a new tab)") is included; visibility: hidden
 *     sizers, closed menus and sheets are not; <select> options are (innerText lists them).
 *   - Sample-document fine print is aria-hidden illustration text. It counts as page text exactly when its
 *     document is rendered at 1440 px (the hero's document stack, the 404's contract, the Anatomy document
 *     shown first) and not when it is display: none at that width (the phone variants, the other Anatomy
 *     documents). The watermark is CSS generated content and, like every ::before / ::after text and all
 *     attribute text (alt, aria-label, title, placeholder, input values), is never part of innerText.
 *   - Two adjustments before reading: CSS text-transform is switched off (a constructed stylesheet, inline
 *     !important as a fallback; on ::first-letter / ::first-line only where the page's own CSS transforms
 *     them, because a global *::first-letter rule makes Chrome's innerText return a leading Arabic-Indic
 *     numeral and its period in visual order), so the snapshot records the characters as authored, not the case CSS paints
 *     (an uppercase label is stored as written in its source; a CSS-only change never changes a snapshot); and every
 *     <details> is opened (its name attribute removed first so exclusive groups stay open), because the
 *     accordion answers are complete in the server HTML (brief §5.2) and the parity test must cover them.
 *   - Everything outside the three landmarks is left out: the skip link, Design 1's language-suggestion
 *     bars and bottom bar, Design 2's tab bar, sheets, command palette, toast region and announcer.
 *
 * Normalisation (per part)
 *   Unicode NFC; CR LF, CR, U+2028 and U+2029 become line breaks; tabs and every Unicode space separator
 *   (\p{Zs}: no-break, narrow no-break, thin space ...) become a space; runs of spaces collapse to one;
 *   lines are trimmed; empty lines are dropped. Quotes, apostrophes, dashes, ellipses, digits (Western or
 *   Eastern Arabic) and letter case are kept exactly: brief §5.3 folds curly and straight quotes only in the
 *   DOCX conformance check, where the site is compared with Word files; here the site is compared with
 *   itself, and a changed quote is a change to approved copy (docs/d2/engineering.md §0.2: never re-punctuate).
 *
 * Masks (volatile values; the run prints how often each one applied)
 *   {{YEAR}}   the year after "©": both footers print the approved "© 2026 …" line with the year taken
 *              from the clock (Asia/Dubai), so it would change on 1 January
 *   {{CSRF}}   the page's CSRF token, {{NONCE}} its CSP nonce, {{UUID}} any UUID (the form's idempotency key):
 *              none of them is visible text today; they are masked only if one ever appears in the text
 *   No other text depends on the clock: the live office-hours status needs JavaScript, and the dates in the
 *   approved copy ("Last updated: 23 October 2026", "Decree-Law No. 25 of 2025") and on the sample documents
 *   ("00/00/2026") are content, so they are not masked (a blanket "today" mask would hide such a date on the
 *   day it equals the run date). The run warns when today's date appears in a capture.
 *
 * File format
 *   # text-snapshot: design 2, locale en, route home
 *   # GET /en/ -> 200; html lang=en dir=ltr data-design=2
 *   === header ===
 *   …one line per line of visible text…
 *   === main ===
 *   …
 *   === footer ===
 *   …
 */
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, readdirSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { dirname, isAbsolute, join, relative, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { chromium } from '@playwright/test';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const DEFAULT_BASE = 'http://127.0.0.1:8042';
const DEFAULT_DIR = join(ROOT, 'tests/Snapshots/text');
const ROOT_LOCALE = 'root';
const NOT_FOUND_PATH = 'no-such-page-qa/';
const VIEWPORT = { width: 1440, height: 900 };
const TIMEZONE = 'Asia/Dubai';
const BROWSER_LOCALE = { en: 'en-GB', ar: 'ar-AE', ru: 'ru-RU', [ROOT_LOCALE]: 'en-GB' };
const BLOCKED_TYPES = new Set(['image', 'font', 'media', 'script']);
const ATTEMPTS = 3;
const NAV_TIMEOUT = 90_000;
const CONTEXT_LINES = 3;
const MAX_EDIT_DISTANCE = 1500;
const MASKS = ['{{YEAR}}', '{{CSRF}}', '{{NONCE}}', '{{UUID}}'];
const YEAR_RE = /©(\s*)(?:(?:19|20)[0-9]{2}|[١٢][٠-٩]{3})/gu;
const UUID_RE = /\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/giu;

// ------------------------------------------------------------------------------------------ routes

/** Locales, pages and services from config/summit.php (read by PHP; env() returns each default). */
export function readRegistry() {
    const php = [
        'function env($key, $default = null) { return $default; }',
        '$c = require $argv[1];',
        'echo json_encode(["locales" => array_keys($c["locales"]), "pages" => array_map(fn ($p) => $p["path"], $c["pages"]), "services" => $c["services"]]);',
    ].join(' ');
    return JSON.parse(execFileSync('php', ['-r', php, join(ROOT, 'config/summit.php')], { encoding: 'utf8' }));
}

/**
 * Every public HTML route, per locale, with its stable slug and expected status (plus the chooser). A route with
 * `designs` exists only in those designs (the calculators page: Design 2).
 */
export function buildRoutes(registry) {
    const routes = [];
    for (const locale of registry.locales) {
        for (const [code, path] of Object.entries(registry.pages)) {
            const slug = path === '' ? 'home' : `${code.toLowerCase()}-${path.replace(/\/+$/, '').replaceAll('/', '-')}`;
            routes.push({ locale, slug, path: `/${locale}/${path}`, expect: 200 });
        }
        for (const [code, service] of Object.entries(registry.services)) {
            routes.push({ locale, slug: `${code.toLowerCase()}-${service}`, path: `/${locale}/practice-areas/${service}/`, expect: 200 });
        }
        routes.push({ locale, slug: 'calculators', path: `/${locale}/calculators/`, expect: 200, designs: ['2'] });
        routes.push({ locale, slug: 'styleguide', path: `/${locale}/styleguide/`, expect: 200 });
        routes.push({ locale, slug: 'offline', path: `/${locale}/offline/`, expect: 200 });
        routes.push({ locale, slug: '404', path: `/${locale}/${NOT_FOUND_PATH}`, expect: 404 });
    }
    routes.push({ locale: ROOT_LOCALE, slug: 'chooser', path: '/', expect: 200 });
    return routes;
}

// ------------------------------------------------------------------------------------------ text

/** NFC, one space for any run of tabs and Unicode spaces, trimmed lines, no empty lines. */
export function normaliseText(text) {
    return String(text)
        .normalize('NFC')
        .split(/\r\n|[\n\r\p{Zl}\p{Zp}]/u)
        .map((line) => line.replace(/[\t\p{Zs}]+/gu, ' ').trim())
        .filter((line) => line !== '');
}

/** Replaces the volatile values in each line and counts every replacement in `counts`. */
function applyMasks(lines, volatile, counts) {
    const exact = [
        ...volatile.csrf.map((value) => [value, '{{CSRF}}']),
        ...volatile.nonce.map((value) => [value, '{{NONCE}}']),
        ...volatile.keys.map((value) => [value, '{{UUID}}']),
    ];
    return lines.map((line) => {
        let out = line;
        for (const [value, token] of exact) {
            if (out.includes(value)) {
                counts[token] += out.split(value).length - 1;
                out = out.split(value).join(token);
            }
        }
        out = out.replace(UUID_RE, () => {
            counts['{{UUID}}'] += 1;
            return '{{UUID}}';
        });
        return out.replace(YEAR_RE, (match, space) => {
            counts['{{YEAR}}'] += 1;
            return `©${space}{{YEAR}}`;
        });
    });
}

/** Today's date (Asia/Dubai) as numbers and as the en/ar/ru browsers write it: a warning, never a mask. */
function todayStrings(now = new Date()) {
    const part = Object.fromEntries(
        new Intl.DateTimeFormat('en-GB', { timeZone: TIMEZONE, year: 'numeric', month: '2-digit', day: '2-digit' })
            .formatToParts(now)
            .map((p) => [p.type, p.value]),
    );
    const day = String(Number(part.day));
    const month = String(Number(part.month));
    const found = new Set([
        `${part.day}/${part.month}/${part.year}`,
        `${day}/${month}/${part.year}`,
        `${part.year}-${part.month}-${part.day}`,
        `${part.day}.${part.month}.${part.year}`,
        `${part.month}/${part.day}/${part.year}`,
    ]);
    for (const locale of ['en-GB', 'en-US', 'ar', 'ar-AE', 'ru-RU']) {
        for (const dateStyle of ['medium', 'long']) found.add(new Intl.DateTimeFormat(locale, { timeZone: TIMEZONE, dateStyle }).format(now));
    }
    const eastern = (s) => s.replace(/[0-9]/g, (c) => '٠١٢٣٤٥٦٧٨٩'[Number(c)]);
    for (const s of [...found]) found.add(eastern(s));
    return [...found];
}

/** The snapshot file: two header lines, then the three parts. */
function renderSnapshot(target, capture, parts) {
    const lines = [
        `# text-snapshot: design ${target.design}, locale ${target.locale}, route ${target.slug}`,
        `# GET ${target.path} -> ${capture.chain}; html lang=${capture.lang || '-'} dir=${capture.dir || '-'} data-design=${capture.served ?? '-'}`,
    ];
    for (const part of parts) {
        lines.push(part.count > 1 ? `=== ${part.name} (first of ${part.count}) ===` : `=== ${part.name} ===`);
        if (part.lines === null) lines.push(`(no ${part.name} element on this page)`);
        else if (part.lines.length === 0) lines.push('(no visible text)');
        else lines.push(...part.lines);
    }
    return `${lines.join('\n')}\n`;
}

// ------------------------------------------------------------------------------------------ browser

/**
 * Runs in the page (JavaScript is off for the page's own scripts; Playwright still evaluates). Switches
 * text-transform off, opens every <details>, then reads innerText of the landmark header, main and footer.
 */
function pageCapture() {
    // ::first-letter and ::first-line are switched off only on the elements whose own CSS transforms them
    // (today the Russian language chips of the team profiles, css/d2/components/profile.css). A global
    // *::first-letter rule would give every block a first-letter box, and Chrome's innerText then returns a
    // leading Arabic-Indic numeral and its period in visual order (".١" for the page's "١.": the step titles
    // of the Arabic About and Contact pages).
    const sheet = new CSSStyleSheet();
    sheet.replaceSync([
        '*, *::before, *::after { text-transform: none !important; }',
        '[data-text-snapshot-first-letter]::first-letter { text-transform: none !important; }',
        '[data-text-snapshot-first-line]::first-line { text-transform: none !important; }',
    ].join('\n'));
    document.adoptedStyleSheets = [...document.adoptedStyleSheets, sheet];
    let overrides = 0;
    for (const el of document.querySelectorAll('body, body *')) {
        if (getComputedStyle(el).textTransform !== 'none') {
            el.style.setProperty('text-transform', 'none', 'important');
            overrides += 1;
        }
        if (getComputedStyle(el, '::first-letter').textTransform !== 'none') el.setAttribute('data-text-snapshot-first-letter', '');
        if (getComputedStyle(el, '::first-line').textTransform !== 'none') el.setAttribute('data-text-snapshot-first-line', '');
    }

    const details = [...document.querySelectorAll('details')];
    for (const item of details) item.removeAttribute('name');
    let opened = 0;
    for (const item of details) {
        if (!item.open) {
            item.open = true;
            opened += 1;
        }
    }

    const outside = (el) => !el.parentElement || !el.parentElement.closest('article, aside, main, nav, section');
    const found = {
        header: [...document.getElementsByTagName('header')].filter(outside),
        main: [...document.getElementsByTagName('main')],
        footer: [...document.getElementsByTagName('footer')].filter(outside),
    };
    const present = Object.entries(found)
        .filter(([, list]) => list.length > 0)
        .map(([name, list]) => ({ name, el: list[0], count: list.length }))
        .sort((a, b) => (a.el.compareDocumentPosition(b.el) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1));
    const order = present.map((p) => p.name);
    if (!found.header.length) order.unshift('header');
    if (!found.main.length) order.splice(order.indexOf('header') + 1, 0, 'main');
    if (!found.footer.length) order.push('footer');
    const parts = order.map((name) => {
        const hit = present.find((p) => p.name === name);
        return hit ? { name, count: hit.count, text: hit.el.innerText } : { name, count: 0, text: null };
    });

    const uniq = (list) => [...new Set(list.filter((v) => typeof v === 'string' && v.length >= 8))];
    const html = document.documentElement;
    return {
        parts,
        opened,
        overrides,
        lang: html.getAttribute('lang'),
        dir: html.getAttribute('dir'),
        served: html.getAttribute('data-design'),
        unloaded: [...document.querySelectorAll('link[rel~="stylesheet"]')]
            .filter((link) => !/\balternate\b/i.test(link.rel) && !link.sheet)
            .map((link) => link.href),
        volatile: {
            csrf: uniq([
                document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                ...[...document.querySelectorAll('input[name="_token"]')].map((input) => input.value),
            ]),
            nonce: uniq([...document.querySelectorAll('[nonce]')].map((el) => el.nonce)),
            keys: uniq(
                [...document.querySelectorAll('input[type="hidden"]')]
                    .map((input) => input.value)
                    .filter((v) => /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(v)),
            ),
        },
    };
}

const sleep = (ms) => new Promise((done) => setTimeout(done, ms));

/** One capture in a fresh context. Throws on a navigation error or a stylesheet that did not load. */
async function captureOnce(browser, base, target) {
    const origin = new URL(base);
    const context = await browser.newContext({
        viewport: VIEWPORT,
        deviceScaleFactor: 1,
        javaScriptEnabled: false,
        reducedMotion: 'reduce',
        colorScheme: 'light',
        locale: BROWSER_LOCALE[target.locale] ?? 'en-GB',
        timezoneId: TIMEZONE,
        serviceWorkers: 'block',
    });
    try {
        await context.addCookies([
            { name: 'summit_design', value: target.design, url: origin.origin },
            { name: 'summit_qa', value: '1', url: origin.origin },
        ]);
        await context.route('**/*', (route) => {
            const request = route.request();
            let host = '';
            try {
                host = new URL(request.url()).hostname;
            } catch {
                // data: and blob: URLs have no host and are never fetched
            }
            const local = host === origin.hostname || host === '127.0.0.1' || host === 'localhost';
            return !local || BLOCKED_TYPES.has(request.resourceType()) ? route.abort() : route.continue();
        });
        const page = await context.newPage();
        const failedSheets = [];
        page.on('requestfailed', (request) => {
            if (request.resourceType() === 'stylesheet') failedSheets.push(`${request.url()} (${request.failure()?.errorText ?? 'failed'})`);
        });
        page.on('response', (response) => {
            if (response.request().resourceType() === 'stylesheet' && response.status() >= 400) failedSheets.push(`${response.url()} (HTTP ${response.status()})`);
        });
        const response = await page.goto(origin.origin + target.path, { waitUntil: 'load', timeout: NAV_TIMEOUT });
        if (!response) throw new Error('no response');
        const hops = [];
        for (let request = response.request().redirectedFrom(); request; request = request.redirectedFrom()) hops.unshift(request);
        const chain = [];
        for (const hop of hops) {
            const hopResponse = await hop.response();
            const to = hop.redirectedTo() ? new URL(hop.redirectedTo().url()) : null;
            chain.push(`${hopResponse ? hopResponse.status() : '?'} ${to ? to.pathname + to.search : '?'}`);
        }
        chain.push(String(response.status()));
        const data = await page.evaluate(pageCapture);
        const unloaded = [...failedSheets, ...data.unloaded];
        if (unloaded.length) throw new Error(`stylesheet not loaded: ${[...new Set(unloaded)].join(', ')}`);
        return { status: response.status(), chain: chain.join(' -> '), ...data };
    } finally {
        await context.close();
    }
}

/** A capture with retries (navigation error, missing stylesheet, 5xx). */
async function capture(browser, base, target) {
    let last = null;
    for (let attempt = 1; attempt <= ATTEMPTS; attempt += 1) {
        try {
            const result = await captureOnce(browser, base, target);
            if (result.status < 500 || attempt === ATTEMPTS) return { ...result, attempts: attempt };
            last = `HTTP ${result.status}`;
        } catch (error) {
            last = String(error?.message ?? error).split('\n')[0];
        }
        await sleep(1000 * attempt);
    }
    return { error: last, attempts: ATTEMPTS };
}

async function pool(items, size, task) {
    const results = new Array(items.length);
    let next = 0;
    const worker = async () => {
        while (next < items.length) {
            const index = next++;
            results[index] = await task(items[index], index);
        }
    };
    await Promise.all(Array.from({ length: Math.max(1, Math.min(size, items.length)) }, worker));
    return results;
}

/** The site answers and each selected design's stylesheets load (exit 2 otherwise). */
async function preflight(base, designs) {
    const origin = new URL(base).origin;
    for (const design of designs) {
        let html;
        try {
            const response = await fetch(`${origin}/en/`, { headers: { cookie: `summit_design=${design}; summit_qa=1` }, signal: AbortSignal.timeout(20_000) });
            html = await response.text();
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
        } catch (error) {
            throw new Error(`${origin}/en/ (design ${design}) cannot be reached: ${error.message}`);
        }
        const links = [...html.matchAll(/<link\b[^>]*>/gi)].map((m) => m[0]).filter((tag) => /\brel=["'][^"']*\bstylesheet\b/i.test(tag));
        for (const tag of links) {
            const href = tag.match(/\bhref=["']([^"']+)["']/i)?.[1];
            if (!href) continue;
            const url = new URL(href.replaceAll('&amp;', '&'), `${origin}/en/`).href;
            try {
                const response = await fetch(url, { signal: AbortSignal.timeout(20_000) });
                await response.arrayBuffer();
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
            } catch (error) {
                throw new Error(`design ${design} stylesheet ${url} cannot be loaded (${error.message}); is its Vite dev server or build there?`);
            }
        }
    }
}

// ------------------------------------------------------------------------------------------ diff

/** Myers' shortest edit script between two line arrays, or null beyond `maxD` edits. */
function myers(a, b, maxD) {
    const n = a.length;
    const m = b.length;
    if (n === 0 || m === 0) return [...a.map((s) => ['-', s]), ...b.map((s) => ['+', s])];
    const max = n + m;
    const off = max + 1;
    const v = new Int32Array(2 * max + 3);
    const trace = [];
    let end = -1;
    search: for (let d = 0; d <= max; d += 1) {
        if (d > maxD) return null;
        trace.push(v.slice(off - d - 1, off + d + 2));
        for (let k = -d; k <= d; k += 2) {
            let x = k === -d || (k !== d && v[off + k - 1] < v[off + k + 1]) ? v[off + k + 1] : v[off + k - 1] + 1;
            let y = x - k;
            while (x < n && y < m && a[x] === b[y]) {
                x += 1;
                y += 1;
            }
            v[off + k] = x;
            if (x >= n && y >= m) {
                end = d;
                break search;
            }
        }
    }
    const ops = [];
    let x = n;
    let y = m;
    for (let d = end; d >= 0; d -= 1) {
        const at = (k) => trace[d][k + d + 1];
        let prevX = 0;
        let prevY = 0;
        if (d > 0) {
            const k = x - y;
            const prevK = k === -d || (k !== d && at(k - 1) < at(k + 1)) ? k + 1 : k - 1;
            prevX = at(prevK);
            prevY = prevX - prevK;
        }
        while (x > prevX && y > prevY) {
            ops.push([' ', a[x - 1]]);
            x -= 1;
            y -= 1;
        }
        if (d > 0) ops.push(x === prevX ? ['+', b[prevY]] : ['-', a[prevX]]);
        x = prevX;
        y = prevY;
    }
    return ops.reverse();
}

function editScript(a, b) {
    let start = 0;
    while (start < a.length && start < b.length && a[start] === b[start]) start += 1;
    let endA = a.length;
    let endB = b.length;
    while (endA > start && endB > start && a[endA - 1] === b[endB - 1]) {
        endA -= 1;
        endB -= 1;
    }
    const midA = a.slice(start, endA);
    const midB = b.slice(start, endB);
    const middle = myers(midA, midB, MAX_EDIT_DISTANCE) ?? [...midA.map((s) => ['-', s]), ...midB.map((s) => ['+', s])];
    return [...a.slice(0, start).map((s) => [' ', s]), ...middle, ...a.slice(endA).map((s) => [' ', s])];
}

/** A unified diff (3 lines of context), '' when the line arrays are equal. */
export function unifiedDiff(oldLines, newLines, oldLabel, newLabel, context = CONTEXT_LINES) {
    let oldNo = 0;
    let newNo = 0;
    const rows = editScript(oldLines, newLines).map(([tag, text]) => {
        const row = { tag, text, oldBefore: oldNo, newBefore: newNo };
        if (tag !== '+') oldNo += 1;
        if (tag !== '-') newNo += 1;
        return row;
    });
    const ranges = [];
    rows.forEach((row, i) => {
        if (row.tag === ' ') return;
        const from = Math.max(0, i - context);
        const to = Math.min(rows.length, i + context + 1);
        const last = ranges.at(-1);
        if (last && from <= last[1]) last[1] = to;
        else ranges.push([from, to]);
    });
    if (!ranges.length) return '';
    const out = [`--- ${oldLabel}`, `+++ ${newLabel}`];
    for (const [from, to] of ranges) {
        const hunk = rows.slice(from, to);
        const oldLen = hunk.filter((r) => r.tag !== '+').length;
        const newLen = hunk.filter((r) => r.tag !== '-').length;
        out.push(`@@ -${hunk[0].oldBefore + (oldLen ? 1 : 0)},${oldLen} +${hunk[0].newBefore + (newLen ? 1 : 0)},${newLen} @@`);
        for (const r of hunk) out.push(`${r.tag}${r.text}`);
    }
    return out.join('\n');
}

// ------------------------------------------------------------------------------------------ files

const toLines = (content) => content.replace(/\r\n?/g, '\n').replace(/\n$/, '').split('\n');
const snapshotFile = (dir, target) => join(dir, target.design, target.locale, `${target.slug}.txt`);

/** Every stored snapshot: {dir}/{design}/{locale}/{route}.txt. */
function listSnapshots(dir) {
    const files = [];
    if (!existsSync(dir)) return files;
    for (const design of readdirSync(dir).sort()) {
        const designDir = join(dir, design);
        if (!statSync(designDir).isDirectory()) continue;
        for (const locale of readdirSync(designDir).sort()) {
            const localeDir = join(designDir, locale);
            if (!statSync(localeDir).isDirectory()) continue;
            for (const file of readdirSync(localeDir).sort()) if (file.endsWith('.txt')) files.push(join(localeDir, file));
        }
    }
    return files;
}

/** sha256 over the files under `folder` (relative to the project) whose names match `pattern`. */
function hashSources(folder, pattern) {
    const files = [];
    const walk = (dir) => {
        for (const name of readdirSync(dir).sort()) {
            const file = join(dir, name);
            if (statSync(file).isDirectory()) walk(file);
            else if (pattern.test(name)) files.push(file);
        }
    };
    walk(join(ROOT, folder));
    const hash = createHash('sha256');
    for (const file of files) hash.update(`${relative(ROOT, file)}\0`).update(readFileSync(file)).update('\0');
    return `sha256:${hash.digest('hex')}`;
}

const sourceHashes = () => ({
    'database/content/*.json': hashSources('database/content', /\.json$/),
    'lang/**/*.php': hashSources('lang', /\.php$/),
});

/** Stored snapshots per design and locale, locales in the order en, ar, ru, root (then any other). */
function countFiles(dir) {
    const counts = {};
    for (const file of listSnapshots(dir)) {
        const [design, locale] = relative(dir, file).split(/[\\/]/);
        counts[design] ??= {};
        counts[design][locale] = (counts[design][locale] ?? 0) + 1;
    }
    const rank = (locale) => {
        const i = ['en', 'ar', 'ru', ROOT_LOCALE].indexOf(locale);
        return i === -1 ? 99 : i;
    };
    for (const design of Object.keys(counts)) {
        counts[design] = Object.fromEntries(Object.entries(counts[design]).sort(([a], [b]) => rank(a) - rank(b) || a.localeCompare(b)));
    }
    return counts;
}

/** A folder for messages: relative to the working directory when inside it, else absolute. */
function shown(dir) {
    const rel = relative(process.cwd(), dir);
    return rel === '' ? '.' : rel.startsWith('..') ? dir : rel;
}

/** A snapshot file for messages and diff labels: relative to the project when inside it, else absolute. */
function fileLabel(file) {
    const rel = relative(ROOT, file);
    return rel.startsWith('..') ? file : rel;
}

function writeManifest(dir, routes) {
    const manifest = {
        generator: 'scripts/qa/text-snapshot.mjs',
        format: 1,
        layout: '{design}/{locale}/{route}.txt; the language chooser "/" has no locale and is stored as {design}/root/chooser.txt',
        capture: {
            browser: 'Google Chrome (Playwright channel "chrome"), headless, one fresh context per page',
            cookies: 'summit_design=<design>, summit_qa=1',
            javaScript: false,
            reducedMotion: 'reduce',
            colorScheme: 'light',
            viewport: `${VIEWPORT.width}x${VIEWPORT.height} at device scale 1`,
            timezone: TIMEZONE,
            browserLocale: BROWSER_LOCALE,
            notFetched: 'images, fonts, media, scripts and any other host',
            landmarks: 'first <header> and first <footer> outside article/aside/main/nav/section, first <main>',
            text: 'innerText with CSS text-transform switched off and every <details> opened',
        },
        normalisation: 'NFC; CR LF, CR, U+2028, U+2029 -> LF; tabs and \\p{Zs} -> one space; runs of spaces collapsed; lines trimmed; empty lines dropped; quotes, dashes, digits and case kept',
        masks: {
            '{{YEAR}}': 'the year after "©" (footer copyright line, rendered from the clock)',
            '{{CSRF}}': 'the page CSRF token, if it ever appears in the text',
            '{{NONCE}}': 'the page CSP nonce, if it ever appears in the text',
            '{{UUID}}': 'any UUID (idempotency keys), if one ever appears in the text',
        },
        // An array keeps the route order ("404" would sort first as an object key).
        routes: routes
            .filter((r) => r.locale === routes[0].locale || r.locale === ROOT_LOCALE)
            .map((r) => ({
                route: r.slug,
                path: r.locale === ROOT_LOCALE ? r.path : `/{locale}${r.path.slice(r.locale.length + 1)}`,
                status: r.expect,
            })),
        sources: sourceHashes(),
        files: countFiles(dir),
    };
    writeFileSync(join(dir, 'manifest.json'), `${JSON.stringify(manifest, null, 2)}\n`);
}

// ------------------------------------------------------------------------------------------ cli

const USAGE = `Usage: node scripts/qa/text-snapshot.mjs [--check] [--base=<url>] [--design=1|2|both] [--locales=en,ar,ru,root]
       [--routes=<slugs, * wildcard>] [--dir=<folder>] [--concurrency=<n>] [--verbose]
See the header of scripts/qa/text-snapshot.mjs for the capture rules, the normalisation and the masks.`;

class UsageError extends Error {}

function parseArgs(argv) {
    const opt = { check: false, verbose: false, help: false, base: DEFAULT_BASE, dir: DEFAULT_DIR, concurrency: 4, design: null, locales: null, routes: null };
    for (const arg of argv) {
        const [, key, value] = arg.match(/^--([a-z-]+)(?:=(.*))?$/) ?? [];
        if (key === 'check' || key === 'verbose' || key === 'help') {
            if (value !== undefined) throw new UsageError(`--${key} takes no value`);
            opt[key] = true;
        } else if (['base', 'dir', 'concurrency', 'design', 'locales', 'routes'].includes(key)) {
            if (!value) throw new UsageError(`--${key} needs a value`);
            opt[key] = value;
        } else {
            throw new UsageError(`unknown argument: ${arg}`);
        }
    }
    try {
        opt.base = new URL(opt.base).origin;
    } catch {
        throw new UsageError(`--base is not a URL: ${opt.base}`);
    }
    opt.dir = resolve(opt.dir);
    opt.concurrency = Number(opt.concurrency);
    if (!Number.isInteger(opt.concurrency) || opt.concurrency < 1) throw new UsageError('--concurrency must be a whole number of at least 1');
    return opt;
}

const splitList = (value) => value.split(',').map((s) => s.trim()).filter(Boolean);

function selectTargets(opt, routes, registry) {
    const designs = opt.design ? [...new Set(splitList(opt.design).flatMap((d) => (d === 'both' ? ['1', '2'] : [d])))] : ['1', '2'];
    for (const design of designs) if (!['1', '2'].includes(design)) throw new UsageError(`unknown design: ${design} (1, 2 or both)`);
    const allLocales = [...registry.locales, ROOT_LOCALE];
    const locales = opt.locales ? splitList(opt.locales) : allLocales;
    for (const locale of locales) if (!allLocales.includes(locale)) throw new UsageError(`unknown locale: ${locale} (${allLocales.join(', ')})`);
    const slugs = [...new Set(routes.map((r) => r.slug))];
    const patterns = opt.routes
        ? splitList(opt.routes).map((p) => {
              const re = new RegExp(`^${p.replace(/[.+?^${}()|[\]\\]/g, '\\$&').replaceAll('*', '.*')}$`);
              if (!slugs.some((slug) => re.test(slug))) throw new UsageError(`no route matches "${p}" (routes: ${slugs.join(', ')})`);
              return re;
          })
        : null;
    const targets = [];
    for (const design of designs) {
        for (const route of routes) {
            if (!locales.includes(route.locale)) continue;
            if (patterns && !patterns.some((re) => re.test(route.slug))) continue;
            if (route.designs && !route.designs.includes(design)) continue;
            targets.push({ ...route, design, id: `${design}/${route.locale}/${route.slug}` });
        }
    }
    if (!targets.length) throw new UsageError('no page matches the filters');
    return { targets, filtered: Boolean(opt.design || opt.locales || opt.routes), designs };
}

/** Turns a raw capture into the snapshot text, its mask counts and its problems. */
function processCapture(target, raw, today) {
    if (raw.error) return { target, error: raw.error, attempts: raw.attempts };
    const servedOk = target.design === '2' ? raw.served === '2' : raw.served === null || raw.served === '1';
    if (!servedOk) return { target, error: `served html data-design=${raw.served ?? 'none'} for design ${target.design} (cookie not applied?)`, attempts: raw.attempts };
    const counts = Object.fromEntries(MASKS.map((m) => [m, 0]));
    const parts = raw.parts.map((part) => ({ ...part, lines: part.text === null ? null : applyMasks(normaliseText(part.text), raw.volatile, counts) }));
    const content = renderSnapshot(target, raw, parts);
    const todayHits = parts.flatMap((part) => (part.lines ?? []).filter((line) => today.some((t) => line.includes(t))));
    return {
        target,
        content,
        counts,
        todayHits,
        status: raw.status,
        chain: raw.chain,
        unexpected: raw.status !== target.expect,
        opened: raw.opened,
        overrides: raw.overrides,
        attempts: raw.attempts,
        lines: parts.reduce((sum, part) => sum + (part.lines?.length ?? 0), 0),
    };
}

function printCommon(processed) {
    const done = processed.filter((p) => !p.error);
    const unexpected = done.filter((p) => p.unexpected);
    const failed = processed.filter((p) => p.error);
    const statuses = {};
    for (const p of done) statuses[p.chain] = (statuses[p.chain] ?? 0) + 1;
    console.log(`\nStatuses: ${Object.entries(statuses).map(([chain, n]) => `${chain} x ${n}`).join(', ') || 'none'}`);
    console.log(`Unexpected status: ${unexpected.length ? unexpected.map((p) => `${p.target.id} ${p.target.path} -> ${p.chain} (expected ${p.target.expect})`).join('; ') : 'none'}`);
    if (failed.length) for (const p of failed) console.log(`FAILED ${p.target.id} ${p.target.path}: ${p.error} (${p.attempts} attempts)`);
    const retried = done.filter((p) => p.attempts > 1);
    if (retried.length) console.log(`Retried: ${retried.map((p) => `${p.target.id} (${p.attempts} attempts)`).join(', ')}`);
    const totals = Object.fromEntries(MASKS.map((m) => [m, 0]));
    const files = Object.fromEntries(MASKS.map((m) => [m, 0]));
    for (const p of done) {
        for (const m of MASKS) {
            totals[m] += p.counts[m];
            if (p.counts[m]) files[m] += 1;
        }
    }
    console.log(`Masked: ${MASKS.map((m) => `${m} ${totals[m]} in ${files[m]} page(s)`).join(', ')}`);
    const sample = done.find((p) => p.counts['{{YEAR}}']);
    if (sample) console.log(`  e.g. ${sample.target.id}: ${toLines(sample.content).find((line) => line.includes('{{YEAR}}'))}`);
    const today = done.filter((p) => p.todayHits.length);
    if (today.length) console.log(`Warning: today's date appears in ${today.map((p) => `${p.target.id} ("${p.todayHits[0]}")`).join(', ')}; mask it if the clock produces it`);
    const opened = done.reduce((sum, p) => sum + p.opened, 0);
    const overrides = done.reduce((sum, p) => sum + p.overrides, 0);
    console.log(`Accordions opened: ${opened} <details> in ${done.filter((p) => p.opened).length} page(s); text-transform inline overrides: ${overrides}`);
    return { failed: failed.length, unexpected: unexpected.length };
}

function printCounts(dir) {
    const counts = countFiles(dir);
    console.log(`Files per design and locale in ${shown(dir)}:`);
    for (const [design, byLocale] of Object.entries(counts)) {
        const total = Object.values(byLocale).reduce((a, b) => a + b, 0);
        console.log(`  design ${design}: ${Object.entries(byLocale).map(([locale, n]) => `${locale} ${n}`).join(', ')} (${total})`);
    }
}

function writeMode(opt, processed, routes, filtered, targets) {
    const tally = { created: 0, changed: 0, unchanged: 0, skipped: 0 };
    for (const p of processed) {
        if (p.error || p.unexpected) {
            tally.skipped += 1;
            continue;
        }
        const file = snapshotFile(opt.dir, p.target);
        const before = existsSync(file) ? readFileSync(file, 'utf8').replace(/\r\n?/g, '\n') : null;
        let state = 'unchanged';
        if (before !== p.content) {
            mkdirSync(dirname(file), { recursive: true });
            writeFileSync(file, p.content);
            state = before === null ? 'created' : 'changed';
        }
        tally[state] += 1;
        console.log(`${state.padEnd(9)} ${p.target.id.padEnd(40)} ${p.chain.padEnd(5)} ${String(p.lines).padStart(5)} lines  ${p.target.path}`);
    }
    if (!filtered) {
        const keep = new Set(targets.map((t) => snapshotFile(opt.dir, t)));
        for (const file of listSnapshots(opt.dir)) {
            if (keep.has(file)) continue;
            rmSync(file);
            console.log(`deleted   ${relative(opt.dir, file)} (no such route)`);
        }
    }
    mkdirSync(opt.dir, { recursive: true });
    writeManifest(opt.dir, routes);
    const { failed, unexpected } = printCommon(processed);
    console.log(`Written: ${tally.created} created, ${tally.changed} changed, ${tally.unchanged} unchanged, ${tally.skipped} not written`);
    printCounts(opt.dir);
    return failed || unexpected ? 1 : 0;
}

function checkMode(opt, processed, filtered, targets) {
    const tally = { same: 0, different: 0, missing: 0, stale: 0 };
    for (const p of processed) {
        if (p.error) continue;
        const file = snapshotFile(opt.dir, p.target);
        const rel = fileLabel(file);
        if (!existsSync(file)) {
            tally.missing += 1;
            console.log(`MISSING   ${rel} (${p.target.path})`);
            continue;
        }
        const stored = readFileSync(file, 'utf8').replace(/\r\n?/g, '\n');
        if (stored === p.content) {
            tally.same += 1;
            if (opt.verbose) console.log(`same      ${p.target.id}`);
            continue;
        }
        tally.different += 1;
        console.log(`DIFFERENT ${rel}`);
        const side = (prefix) => (isAbsolute(rel) ? rel : `${prefix}/${rel}`);
        console.log(unifiedDiff(toLines(stored), toLines(p.content), `${side('a')} (stored)`, `${side('b')} (live ${opt.base}${p.target.path})`) || '(the files differ only in their final line break)');
    }
    if (!filtered) {
        const expected = new Set(targets.map((t) => snapshotFile(opt.dir, t)));
        for (const file of listSnapshots(opt.dir)) {
            if (expected.has(file)) continue;
            tally.stale += 1;
            console.log(`STALE     ${fileLabel(file)} (no such route; the write mode deletes it)`);
        }
    }
    const manifestFile = join(opt.dir, 'manifest.json');
    if (existsSync(manifestFile)) {
        try {
            const stored = JSON.parse(readFileSync(manifestFile, 'utf8')).sources ?? {};
            const now = sourceHashes();
            const changed = Object.keys(now).filter((key) => stored[key] !== now[key]);
            console.log(`\nText sources: ${changed.length ? `changed since the snapshots were written (${changed.join(', ')})` : 'unchanged since the snapshots were written'}`);
        } catch {
            console.log('\nText sources: manifest.json is unreadable');
        }
    }
    const { failed, unexpected } = printCommon(processed);
    console.log(`Checked ${processed.length} page(s) against ${shown(opt.dir)}: ${tally.same} same, ${tally.different} different, ${tally.missing} missing, ${tally.stale} stale, ${failed} failed`);
    return tally.different || tally.missing || tally.stale || failed || unexpected ? 1 : 0;
}

async function main() {
    let opt;
    let selection;
    let registry;
    let routes;
    try {
        opt = parseArgs(process.argv.slice(2));
        if (opt.help) {
            console.log(USAGE);
            return 0;
        }
        registry = readRegistry();
        routes = buildRoutes(registry);
        selection = selectTargets(opt, routes, registry);
    } catch (error) {
        console.error(error instanceof UsageError ? `${error.message}\n${USAGE}` : `Cannot read the routes: ${error.message}`);
        return 2;
    }
    try {
        await preflight(opt.base, selection.designs);
    } catch (error) {
        console.error(error.message);
        return 2;
    }
    const { targets, filtered } = selection;
    const started = Date.now();
    console.log(`${opt.check ? 'Checking' : 'Writing'} ${targets.length} text snapshot(s) from ${opt.base} (${opt.concurrency} at a time)`);
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    let raw;
    try {
        raw = await pool(targets, opt.concurrency, (target) => capture(browser, opt.base, target));
    } finally {
        await browser.close();
    }
    const today = todayStrings();
    const processed = targets.map((target, i) => processCapture(target, raw[i], today));
    const code = opt.check ? checkMode(opt, processed, filtered, targets) : writeMode(opt, processed, routes, filtered, targets);
    console.log(`Done in ${((Date.now() - started) / 1000).toFixed(1)} s, exit ${code}`);
    return code;
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
    main().then(
        (code) => {
            process.exitCode = code;
        },
        (error) => {
            console.error(error);
            process.exitCode = 2;
        },
    );
}
