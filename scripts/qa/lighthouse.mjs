#!/usr/bin/env node
/**
 * Lighthouse gate (mobile form factor, default simulated throttling) on the installed Chrome.
 *
 *   npm run qa:lighthouse                        /en/ /ar/ /ru/
 *   npm run qa:lighthouse -- --url=/en/           one page (site path or absolute URL)
 *   npm run qa:lighthouse -- --runs=3             median of 3 runs per page
 *   npm run qa:lighthouse -- --no-compress        measure the raw server (see below)
 *
 * Env: QA_BASE_URL (default http://127.0.0.1:8000), CHROME_PATH.
 * Reports: storage/app/qa/lighthouse/<page>.report.{html,json} and summary.json.
 * Exit 1 when performance < 95, accessibility / best practices / SEO < 100, or a budget is
 * exceeded (LCP <= 2.0 s, CLS <= 0.05, first-party JS <= 35 KiB compressed, Home <= 600 KiB).
 * Exit 2 when the run itself cannot happen (no server, Chrome missing).
 *
 * Compression: `php artisan serve` never compresses responses, while production sits behind
 * Cloudflare. When the server sends text uncompressed, pages are measured through a local gzip
 * proxy (same host, random port) so transfer sizes and simulated timings match a compressing
 * CDN. --no-compress disables the proxy, --compress forces it.
 */
import { existsSync, mkdirSync, writeFileSync } from 'node:fs';
import http from 'node:http';
import path from 'node:path';
import { pipeline } from 'node:stream';
import { fileURLToPath } from 'node:url';
import zlib from 'node:zlib';
import * as chromeLauncher from 'chrome-launcher';
import lighthouse, { generateReport } from 'lighthouse';
import { computeMedianRun } from 'lighthouse/core/lib/median-run.js';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const OUT_DIR = path.join(ROOT, 'storage/app/qa/lighthouse');
const CHROME_PATH = process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const BASE_URL = process.env.QA_BASE_URL || 'http://127.0.0.1:8000';
const DEFAULT_PATHS = ['/en/', '/ar/', '/ru/'];
const LOCALES = ['en', 'ar', 'ru'];

const CATEGORIES = ['performance', 'accessibility', 'best-practices', 'seo'];
const MIN_SCORE = { performance: 95, accessibility: 100, 'best-practices': 100, seo: 100 };
const KIB = 1024;
const BUDGET = { lcpMs: 2000, cls: 0.05, firstPartyJs: 35 * KIB, homeBytes: 600 * KIB };

// ------------------------------------------------------------------------------------ options
function usage(code) {
  console.log(
    'Usage: node scripts/qa/lighthouse.mjs [--url=<path|url>] [--runs=<n>] [--no-compress|--compress] [--verbose]',
  );
  process.exit(code);
}

function parseArgs(argv) {
  const opts = { urls: [], runs: 1, compress: 'auto', verbose: false };
  for (let i = 0; i < argv.length; i += 1) {
    const arg = argv[i];
    const eq = arg.indexOf('=');
    const flag = eq === -1 ? arg : arg.slice(0, eq);
    const value = () => {
      const v = eq === -1 ? argv[(i += 1)] : arg.slice(eq + 1);
      if (!v) usage(2);
      return v;
    };
    if (flag === '--url') opts.urls.push(value());
    else if (flag === '--runs') opts.runs = Math.max(1, Number.parseInt(value(), 10) || 1);
    else if (flag === '--no-compress') opts.compress = 'off';
    else if (flag === '--compress') opts.compress = 'on';
    else if (flag === '--verbose') opts.verbose = true;
    else if (flag === '--help' || flag === '-h') usage(0);
    else {
      console.error(`Unknown option: ${arg}`);
      usage(2);
    }
  }
  return opts;
}

const resolveUrl = (target) => {
  if (/^https?:\/\//i.test(target)) return new URL(target);
  const base = BASE_URL.endsWith('/') ? BASE_URL : `${BASE_URL}/`;
  return new URL(target.replace(/^\//, ''), base);
};

/** /en/ -> home-en, /ar/styleguide/ -> styleguide-ar, / -> root (matches the screenshot names). */
const slugOf = (url) => {
  const segments = url.pathname.split('/').filter(Boolean);
  const locale = LOCALES.includes(segments[0]) ? segments.shift() : null;
  const name = segments.length ? segments.join('-') : locale ? 'home' : 'root';
  return [name, locale].filter(Boolean).join('-').replace(/[^a-z0-9-]+/gi, '_');
};

// ------------------------------------------------------------------------------------ gzip proxy
const COMPRESSIBLE = /^(text\/|application\/(javascript|json|ld\+json|manifest\+json|xml)|image\/svg\+xml)/i;

function startCompressionProxy(upstream) {
  const agent = new http.Agent({ keepAlive: true, maxSockets: 8 });
  const server = http.createServer((req, res) => {
    // Keep the browser's Host header so Laravel builds asset URLs for the proxy origin.
    const headers = { ...req.headers };
    delete headers['accept-encoding'];
    // The internal design switcher (SUMMIT_DESIGN_SWITCHER) is never part of a Lighthouse run.
    headers['x-summit-qa'] = '1';
    const forward = http.request(
      { hostname: upstream.hostname, port: upstream.port || 80, method: req.method, path: req.url, headers, agent },
      (up) => {
        const type = String(up.headers['content-type'] ?? '');
        const accepts = String(req.headers['accept-encoding'] ?? '');
        const compress =
          COMPRESSIBLE.test(type) &&
          !up.headers['content-encoding'] &&
          /\bgzip\b/.test(accepts) &&
          req.method !== 'HEAD' &&
          ![204, 304].includes(up.statusCode);
        const out = { ...up.headers };
        if (compress) {
          delete out['content-length'];
          out['content-encoding'] = 'gzip';
          out.vary = out.vary ? `${out.vary}, Accept-Encoding` : 'Accept-Encoding';
        }
        res.writeHead(up.statusCode, up.statusMessage, out);
        if (compress) pipeline(up, zlib.createGzip({ level: 6 }), res, () => {});
        else pipeline(up, res, () => {});
      },
    );
    forward.on('error', (error) => {
      if (!res.headersSent) res.writeHead(502, { 'content-type': 'text/plain' });
      res.end(`qa proxy: ${error.message}`);
    });
    pipeline(req, forward, () => {});
  });
  return new Promise((resolve, reject) => {
    server.once('error', reject);
    server.listen(0, '127.0.0.1', () => resolve({ server, agent, port: server.address().port }));
  });
}

async function probe(url) {
  try {
    const res = await fetch(url, {
      headers: { 'accept-encoding': 'gzip, deflate, br' },
      redirect: 'manual',
      signal: AbortSignal.timeout(15_000),
    });
    await res.arrayBuffer().catch(() => null);
    return { ok: true, status: res.status, encoding: res.headers.get('content-encoding') };
  } catch (error) {
    return { ok: false, error: error.cause?.code ?? error.message };
  }
}

// ------------------------------------------------------------------------------------ results
function summarise(target, lhr) {
  const score = (id) => {
    const s = lhr.categories[id]?.score;
    return typeof s === 'number' ? Math.round(s * 100) : null;
  };
  const numeric = (id) => lhr.audits[id]?.numericValue ?? null;
  const summary = lhr.audits['resource-summary']?.details?.items ?? [];
  const bytesOf = (type) => summary.find((i) => i.resourceType === type)?.transferSize ?? 0;
  const requests = lhr.audits['network-requests']?.details?.items ?? [];
  const origin = new URL(lhr.mainDocumentUrl || lhr.finalDisplayedUrl || target.measured.href).origin;
  const sameOrigin = (r) => {
    try {
      return new URL(r.url).origin === origin;
    } catch {
      return false;
    }
  };
  const firstPartyJs = requests
    .filter((r) => r.resourceType === 'Script' && sameOrigin(r))
    .reduce((sum, r) => sum + (r.transferSize || 0), 0);
  const uncompressed = requests.filter(
    (r) => sameOrigin(r) && /^(Document|Script|Stylesheet|Fetch|XHR)$/.test(r.resourceType) && r.resourceSize > 1400 && r.transferSize >= r.resourceSize,
  ).length;

  const failing = {};
  for (const id of CATEGORIES) {
    const refs = lhr.categories[id]?.auditRefs ?? [];
    failing[id] = refs
      .filter((ref) => ref.weight > 0)
      .map((ref) => lhr.audits[ref.id])
      .filter((a) => a && typeof a.score === 'number' && a.score < (id === 'performance' ? 0.9 : 1))
      .map((a) => (id === 'performance' ? `${a.id} ${a.displayValue ?? ''} (score ${a.score})`.trim() : a.id));
  }

  return {
    page: target.slug,
    url: target.display.href,
    measuredUrl: target.measured.href,
    scores: Object.fromEntries(CATEGORIES.map((id) => [id, score(id)])),
    lcpMs: numeric('largest-contentful-paint'),
    cls: numeric('cumulative-layout-shift'),
    tbtMs: numeric('total-blocking-time'),
    totalBytes: numeric('total-byte-weight'),
    scriptBytes: bytesOf('script'),
    firstPartyScriptBytes: firstPartyJs,
    fontBytes: bytesOf('font'),
    uncompressedTextResponses: uncompressed,
    runtimeError: lhr.runtimeError ? `${lhr.runtimeError.code}: ${lhr.runtimeError.message}` : null,
    warnings: lhr.runWarnings ?? [],
    failingAudits: failing,
  };
}

function gates(row) {
  const problems = [];
  if (row.runtimeError) problems.push(`Lighthouse could not measure the page: ${row.runtimeError}`);
  for (const id of CATEGORIES) {
    const s = row.scores[id];
    if (s === null || s < MIN_SCORE[id]) {
      const detail = row.failingAudits[id]?.length ? ` [${row.failingAudits[id].join(', ')}]` : '';
      problems.push(`${id} ${s ?? 'n/a'} < ${MIN_SCORE[id]}${detail}`);
    }
  }
  if (row.lcpMs === null || row.lcpMs > BUDGET.lcpMs) problems.push(`LCP ${fmtMs(row.lcpMs)} > 2.0 s`);
  if (row.cls === null || row.cls > BUDGET.cls) problems.push(`CLS ${fmtCls(row.cls)} > 0.05`);
  if (row.firstPartyScriptBytes > BUDGET.firstPartyJs) {
    problems.push(`first-party JS ${fmtKib(row.firstPartyScriptBytes)} > 35 KiB compressed`);
  }
  if (row.page.startsWith('home-') && (row.totalBytes === null || row.totalBytes > BUDGET.homeBytes)) {
    problems.push(`page weight ${fmtKib(row.totalBytes)} > 600 KiB (Home budget)`);
  }
  return problems;
}

const fmtMs = (ms) => (ms === null ? 'n/a' : ms >= 1000 ? `${(ms / 1000).toFixed(2)} s` : `${Math.round(ms)} ms`);
const fmtCls = (v) => (v === null ? 'n/a' : v.toFixed(3));
const fmtKib = (b) => (b === null ? 'n/a' : `${(b / KIB).toFixed(1)} KiB`);

function printTable(rows) {
  const head = ['Page', 'Perf', 'A11y', 'BP', 'SEO', 'LCP', 'CLS', 'TBT', 'Total bytes', 'JS (1st-party / all)', 'Fonts'];
  const body = rows.map((r) => [
    r.page,
    ...CATEGORIES.map((id) => String(r.scores[id] ?? 'n/a')),
    fmtMs(r.lcpMs),
    fmtCls(r.cls),
    fmtMs(r.tbtMs),
    fmtKib(r.totalBytes),
    `${fmtKib(r.firstPartyScriptBytes)} / ${fmtKib(r.scriptBytes)}`,
    fmtKib(r.fontBytes),
  ]);
  const widths = head.map((h, i) => Math.max(h.length, ...body.map((row) => row[i].length)));
  const line = (cells) => cells.map((c, i) => (i === 0 ? c.padEnd(widths[i]) : c.padStart(widths[i]))).join('  ');
  console.log(`\n${line(head)}\n${widths.map((w) => '-'.repeat(w)).join('  ')}`);
  for (const row of body) console.log(line(row));
  console.log(
    '\nGates: performance >= 95; accessibility, best practices, SEO = 100; LCP <= 2.0 s; CLS <= 0.05;' +
      ' first-party JS <= 35 KiB compressed; Home <= 600 KiB.',
  );
}

// ------------------------------------------------------------------------------------ main
async function main() {
  const opts = parseArgs(process.argv.slice(2));
  const targets = (opts.urls.length ? opts.urls : DEFAULT_PATHS).map((u) => {
    const display = resolveUrl(u);
    return { display, measured: display, slug: slugOf(display) };
  });

  if (!existsSync(CHROME_PATH)) {
    console.error(`Google Chrome not found at ${CHROME_PATH} (set CHROME_PATH).`);
    return 2;
  }
  const first = await probe(targets[0].display.href);
  if (!first.ok) {
    console.error(
      `Nothing answers at ${targets[0].display.origin} (${first.error}). Start the site (php artisan serve --port=8000) or set QA_BASE_URL.`,
    );
    return 2;
  }
  const notes = [];
  if (existsSync(path.join(ROOT, 'public/hot'))) {
    notes.push('The Vite dev server is active (public/hot): pages load unbundled dev assets. Run `npm run build` and stop `npm run dev` for representative numbers.');
  }

  let proxy = null;
  const useProxy = opts.compress === 'on' || (opts.compress === 'auto' && !first.encoding);
  if (useProxy) {
    const upstream = targets[0].display;
    if (upstream.protocol !== 'http:' || targets.some((t) => t.display.origin !== upstream.origin)) {
      notes.push('Compression proxy skipped: it only fronts a single plain-http origin.');
    } else {
      proxy = await startCompressionProxy(upstream);
      for (const t of targets) {
        t.measured = new URL(t.display.href);
        t.measured.hostname = '127.0.0.1';
        t.measured.port = String(proxy.port);
      }
      notes.push(
        `${upstream.origin} sends text uncompressed, so pages were measured through a local gzip proxy on port ${proxy.port}` +
          ' (production is compressed by Cloudflare). Use --no-compress to measure the raw server.',
      );
    }
  } else if (!first.encoding) {
    notes.push(`${targets[0].display.origin} sends text uncompressed; transfer sizes and timings are pessimistic.`);
  }

  mkdirSync(OUT_DIR, { recursive: true });
  // One retry: a Chrome started right after another one was killed occasionally fails to come up.
  const launch = () =>
    chromeLauncher.launch({
      chromePath: CHROME_PATH,
      chromeFlags: ['--headless=new', '--no-first-run', '--no-default-browser-check', '--disable-extensions'],
      logLevel: 'silent',
    });
  const chrome = await launch().catch(async (error) => {
    console.error(`Chrome did not start (${error.message}); retrying once.`);
    await new Promise((resolve) => setTimeout(resolve, 1500));
    return launch();
  });
  const stop = async () => {
    try {
      await chrome.kill();
    } catch {
      // already gone
    }
    if (proxy) {
      proxy.agent.destroy();
      await new Promise((resolve) => proxy.server.close(resolve));
    }
  };
  process.once('SIGINT', () => stop().finally(() => process.exit(130)));

  const rows = [];
  try {
    for (const target of targets) {
      const lhrs = [];
      for (let run = 1; run <= opts.runs; run += 1) {
        process.stdout.write(`Lighthouse ${target.display.href}${opts.runs > 1 ? ` (run ${run}/${opts.runs})` : ''} … `);
        const result = await lighthouse(target.measured.href, {
          port: chrome.port,
          output: 'json',
          logLevel: opts.verbose ? 'info' : 'error',
          onlyCategories: CATEGORIES,
          formFactor: 'mobile', // default mobile screen emulation and simulated Slow 4G / 4x CPU
        });
        if (!result?.lhr) throw new Error(`Lighthouse returned no result for ${target.measured.href}`);
        lhrs.push(result.lhr);
        console.log(result.lhr.runtimeError ? result.lhr.runtimeError.code : 'done');
      }
      // The median needs FCP and TTI from every run; errored runs are left out of it.
      const valid = lhrs.filter(
        (l) =>
          !l.runtimeError &&
          Number.isFinite(l.audits['first-contentful-paint']?.numericValue) &&
          Number.isFinite(l.audits.interactive?.numericValue),
      );
      const lhr = valid.length > 1 ? computeMedianRun(valid) : (valid[0] ?? lhrs[lhrs.length - 1]);
      writeFileSync(path.join(OUT_DIR, `${target.slug}.report.html`), generateReport(lhr, 'html'));
      writeFileSync(path.join(OUT_DIR, `${target.slug}.report.json`), JSON.stringify(lhr, null, 2));
      rows.push(summarise(target, lhr));
    }
  } finally {
    await stop();
  }

  printTable(rows);
  let failed = false;
  console.log('');
  for (const row of rows) {
    row.gateFailures = gates(row);
    failed ||= row.gateFailures.length > 0;
    console.log(row.gateFailures.length ? `FAIL ${row.page}\n  - ${row.gateFailures.join('\n  - ')}` : `PASS ${row.page}`);
    if (row.uncompressedTextResponses && !proxy) {
      console.log(`  note: ${row.uncompressedTextResponses} first-party text response(s) were served uncompressed`);
    }
  }
  for (const note of notes) console.log(`\nNote: ${note}`);
  writeFileSync(
    path.join(OUT_DIR, 'summary.json'),
    `${JSON.stringify({ generatedAt: new Date().toISOString(), baseUrl: BASE_URL, proxied: Boolean(proxy), budget: BUDGET, minScore: MIN_SCORE, rows }, null, 2)}\n`,
  );
  console.log(`\nReports: ${path.relative(ROOT, OUT_DIR)}/<page>.report.html (and .json, summary.json)`);
  return failed ? 1 : 0;
}

main().then(
  (code) => process.exit(code),
  (error) => {
    console.error(`qa:lighthouse could not run: ${error?.stack || error}`);
    process.exit(2);
  },
);
