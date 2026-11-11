#!/usr/bin/env node
/**
 * Lighthouse gate for Design 2 on a PRODUCTION build (upgrade spec §9.11, §12 G4 and the §12 budgets).
 *
 *   1. builds Design 2 into public/build-d2-qa (D2_BUILD_DIR=build-d2-qa npx vite build --config
 *      vite.d2.config.js): public/build-d2 and the dev server's public/hot-d2 are never touched;
 *   2. serves this working tree with php -S on 127.0.0.1:8043 (its own server, stopped at the end) with
 *      SUMMIT_D2_BUILD_DIR=build-d2-qa and SUMMIT_D2_HOT_FILE=<a file that does not exist> (config/design.php),
 *      so Laravel ignores the dev server and loads the bundled assets; APP_ENV=production, APP_DEBUG=false,
 *      SUMMIT_DEFAULT_DESIGN=2 (a first visit, no cookie), SUMMIT_DESIGN_SWITCHER=false, SUMMIT_NOINDEX=false;
 *   3. runs Lighthouse (mobile, default simulated throttling, installed Chrome) through a local gzip proxy
 *      (production sits behind Cloudflare; php -S never compresses) that also sends X-Summit-QA: 1, on the
 *      home, one service, the resources page and the contact page in EN and AR;
 *   4. gates: performance >= 90, accessibility = 100, best practices >= 95, SEO = 100; budgets LCP <= 2.0 s,
 *      CLS <= 0.02, first-party JS on home <= 90 KB gzip, hero JS <= 25 KB gzip, <= 2 font preloads.
 *
 *   node scripts/qa/lighthouse-d2.mjs                    build + serve + 8 runs
 *   node scripts/qa/lighthouse-d2.mjs --no-build          reuse public/build-d2-qa
 *   node scripts/qa/lighthouse-d2.mjs --url=/en/ --runs=3 one page, median of 3 runs
 *   node scripts/qa/lighthouse-d2.mjs --serve             build, serve on :8043 and wait (Ctrl+C stops it):
 *                                                         QA_BASE_URL=http://127.0.0.1:8043 npm run qa:d2:hero
 *   node scripts/qa/lighthouse-d2.mjs --build-dir=build-d2-<you>   build into and serve another public/ folder
 *                                                         (two runs never share one build)
 *
 * Output: qa/lighthouse-d2.json (summary rows + gates) and the table below; full reports in
 * qa/shots/<date>/lighthouse/<page>.report.{html,json} and summary.md.
 * SEO on a page the app itself marks noindex (an unbuilt template, or P07 while contact_page_released is
 * false) is also given without the is-crawlable audit, and that adjusted score is the one gated.
 * The server never outlives the script: it is stopped at the end, on Ctrl+C, SIGTERM and SIGHUP, and on any
 * crash (an 'exit' handler ends its process group synchronously, and Chrome's), so the port is free for the
 * next run. Only kill -9 of this script leaves it running; the next run then names the PIDs on the port.
 * Self-test (proxy and crash clean-up): node --test scripts/qa/lighthouse-d2.test.mjs
 * Exit 0: every gate passes. 1: a gate fails. 2: the run could not happen (build, port, server, Chrome, crash).
 */
import { spawn, spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, realpathSync, writeFileSync } from 'node:fs';
import http from 'node:http';
import path from 'node:path';
import { pipeline } from 'node:stream';
import { fileURLToPath } from 'node:url';
import zlib from 'node:zlib';
import * as chromeLauncher from 'chrome-launcher';
import lighthouse, { generateReport } from 'lighthouse';
import { computeMedianRun } from 'lighthouse/core/lib/median-run.js';
import { QA_DIR, ROOT, runDir } from '../../qa/lib/config.mjs';

const CHROME_PATH = process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
let BUILD_DIR = 'build-d2-qa'; // --build-dir=build-d2-<name> replaces it (never public/build or public/build-d2)
const BUILD_DIR_PATTERN = /^build-d2-[a-z0-9][a-z0-9-]*$/;
const MISSING_HOT = 'hot-d2-qa-disabled';
const OUT_DIR = runDir('lighthouse');
const SUMMARY = path.join(QA_DIR, 'lighthouse-d2.json');
const SERVICE = process.env.QA_D2_SERVICE || 'debt-recovery-cheque-cases';
const DEFAULT_PATHS = ['/en/', '/ar/', `/en/practice-areas/${SERVICE}/`, `/ar/practice-areas/${SERVICE}/`, '/en/resources/', '/ar/resources/', '/en/contact/', '/ar/contact/'];
const LOCALES = ['en', 'ar', 'ru'];
const CATEGORIES = ['performance', 'accessibility', 'best-practices', 'seo'];
const MIN_SCORE = { performance: 90, accessibility: 100, 'best-practices': 95, seo: 100 };
const KB = 1024;
const BUDGET = { lcpMs: 2000, cls: 0.02, homeJs: 90 * KB, heroJs: 25 * KB, fontPreloads: 2 };

// ------------------------------------------------------------------------------------ options
function parseArgs(argv) {
    const opts = { urls: [], runs: 1, build: true, port: 8043, serve: false, verbose: false, buildDir: null };
    for (const arg of argv) {
        const [flag, value] = arg.split(/=(.*)/s);
        if (flag === '--url' && value) opts.urls.push(value);
        else if (flag === '--runs') opts.runs = Math.max(1, Number.parseInt(value, 10) || 1);
        else if (flag === '--no-build') opts.build = false;
        else if (flag === '--port') opts.port = Number(value) || 8043;
        else if (flag === '--serve') opts.serve = true;
        else if (flag === '--verbose') opts.verbose = true;
        else if (flag === '--build-dir') {
            if (!BUILD_DIR_PATTERN.test(value ?? '')) {
                console.error('--build-dir must name a public/ folder that starts with build-d2- (for example build-d2-qa2), never build or build-d2.');
                process.exit(2);
            }
            opts.buildDir = value;
        } else if (flag === '--help' || flag === '-h') {
            console.log('Usage: node scripts/qa/lighthouse-d2.mjs [--no-build] [--url=<path>]… [--runs=<n>] [--port=8043] [--build-dir=build-d2-<name>] [--serve] [--verbose]');
            process.exit(0);
        } else {
            console.error(`Unknown option: ${arg}`);
            process.exit(2);
        }
    }
    return opts;
}

const slugOf = (url) => {
    const segments = url.pathname.split('/').filter(Boolean);
    const locale = LOCALES.includes(segments[0]) ? segments.shift() : null;
    let name = segments.length ? segments.join('-') : locale ? 'home' : 'root';
    if (name.startsWith('practice-areas-')) name = `service-${name.slice('practice-areas-'.length)}`;
    return [name, locale].filter(Boolean).join('-').replace(/[^a-z0-9-]+/gi, '_');
};

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

// ------------------------------------------------------------------------------------ build + server
function run(cmd, args, env) {
    return new Promise((resolve) => {
        const child = spawn(cmd, args, { cwd: ROOT, env: { ...process.env, ...env }, stdio: ['ignore', 'pipe', 'pipe'] });
        let out = '';
        child.stdout.on('data', (d) => (out += d));
        child.stderr.on('data', (d) => (out += d));
        child.on('close', (code) => resolve({ code, out }));
        child.on('error', (error) => resolve({ code: -1, out: String(error) }));
    });
}

async function build() {
    const started = Date.now();
    process.stdout.write(`Building Design 2 for production into public/${BUILD_DIR} … `);
    const { code, out } = await run('npx', ['vite', 'build', '--config', 'vite.d2.config.js'], { D2_BUILD_DIR: BUILD_DIR, NODE_ENV: 'production' });
    const ms = Date.now() - started;
    if (code !== 0) {
        console.log('failed');
        console.error(out.split('\n').slice(-40).join('\n'));
        return { ok: false, ms };
    }
    const manifest = path.join(ROOT, 'public', BUILD_DIR, 'manifest.json');
    if (!existsSync(manifest)) {
        console.log('failed');
        console.error(`No ${path.relative(ROOT, manifest)} after the build.`);
        return { ok: false, ms };
    }
    console.log(`done in ${(ms / 1000).toFixed(1)} s`);
    return { ok: true, ms, log: out.split('\n').filter((l) => /gzip/.test(l)).map((l) => l.replace(/\x1b\[[0-9;]*m/g, '').trim()) };
}

async function answers(url, timeout = 3000) {
    try {
        const res = await fetch(url, { redirect: 'manual', signal: AbortSignal.timeout(timeout) });
        await res.arrayBuffer().catch(() => null);
        return res.status;
    } catch {
        return 0;
    }
}

function serverPhp() {
    const own = path.join(ROOT, 'server.php');
    return existsSync(own) ? own : path.join(ROOT, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php');
}

/** PIDs listening on a local port (lsof; none when lsof is missing). */
function listeners(port) {
    const out = spawnSync('lsof', ['-nP', `-iTCP:${port}`, '-sTCP:LISTEN', '-t'], { encoding: 'utf8' });
    return String(out.stdout ?? '').split('\n').map((s) => s.trim()).filter(Boolean);
}

// ------------------------------------------------------------------------------------ never orphan the server
/** What this run started: stopped synchronously on any exit (the end, a signal, a crash). */
const live = { server: null, chrome: null };

/**
 * Ends the server's whole process group now (php -S and its PHP_CLI_SERVER_WORKERS workers). Synchronous,
 * because it also runs from the 'exit' handler, where nothing can be awaited: SIGTERM, 300 ms, then SIGKILL.
 */
export function stopServerSync(server) {
    if (!server || server.stopped) return;
    server.stopped = true;
    const group = (signal) => {
        try {
            process.kill(-server.child.pid, signal);
            return true;
        } catch {
            return false; // already gone
        }
    };
    if (!group('SIGTERM')) return;
    Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, 300);
    group('SIGKILL');
}

function stopAllSync() {
    if (live.chrome) {
        try {
            live.chrome.kill(); // chrome-launcher: SIGKILL to Chrome's process group, synchronous
        } catch {
            // already gone
        }
        live.chrome = null;
    }
    stopServerSync(live.server);
}

/**
 * The server and Chrome never outlive the script. An uncaught exception skips every finally block, and
 * chrome-launcher's own SIGINT/SIGTERM listener calls process.exit() at once, so the clean-up that cannot be
 * skipped is the synchronous 'exit' handler: crashes and the signals that end a run (Ctrl+C, SIGTERM, a closed
 * terminal) are all routed to process.exit for it.
 */
export function guardProcess() {
    if (guardProcess.installed) return;
    guardProcess.installed = true;
    process.on('exit', stopAllSync);
    process.on('uncaughtException', (error) => {
        console.error(`lighthouse-d2 crashed: ${error?.stack || error}`);
        process.exit(2);
    });
    process.on('unhandledRejection', (reason) => {
        console.error(`lighthouse-d2 crashed (unhandled rejection): ${reason?.stack || reason}`);
        process.exit(2);
    });
    for (const [signal, code] of [['SIGINT', 130], ['SIGTERM', 143], ['SIGHUP', 129]]) process.once(signal, () => process.exit(code));
}

export async function startServer(port) {
    const origin = `http://127.0.0.1:${port}`;
    if (await answers(`${origin}/up`)) {
        const pids = listeners(port);
        console.error(
            `Port ${port} already answers${pids.length ? ` (PID ${pids.join(', ')})` : ''}: this script only stops the server it starts. ` +
                `A run killed with kill -9 leaves its php -S behind (stop it: kill ${pids.join(' ') || '<pid>'}); otherwise pass --port=<n>.`,
        );
        return null;
    }
    const env = {
        PHP_CLI_SERVER_WORKERS: '4',
        APP_ENV: 'production',
        APP_DEBUG: 'false',
        SUMMIT_D2_BUILD_DIR: BUILD_DIR,
        SUMMIT_D2_HOT_FILE: MISSING_HOT,
        SUMMIT_DEFAULT_DESIGN: '2',
        SUMMIT_DESIGN_SWITCHER: 'false',
        SUMMIT_NOINDEX: 'false',
    };
    // Its own process group: php -S forks PHP_CLI_SERVER_WORKERS workers, and stopping must reach them all.
    const child = spawn('php', ['-S', `127.0.0.1:${port}`, serverPhp()], { cwd: path.join(ROOT, 'public'), env: { ...process.env, ...env }, stdio: ['ignore', 'ignore', 'pipe'], detached: true });
    let log = '';
    child.stderr.on('data', (d) => {
        log = (log + d).slice(-4000);
    });
    const server = { child, origin, env, port };
    live.server = server;
    for (let i = 0; i < 60; i += 1) {
        if (child.exitCode !== null) break;
        if ((await answers(`${origin}/up`, 2000)) === 200) return server;
        await sleep(250);
    }
    await stopServer(server);
    console.error(`php -S on port ${port} did not come up:\n${log}`);
    return null;
}

/** Stops the whole process group of the server this script started (the master and its workers). */
export async function stopServer(server) {
    if (!server || server.stopped) return;
    server.stopped = true;
    const group = (signal) => {
        try {
            process.kill(-server.child.pid, signal);
        } catch {
            // already gone
        }
    };
    group('SIGTERM');
    for (let i = 0; i < 30 && (await answers(`${server.origin}/up`, 500)); i += 1) await sleep(100);
    if (await answers(`${server.origin}/up`, 500)) group('SIGKILL');
}

/** The page really renders Design 2 from the QA build (not the dev server, not Design 1). */
async function verify(origin) {
    const res = await fetch(`${origin}/en/`, { signal: AbortSignal.timeout(20_000) });
    const html = await res.text();
    const problems = [];
    if (!html.includes('data-design="2"')) problems.push('no data-design="2" on <html> (Design 2 is not the default design)');
    if (!html.includes(`/${BUILD_DIR}/`)) problems.push(`no /${BUILD_DIR}/ asset URL`);
    if (/127\.0\.0\.1:5183|@vite\/client/.test(html)) problems.push('the page still loads the Vite dev server');
    return problems;
}

// ------------------------------------------------------------------------------------ gzip proxy
const COMPRESSIBLE = /^(text\/|application\/(javascript|json|ld\+json|manifest\+json|xml)|image\/svg\+xml)/i;

/**
 * The browser gives up on requests all the time (Lighthouse cancels preloads and the page between passes).
 * Its response then closes before it is complete: the upstream request is aborted with it, and an upstream
 * answer that still arrives is dropped, never piped into the closed response (Node 26's pipeline() throws
 * ERR_STREAM_UNABLE_TO_PIPE synchronously for a destroyed destination: that killed the run mid-way and left
 * php -S on the port). req.destroyed cannot tell: on Node 26 it is true once any request body is read.
 */
export function startProxy(upstream) {
    const agent = new http.Agent({ keepAlive: true, maxSockets: 8 });
    const server = http.createServer((req, res) => {
        const headers = { ...req.headers }; // the browser's Host header: Laravel builds URLs for the proxy origin
        delete headers['accept-encoding'];
        headers['x-summit-qa'] = '1'; // never the design switcher
        if (!/(?:^|;\s*)summit_design=/.test(headers.cookie ?? '')) headers.cookie = [headers.cookie, 'summit_design=2'].filter(Boolean).join('; ');
        const browserLeft = () => res.destroyed || res.writableEnded;
        const forward = http.request({ hostname: upstream.hostname, port: upstream.port, method: req.method, path: req.url, headers, agent }, (up) => {
            if (browserLeft()) {
                up.destroy();
                return;
            }
            const type = String(up.headers['content-type'] ?? '');
            const compress = COMPRESSIBLE.test(type) && !up.headers['content-encoding'] && /\bgzip\b/.test(String(req.headers['accept-encoding'] ?? '')) && req.method !== 'HEAD' && ![204, 304].includes(up.statusCode);
            const out = { ...up.headers };
            if (compress) {
                delete out['content-length'];
                out['content-encoding'] = 'gzip';
                out.vary = out.vary ? `${out.vary}, Accept-Encoding` : 'Accept-Encoding';
            }
            try {
                res.writeHead(up.statusCode, up.statusMessage, out);
                // A browser that leaves mid-body ends the pipeline with a premature close: it destroys `up` itself.
                if (compress) pipeline(up, zlib.createGzip({ level: 6 }), res, () => {});
                else pipeline(up, res, () => {});
            } catch {
                up.destroy();
                res.destroy();
            }
        });
        forward.on('error', (error) => {
            if (browserLeft()) return; // aborted because the browser left: nobody to answer
            if (res.headersSent) {
                res.destroy(); // the upstream failed mid-body: never append text to a (gzip) body
                return;
            }
            res.writeHead(502, { 'content-type': 'text/plain' });
            res.end(`qa proxy: ${error.message}`);
        });
        res.on('close', () => {
            if (!res.writableFinished) forward.destroy(); // the browser left before the end: abort upstream too
        });
        pipeline(req, forward, () => {});
    });
    return new Promise((resolve, reject) => {
        server.once('error', reject);
        server.listen(0, '127.0.0.1', () => resolve({ server, agent, port: server.address().port }));
    });
}

// ------------------------------------------------------------------------------------ results
/**
 * JavaScript loaded because of the hero, from the Vite manifest: the chunks of resources/js/d2/hero/* and
 * their static imports, minus everything the entry (app.js) loads anyway.
 */
function heroFiles() {
    const file = path.join(ROOT, 'public', BUILD_DIR, 'manifest.json');
    if (!existsSync(file)) return new Set();
    const manifest = JSON.parse(readFileSync(file, 'utf8'));
    const closure = (keys) => {
        const out = new Set();
        const visit = (key) => {
            if (out.has(key) || !manifest[key]) return;
            out.add(key);
            for (const dep of manifest[key].imports ?? []) visit(dep);
        };
        keys.forEach(visit);
        return out;
    };
    const keys = Object.keys(manifest);
    const eager = closure(keys.filter((k) => manifest[k].isEntry && manifest[k].file.endsWith('.js')));
    const hero = closure(keys.filter((k) => k.startsWith('resources/js/d2/hero/')));
    return new Set([...hero].filter((k) => !eager.has(k)).map((k) => `/${BUILD_DIR}/${manifest[k].file}`));
}

/** The LCP node: Lighthouse 13 reports it in lcp-breakdown-insight (older: largest-contentful-paint-element). */
function lcpNode(lhr) {
    const find = (value) => {
        if (!value || typeof value !== 'object') return null;
        if (value.type === 'node' && (value.selector || value.snippet)) return value;
        for (const v of Array.isArray(value) ? value : Object.values(value)) {
            const hit = find(v);
            if (hit) return hit;
        }
        return null;
    };
    return find(lhr.audits['lcp-breakdown-insight']?.details) ?? find(lhr.audits['largest-contentful-paint-element']?.details);
}

function summarise(target, lhr, html, hero) {
    const score = (id) => {
        const s = lhr.categories[id]?.score;
        return typeof s === 'number' ? Math.round(s * 100) : null;
    };
    const numeric = (id) => lhr.audits[id]?.numericValue ?? null;
    const requests = lhr.audits['network-requests']?.details?.items ?? [];
    const origin = new URL(lhr.mainDocumentUrl || lhr.finalDisplayedUrl || target.measured.href).origin;
    const sameOrigin = (r) => {
        try {
            return new URL(r.url).origin === origin;
        } catch {
            return false;
        }
    };
    const pathOf = (r) => {
        try {
            return new URL(r.url).pathname;
        } catch {
            return '';
        }
    };
    const scripts = requests.filter((r) => r.resourceType === 'Script');
    const firstPartyJs = scripts.filter(sameOrigin).reduce((n, r) => n + (r.transferSize || 0), 0);
    const heroJs = scripts.filter((r) => sameOrigin(r) && hero.has(pathOf(r))).reduce((n, r) => n + (r.transferSize || 0), 0);
    const failing = {};
    for (const id of CATEGORIES) {
        failing[id] = (lhr.categories[id]?.auditRefs ?? [])
            .filter((ref) => ref.weight > 0)
            .map((ref) => lhr.audits[ref.id])
            .filter((a) => a && typeof a.score === 'number' && a.score < (id === 'performance' ? 0.9 : 1))
            .map((a) => (id === 'performance' ? `${a.id} ${a.displayValue ?? ''}`.trim() : a.id));
    }
    // SEO without is-crawlable when the app itself marks the page noindex (unbuilt template, unreleased P07).
    const noindex = /<meta[^>]+name=["']robots["'][^>]+content=["'][^"']*noindex/i.test(html);
    let seoAdjusted = null;
    if (noindex) {
        const refs = (lhr.categories.seo?.auditRefs ?? []).filter((r) => r.weight > 0 && r.id !== 'is-crawlable');
        const weights = refs.reduce((n, r) => n + r.weight, 0);
        const sum = refs.reduce((n, r) => n + r.weight * (typeof lhr.audits[r.id]?.score === 'number' ? lhr.audits[r.id].score : 1), 0);
        seoAdjusted = weights ? Math.round((sum / weights) * 100) : null;
    }
    const lcp = lcpNode(lhr);
    return {
        page: target.slug,
        url: target.display.pathname,
        scores: Object.fromEntries(CATEGORIES.map((id) => [id, score(id)])),
        seoAdjusted,
        noindex,
        lcpMs: numeric('largest-contentful-paint'),
        fcpMs: numeric('first-contentful-paint'),
        cls: numeric('cumulative-layout-shift'),
        tbtMs: numeric('total-blocking-time'),
        totalBytes: numeric('total-byte-weight'),
        firstPartyJsBytes: firstPartyJs,
        allJsBytes: scripts.reduce((n, r) => n + (r.transferSize || 0), 0),
        heroJsBytes: heroJs,
        fontPreloads: (html.match(/<link[^>]+rel=["']preload["'][^>]+as=["']font["']|<link[^>]+as=["']font["'][^>]+rel=["']preload["']/gi) ?? []).length,
        lcpElement: lcp
            ? {
                  selector: lcp.selector ?? null,
                  label: lcp.nodeLabel ?? null,
                  snippet: lcp.snippet ?? null,
                  isH1: /^<h1\b/i.test(lcp.snippet ?? '') || /(^|[\s>])h1([#.:\s>]|$)/i.test(lcp.selector ?? ''),
              }
            : null,
        runtimeError: lhr.runtimeError ? `${lhr.runtimeError.code}: ${lhr.runtimeError.message}` : null,
        warnings: lhr.runWarnings ?? [],
        failingAudits: failing,
    };
}

function gates(row) {
    const problems = [];
    if (row.runtimeError) problems.push(`Lighthouse could not measure the page: ${row.runtimeError}`);
    for (const id of CATEGORIES) {
        const s = id === 'seo' && row.noindex ? row.seoAdjusted : row.scores[id];
        if (s === null || s < MIN_SCORE[id]) {
            const detail = row.failingAudits[id]?.length ? ` [${row.failingAudits[id].join(', ')}]` : '';
            problems.push(`${id} ${s ?? 'n/a'} < ${MIN_SCORE[id]}${detail}`);
        }
    }
    if (row.lcpMs === null || row.lcpMs > BUDGET.lcpMs) problems.push(`LCP ${fmtMs(row.lcpMs)} > 2.0 s`);
    if (row.cls === null || row.cls > BUDGET.cls) problems.push(`CLS ${fmtCls(row.cls)} > ${BUDGET.cls}`);
    if (row.page.startsWith('home-')) {
        if (row.firstPartyJsBytes > BUDGET.homeJs) problems.push(`home JS ${fmtKb(row.firstPartyJsBytes)} > 90 KB gzip`);
        if (row.heroJsBytes > BUDGET.heroJs) problems.push(`hero JS ${fmtKb(row.heroJsBytes)} > 25 KB gzip`);
        if (row.lcpElement && !row.lcpElement.isH1) problems.push(`LCP element is ${row.lcpElement.selector ?? row.lcpElement.label}, not the H1`);
    }
    if (row.fontPreloads > BUDGET.fontPreloads) problems.push(`${row.fontPreloads} font preloads > ${BUDGET.fontPreloads}`);
    return problems;
}

const fmtMs = (ms) => (ms === null ? 'n/a' : ms >= 1000 ? `${(ms / 1000).toFixed(2)} s` : `${Math.round(ms)} ms`);
const fmtCls = (v) => (v === null ? 'n/a' : v.toFixed(3));
const fmtKb = (b) => (b === null ? 'n/a' : `${(b / KB).toFixed(1)} KB`);

function table(rows) {
    const head = ['Page', 'Perf', 'A11y', 'BP', 'SEO', 'LCP', 'CLS', 'TBT', 'Weight', 'JS 1st-party', 'Hero JS', 'Fonts', 'LCP element'];
    const body = rows.map((r) => [
        r.page,
        ...CATEGORIES.map((id) => (id === 'seo' && r.noindex ? `${r.scores.seo ?? 'n/a'}→${r.seoAdjusted ?? 'n/a'}*` : String(r.scores[id] ?? 'n/a'))),
        fmtMs(r.lcpMs),
        fmtCls(r.cls),
        fmtMs(r.tbtMs),
        fmtKb(r.totalBytes),
        fmtKb(r.firstPartyJsBytes),
        r.page.startsWith('home-') ? fmtKb(r.heroJsBytes) : '–',
        String(r.fontPreloads),
        r.lcpElement ? (r.lcpElement.isH1 ? 'h1' : (r.lcpElement.selector ?? r.lcpElement.label ?? '?').slice(0, 40)) : 'n/a',
    ]);
    const widths = head.map((h, i) => Math.max(h.length, ...body.map((row) => row[i].length)));
    const line = (cells) => cells.map((c, i) => (i === 0 || i === cells.length - 1 ? c.padEnd(widths[i]) : c.padStart(widths[i]))).join('  ');
    const text = [line(head), widths.map((w) => '-'.repeat(w)).join('  '), ...body.map(line)];
    const md = [`| ${head.join(' | ')} |`, `|${head.map(() => '---').join('|')}|`, ...body.map((r) => `| ${r.join(' | ')} |`)];
    return { text: text.join('\n'), md: md.join('\n') };
}

// ------------------------------------------------------------------------------------ main
async function main() {
    const opts = parseArgs(process.argv.slice(2));
    if (opts.buildDir) BUILD_DIR = opts.buildDir;
    guardProcess();
    if (!existsSync(CHROME_PATH)) {
        console.error(`Google Chrome not found at ${CHROME_PATH} (set CHROME_PATH).`);
        return 2;
    }
    let buildInfo = { ok: true, ms: 0, reused: true };
    if (opts.build) {
        buildInfo = await build();
        if (!buildInfo.ok) return 2;
    } else if (!existsSync(path.join(ROOT, 'public', BUILD_DIR, 'manifest.json'))) {
        console.error(`--no-build, but public/${BUILD_DIR}/manifest.json does not exist.`);
        return 2;
    }
    const server = await startServer(opts.port);
    if (!server) return 2;
    const cleanup = async () => stopServer(server); // signals and crashes: guardProcess()
    try {
        const problems = await verify(server.origin);
        if (problems.length) {
            console.error(`The QA server does not serve the production Design 2 build: ${problems.join('; ')}`);
            return 2;
        }
        if (opts.serve) {
            console.log(`Serving the production Design 2 build on ${server.origin} (Ctrl+C stops it).`);
            await new Promise(() => {});
        }

        const proxy = await startProxy(new URL(server.origin));
        const targets = (opts.urls.length ? opts.urls : DEFAULT_PATHS).map((p) => {
            const display = new URL(p.replace(/^\//, ''), `${server.origin}/`);
            const measured = new URL(display.href);
            measured.port = String(proxy.port);
            return { display, measured, slug: slugOf(display) };
        });
        mkdirSync(OUT_DIR, { recursive: true });
        const launch = () => chromeLauncher.launch({ chromePath: CHROME_PATH, chromeFlags: ['--headless=new', '--no-first-run', '--no-default-browser-check', '--disable-extensions'], logLevel: 'silent' });
        const chrome = await launch().catch(async () => {
            await sleep(1500);
            return launch();
        });
        live.chrome = chrome;
        const hero = heroFiles();
        const rows = [];
        try {
            for (const target of targets) {
                const lhrs = [];
                for (let i = 1; i <= opts.runs; i += 1) {
                    process.stdout.write(`Lighthouse ${target.display.pathname}${opts.runs > 1 ? ` (run ${i}/${opts.runs})` : ''} … `);
                    const result = await lighthouse(target.measured.href, { port: chrome.port, output: 'json', logLevel: opts.verbose ? 'info' : 'error', onlyCategories: CATEGORIES, formFactor: 'mobile' });
                    if (!result?.lhr) throw new Error(`Lighthouse returned no result for ${target.measured.href}`);
                    lhrs.push(result.lhr);
                    console.log(result.lhr.runtimeError ? result.lhr.runtimeError.code : `done (perf ${Math.round((result.lhr.categories.performance?.score ?? 0) * 100)})`);
                }
                const valid = lhrs.filter((l) => !l.runtimeError && Number.isFinite(l.audits['first-contentful-paint']?.numericValue) && Number.isFinite(l.audits.interactive?.numericValue));
                const lhr = valid.length > 1 ? computeMedianRun(valid) : (valid[0] ?? lhrs[lhrs.length - 1]);
                writeFileSync(path.join(OUT_DIR, `${target.slug}.report.html`), generateReport(lhr, 'html'));
                writeFileSync(path.join(OUT_DIR, `${target.slug}.report.json`), JSON.stringify(lhr));
                const html = await (await fetch(target.measured.href, { signal: AbortSignal.timeout(20_000) })).text();
                rows.push(summarise(target, lhr, html, hero));
            }
        } finally {
            live.chrome = null;
            try {
                await chrome.kill();
            } catch {
                // already gone
            }
            proxy.agent.destroy();
            await new Promise((resolve) => proxy.server.close(resolve));
        }

        let failed = false;
        for (const row of rows) {
            row.gateFailures = gates(row);
            failed ||= row.gateFailures.length > 0;
        }
        const { text, md } = table(rows);
        console.log(`\n${text}\n`);
        console.log('Gates: performance >= 90, accessibility 100, best practices >= 95, SEO 100 (* = noindex by the app: SEO without is-crawlable);');
        console.log('       LCP <= 2.0 s, CLS <= 0.02, home JS <= 90 KB gzip, hero JS <= 25 KB gzip, <= 2 font preloads, LCP element = H1 (home).\n');
        for (const row of rows) console.log(row.gateFailures.length ? `FAIL ${row.page}\n  - ${row.gateFailures.join('\n  - ')}` : `PASS ${row.page}`);
        const summary = {
            generatedAt: new Date().toISOString(),
            server: server.origin,
            env: server.env,
            build: { dir: `public/${BUILD_DIR}`, seconds: Math.round(buildInfo.ms / 100) / 10, reused: Boolean(buildInfo.reused), gzip: buildInfo.log ?? [] },
            proxied: true,
            minScore: MIN_SCORE,
            budget: BUDGET,
            reports: path.relative(QA_DIR, OUT_DIR),
            pass: !failed,
            rows,
        };
        writeFileSync(SUMMARY, `${JSON.stringify(summary, null, 2)}\n`);
        writeFileSync(path.join(OUT_DIR, 'summary.md'), `# Lighthouse (Design 2, production build)\n\n${summary.generatedAt}\n\n${md}\n\n${rows.map((r) => (r.gateFailures.length ? `- FAIL ${r.page}: ${r.gateFailures.join('; ')}` : `- PASS ${r.page}`)).join('\n')}\n`);
        console.log(`\nSummary: ${path.relative(ROOT, SUMMARY)} · reports: ${path.relative(ROOT, OUT_DIR)}/<page>.report.html`);
        return failed ? 1 : 0;
    } finally {
        await cleanup();
    }
}

/** Run only as a script: the self-test imports startProxy, startServer and guardProcess from this file. */
function invokedDirectly() {
    try {
        return Boolean(process.argv[1]) && realpathSync(process.argv[1]) === realpathSync(fileURLToPath(import.meta.url));
    } catch {
        return false;
    }
}

if (invokedDirectly()) {
    main().then(
        (code) => process.exit(code),
        (error) => {
            console.error(`lighthouse-d2 could not run: ${error?.stack || error}`);
            process.exit(2);
        },
    );
}
