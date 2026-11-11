#!/usr/bin/env node
/**
 * Performance budgets of Design 2 per page (docs/briefs/design-2.1-and-admin-cms.md §6 "Performance"):
 *   JavaScript at most 100 KB gzip, CSS at most 60 KB gzip, at most two preloaded font files.
 *
 *   node scripts/qa/budgets.mjs                         build, serve, measure every page (npm run qa:budgets)
 *   node scripts/qa/budgets.mjs --no-build --routes=home,calculators --locales=en
 *
 * How
 *   1. Builds Design 2 for production into public/build-d2-budgets (D2_BUILD_DIR, as scripts/qa/lighthouse-d2.mjs
 *      does with build-d2-qa): public/build-d2 and the dev server's public/hot-d2 are never touched. --no-build
 *      reuses the folder.
 *   2. Serves this working tree with its own php -S (default 127.0.0.1:8047, stopped at the end, on Ctrl+C and on a
 *      crash) with SUMMIT_D2_BUILD_DIR=build-d2-budgets and a hot file that does not exist, so every page loads the
 *      bundled assets; APP_ENV=production, SUMMIT_DESIGN_SWITCHER=false. Pending copy is shown by default
 *      (SUMMIT_SHOW_PENDING=true: the tool blocks and the calculators page are what the client meeting sees and the
 *      heaviest case); --pending=off measures what production serves today.
 *   3. Opens every Design 2 route (text-snapshot.mjs's list: home, P02-P08, L01, L02, S01-S11, calculators, 404) in
 *      EN, AR and RU at 1440 and 390 px in Google Chrome, scrolls through the page so lazy chunks load (the wizard,
 *      the calculators, the navigator), waits for the network to settle, and records every script, stylesheet and
 *      font the page requested from the build.
 *   4. Sizes each built file as gzip (level 9) from disk; inline <script> (not JSON) and <style> elements count too.
 *      Font preloads are the page's <link rel="preload" as="font"> elements.
 *
 * Options: --no-build, --build-dir=build-d2-<name>, --port=<n> (default: the first free port from 8047), --routes=<slugs> ("*" wildcard),
 *   --locales=en,ar,ru, --widths=1440,390, --pending=on|off, --concurrency=<n> (default 2),
 *   --out=<path without extension> (default storage/qa/budgets).
 * Output: storage/qa/budgets.md and .json. Exit 0 = every page within budget, 1 = a page over budget,
 * 2 = the run could not happen (build, port, server, Chrome).
 */
import { spawn, spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, isAbsolute, join, relative, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { gzipSync } from 'node:zlib';
import { buildRoutes, readRegistry } from './text-snapshot.mjs';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const KB = 1024;
export const BUDGET = { js: 100 * KB, css: 60 * KB, fontPreloads: 2 };
const BUILD_DIR_PATTERN = /^build-d2-[a-z0-9][a-z0-9-]*$/u;
const MISSING_HOT = 'hot-d2-budgets-disabled';
const BROWSER_LOCALE = { en: 'en-GB', ar: 'ar-AE', ru: 'ru-RU' };

const sleep = (ms) => new Promise((done) => setTimeout(done, ms));

export const gzipBytes = (buffer) => gzipSync(buffer, { level: 9 }).length;

/** Totals for one page from its recorded assets: [{type: 'script'|'stylesheet'|'font', bytes, gzip}] + inline. */
export function totals(assets, inline, fontPreloads) {
    const sum = (type) => assets.filter((a) => a.type === type).reduce((n, a) => n + a.gzip, 0);
    const js = sum('script') + inline.js;
    const css = sum('stylesheet') + inline.css;
    return {
        js,
        css,
        fonts: assets.filter((a) => a.type === 'font').length,
        fontPreloads,
        over: [js > BUDGET.js ? 'js' : null, css > BUDGET.css ? 'css' : null, fontPreloads > BUDGET.fontPreloads ? 'font preloads' : null].filter(Boolean),
    };
}

// ------------------------------------------------------------------------------------------ build + server

function run(cmd, args, env) {
    return new Promise((done) => {
        const child = spawn(cmd, args, { cwd: ROOT, env: { ...process.env, ...env }, stdio: ['ignore', 'pipe', 'pipe'] });
        let out = '';
        child.stdout.on('data', (d) => (out += d));
        child.stderr.on('data', (d) => (out += d));
        child.on('close', (code) => done({ code, out }));
        child.on('error', (error) => done({ code: -1, out: String(error) }));
    });
}

async function build(buildDir) {
    const started = Date.now();
    process.stdout.write(`Building Design 2 for production into public/${buildDir} … `);
    const { code, out } = await run('npx', ['vite', 'build', '--config', 'vite.d2.config.js'], { D2_BUILD_DIR: buildDir, NODE_ENV: 'production' });
    if (code !== 0 || !existsSync(join(ROOT, 'public', buildDir, 'manifest.json'))) {
        console.log('failed');
        console.error(out.split('\n').slice(-40).join('\n'));
        return false;
    }
    console.log(`done in ${((Date.now() - started) / 1000).toFixed(1)} s`);
    return true;
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

const live = { server: null };

function stopServerSync(server) {
    if (!server || server.stopped) return;
    server.stopped = true;
    const group = (signal) => {
        try {
            process.kill(-server.child.pid, signal);
            return true;
        } catch {
            return false;
        }
    };
    if (!group('SIGTERM')) return;
    Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, 300);
    group('SIGKILL');
}

function guardProcess() {
    process.on('exit', () => stopServerSync(live.server));
    process.on('uncaughtException', (error) => {
        console.error(`budgets crashed: ${error?.stack || error}`);
        process.exit(2);
    });
    process.on('unhandledRejection', (reason) => {
        console.error(`budgets crashed (unhandled rejection): ${reason?.stack || reason}`);
        process.exit(2);
    });
    for (const [signal, code] of [['SIGINT', 130], ['SIGTERM', 143], ['SIGHUP', 129]]) process.once(signal, () => process.exit(code));
}

async function startServer(port, buildDir, pending) {
    const origin = `http://127.0.0.1:${port}`;
    if (await answers(`${origin}/up`)) {
        const pids = String(spawnSync('lsof', ['-nP', `-iTCP:${port}`, '-sTCP:LISTEN', '-t'], { encoding: 'utf8' }).stdout ?? '').trim().split('\n').filter(Boolean);
        console.error(`Port ${port} already answers${pids.length ? ` (PID ${pids.join(', ')})` : ''}: pass --port=<n>. This script only stops the server it starts.`);
        return null;
    }
    const env = {
        PHP_CLI_SERVER_WORKERS: '4',
        APP_ENV: 'production',
        APP_DEBUG: 'false',
        SUMMIT_D2_BUILD_DIR: buildDir,
        SUMMIT_D2_HOT_FILE: MISSING_HOT,
        SUMMIT_DESIGN_SWITCHER: 'false',
        SUMMIT_SHOW_PENDING: pending ? 'true' : 'false',
    };
    const own = join(ROOT, 'server.php');
    const router = existsSync(own) ? own : join(ROOT, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php');
    const child = spawn('php', ['-S', `127.0.0.1:${port}`, router], { cwd: join(ROOT, 'public'), env: { ...process.env, ...env }, stdio: ['ignore', 'ignore', 'pipe'], detached: true });
    let log = '';
    child.stderr.on('data', (d) => {
        log = (log + d).slice(-4000);
    });
    const server = { child, origin };
    live.server = server;
    for (let i = 0; i < 80; i += 1) {
        if (child.exitCode !== null) break;
        if ((await answers(`${origin}/up`, 2000)) === 200) return server;
        await sleep(250);
    }
    stopServerSync(server);
    console.error(`php -S on port ${port} did not come up:\n${log}`);
    return null;
}

// ------------------------------------------------------------------------------------------ measuring

async function measure(browser, origin, buildDir, target) {
    const context = await browser.newContext({
        viewport: { width: target.width, height: target.width < 768 ? 844 : 900 },
        isMobile: target.width < 768,
        hasTouch: target.width < 1024,
        deviceScaleFactor: 1,
        locale: BROWSER_LOCALE[target.locale] ?? 'en-GB',
        timezoneId: 'Asia/Dubai',
        serviceWorkers: 'block',
    });
    try {
        await context.addCookies([
            { name: 'summit_design', value: '2', url: origin },
            { name: 'summit_qa', value: '1', url: origin },
        ]);
        await context.route('**/*', (route) => {
            const host = (() => {
                try {
                    return new URL(route.request().url()).hostname;
                } catch {
                    return '';
                }
            })();
            return host === '127.0.0.1' || host === 'localhost' || host === '' ? route.continue() : route.abort();
        });
        const page = await context.newPage();
        const requested = new Map();
        const prefix = `/${buildDir}/`;
        page.on('response', (response) => {
            const type = response.request().resourceType();
            if (!['script', 'stylesheet', 'font'].includes(type)) return;
            const url = new URL(response.url());
            requested.set(url.pathname, { type, path: url.pathname, status: response.status(), built: url.pathname.startsWith(prefix) });
        });
        const response = await page.goto(origin + target.path, { waitUntil: 'load', timeout: 90_000 });
        const status = response?.status() ?? 0;
        // Scroll through the page so IntersectionObserver-driven chunks load, then settle.
        await page.evaluate(async () => {
            const step = Math.max(400, Math.round(window.innerHeight * 0.8));
            for (let y = 0; y < document.documentElement.scrollHeight; y += step) {
                window.scrollTo(0, y);
                await new Promise((r) => setTimeout(r, 200));
            }
            window.scrollTo(0, document.documentElement.scrollHeight);
            await new Promise((r) => setTimeout(r, 500));
            window.scrollTo(0, 0);
        });
        await page.waitForLoadState('networkidle', { timeout: 15_000 }).catch(() => {});
        const html = await page.evaluate(() => ({
            design: document.documentElement.getAttribute('data-design'),
            fontPreloads: [...document.querySelectorAll('link[rel~="preload"][as="font"]')].map((l) => l.getAttribute('href')),
            inlineJs: [...document.querySelectorAll('script:not([src])')].filter((s) => !/json/i.test(s.type || '')).map((s) => s.textContent),
            inlineCss: [...document.querySelectorAll('style')].map((s) => s.textContent),
            devServer: [...document.querySelectorAll('script[src], link[href]')].some((el) => /:5183\/|@vite\/client/.test(el.getAttribute('src') ?? el.getAttribute('href') ?? '')),
        }));
        const assets = [];
        for (const item of requested.values()) {
            const file = join(ROOT, 'public', decodeURIComponent(item.path));
            if (!item.built || !existsSync(file)) {
                assets.push({ ...item, bytes: null, gzip: 0, note: item.built ? 'not on disk' : 'not from the build' });
                continue;
            }
            const buffer = readFileSync(file);
            assets.push({ ...item, path: item.path.slice(prefix.length), bytes: buffer.length, gzip: item.type === 'font' ? buffer.length : gzipBytes(buffer) });
        }
        const inline = {
            js: html.inlineJs.reduce((n, t) => n + (t.trim() ? gzipBytes(Buffer.from(t)) : 0), 0),
            css: html.inlineCss.reduce((n, t) => n + (t.trim() ? gzipBytes(Buffer.from(t)) : 0), 0),
        };
        return { ...target, status, design: html.design, devServer: html.devServer, fontPreloadHrefs: html.fontPreloads, assets, inline, ...totals(assets, inline, html.fontPreloads.length) };
    } finally {
        await context.close();
    }
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

// ------------------------------------------------------------------------------------------ report

const kb = (n) => `${(n / KB).toFixed(1)} KB`;

function renderMarkdown(result) {
    const out = ['# Design 2 performance budgets', ''];
    out.push(`Generated by \`scripts/qa/budgets.mjs\` (brief 2.1 §6) on a production build (public/${result.buildDir}), ${result.generated}; pending copy ${result.pending ? 'shown' : 'hidden'}. Budgets per page: JavaScript ≤ ${kb(BUDGET.js)} gzip, CSS ≤ ${kb(BUDGET.css)} gzip, ≤ ${BUDGET.fontPreloads} preloaded fonts. Gzip level 9 of the built files the page requested after a scroll through it, plus inline scripts and styles.`, '');
    out.push(`Result: **${result.pass ? 'pass' : 'fail'}** (${result.pages.length} page loads, ${result.pages.filter((p) => p.over?.length).length} over budget, ${result.pages.filter((p) => p.error).length} failed).`, '');
    out.push('| Page | Locale | Width | HTTP | JS gzip | CSS gzip | Font preloads | Fonts loaded | Over |', '|---|---|---|---|---|---|---|---|---|');
    for (const p of result.pages) {
        if (p.error) out.push(`| ${p.slug} | ${p.locale} | ${p.width} | - | - | - | - | - | error: ${p.error} |`);
        else out.push(`| ${p.slug} | ${p.locale} | ${p.width} | ${p.status} | ${kb(p.js)} | ${kb(p.css)} | ${p.fontPreloads} | ${p.fonts} | ${p.over.join(', ') || '-'} |`);
    }
    out.push('');
    const max = (key) => result.pages.filter((p) => !p.error).reduce((m, p) => (p[key] > (m?.[key] ?? -1) ? p : m), null);
    for (const key of ['js', 'css']) {
        const p = max(key);
        if (!p) continue;
        out.push(`## Heaviest ${key.toUpperCase()}: ${p.slug} ${p.locale} ${p.width} px (${kb(p[key])})`, '');
        for (const a of p.assets.filter((x) => x.type === (key === 'js' ? 'script' : 'stylesheet')).sort((x, y) => y.gzip - x.gzip)) out.push(`- \`${a.path}\` ${kb(a.gzip)} gzip (${a.bytes === null ? a.note : kb(a.bytes)})`);
        if (p.inline[key]) out.push(`- inline ${key === 'js' ? 'scripts' : 'styles'} ${kb(p.inline[key])} gzip`);
        out.push('');
    }
    return `${out.join('\n')}\n`;
}

// ------------------------------------------------------------------------------------------ main

function parseArgs(argv) {
    const opt = { build: true, buildDir: 'build-d2-budgets', port: 8047, routes: null, locales: ['en', 'ar', 'ru'], widths: [1440, 390], pending: true, concurrency: 2, out: join(ROOT, 'storage/qa/budgets') };
    for (const arg of argv) {
        const m = /^--([a-z-]+)(?:=(.*))?$/u.exec(arg);
        if (!m) throw new Error(`unknown argument: ${arg}`);
        const [, key, value] = m;
        if (key === 'no-build') opt.build = false;
        else if (key === 'help') opt.help = true;
        else if (value === undefined) throw new Error(`--${key} needs a value`);
        else if (key === 'build-dir') {
            if (!BUILD_DIR_PATTERN.test(value)) throw new Error('--build-dir must name a public/ folder that starts with build-d2- (never build or build-d2)');
            opt.buildDir = value;
        } else if (key === 'port') {
            opt.port = Number(value);
            opt.portGiven = true;
        }
        else if (key === 'routes') opt.routes = value;
        else if (key === 'locales') opt.locales = value.split(',').map((s) => s.trim()).filter(Boolean);
        else if (key === 'widths') opt.widths = value.split(',').map(Number).filter((n) => n >= 320);
        else if (key === 'pending') opt.pending = !/^(off|false|0|no)$/iu.test(value);
        else if (key === 'concurrency') opt.concurrency = Math.max(1, Number(value) || 1);
        else if (key === 'out') opt.out = isAbsolute(value) ? value : join(ROOT, value);
        else throw new Error(`unknown option --${key}`);
    }
    return opt;
}

async function main() {
    let opt;
    try {
        opt = parseArgs(process.argv.slice(2));
    } catch (error) {
        console.error(error.message);
        return 2;
    }
    if (opt.help) {
        console.log('Usage: node scripts/qa/budgets.mjs [--no-build] [--build-dir=build-d2-<name>] [--port=8047] [--routes=<slugs>] [--locales=en,ar,ru] [--widths=1440,390] [--pending=on|off] [--concurrency=2] [--out=<path>]');
        return 0;
    }
    const routes = buildRoutes(readRegistry()).filter((r) => r.locale !== 'root' && !['styleguide', 'offline'].includes(r.slug) && (!r.designs || r.designs.includes('2')));
    const slugs = [...new Set(routes.map((r) => r.slug))];
    const patterns = opt.routes
        ? opt.routes.split(',').map((p) => new RegExp(`^${p.trim().replace(/[.+?^${}()|[\]\\]/gu, '\\$&').replaceAll('*', '.*')}$`, 'u'))
        : null;
    if (patterns && !slugs.some((s) => patterns.some((re) => re.test(s)))) {
        console.error(`no route matches ${opt.routes} (routes: ${slugs.join(', ')})`);
        return 2;
    }
    const targets = [];
    for (const r of routes) {
        if (!opt.locales.includes(r.locale) || (patterns && !patterns.some((re) => re.test(r.slug)))) continue;
        if (r.slug === 'calculators' && !opt.pending) continue; // the page renders only while pending copy is shown
        for (const width of opt.widths) targets.push({ slug: r.slug, locale: r.locale, path: r.path, expect: r.expect, width });
    }
    guardProcess();
    if (opt.build && !(await build(opt.buildDir))) return 2;
    if (!existsSync(join(ROOT, 'public', opt.buildDir, 'manifest.json'))) {
        console.error(`public/${opt.buildDir}/manifest.json is missing: run without --no-build.`);
        return 2;
    }
    // The machine is shared: without an explicit --port, the first free port from 8047 on.
    let port = opt.port;
    if (!opt.portGiven) {
        for (let i = 0; i < 30 && (await answers(`http://127.0.0.1:${port}/up`, 1500)); i += 1) port += 1;
    }
    const server = await startServer(port, opt.buildDir, opt.pending);
    if (!server) return 2;
    const { chromium } = await import('@playwright/test');
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    let done = 0;
    const pages = await pool(targets, opt.concurrency, async (target) => {
        let row;
        try {
            row = await measure(browser, server.origin, opt.buildDir, target);
            if (row.status !== target.expect) row.error = `HTTP ${row.status}, expected ${target.expect}`;
            else if (row.design !== '2') row.error = `served data-design=${row.design ?? 'none'}`;
            else if (row.devServer) row.error = 'the page loads the Vite dev server, not the build';
        } catch (error) {
            row = { ...target, error: String(error?.message ?? error).split('\n')[0] };
        }
        done += 1;
        console.log(`[${done}/${targets.length}] ${target.locale} ${target.slug} ${target.width}: ${row.error ? `error (${row.error})` : `JS ${kb(row.js)}, CSS ${kb(row.css)}, font preloads ${row.fontPreloads}${row.over.length ? `  OVER: ${row.over.join(', ')}` : ''}`}`);
        return row;
    });
    await browser.close();
    stopServerSync(server);
    const pass = pages.every((p) => !p.error && p.over.length === 0);
    const result = { generated: new Date().toISOString(), buildDir: opt.buildDir, pending: opt.pending, budget: BUDGET, pass, pages };
    mkdirSync(dirname(opt.out), { recursive: true });
    writeFileSync(`${opt.out}.json`, `${JSON.stringify(result, null, 1)}\n`);
    writeFileSync(`${opt.out}.md`, renderMarkdown(result));
    console.log(`\n${pass ? 'Within budget' : 'Over budget or failed'}. Report: ${relative(ROOT, `${opt.out}.md`)} and .json`);
    return pass ? 0 : pages.some((p) => p.error) && pages.every((p) => p.error || !p.over.length) ? 2 : 1;
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
    main().then((code) => process.exit(code));
}
