/**
 * Node-side QA helpers shared by the specs: page registry, URL resolution, console/network
 * watching, settling (fonts + hero intro) and screenshots.
 */
import { mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';
import { installRuntime } from './runtime.mjs';

export const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../..');
export const QA_DIR = path.join(ROOT, 'storage/app/qa');
export const SCREENS_DIR = path.join(QA_DIR, 'screens');
export const AXE_DIR = path.join(QA_DIR, 'axe');

/** config/summit.php locales, plus the browser locale used when visiting them. */
export const LOCALES = {
  en: { dir: 'ltr', browser: 'en-GB' },
  ar: { dir: 'rtl', browser: 'ar-AE' },
  ru: { dir: 'ltr', browser: 'ru-RU' },
};
export const DEFAULT_LOCALE = 'en';

/** Third-party origins outside our control (Cloudflare Turnstile). */
export const IGNORED_HOSTS = ['challenges.cloudflare.com'];

/** ContentRepository renders missing copy as ⟦key⟧ outside production. */
export const MISSING_MARKER = '⟦';

/** Hero "The File" timeline ends at about 1450 ms; screenshots wait a little longer. */
export const INTRO_MS = 1600;

const DEFAULT_PATHS = ['/en/', '/ar/', '/ru/', '/en/styleguide/', '/ar/styleguide/', '/ru/styleguide/'];

/**
 * One page under test. QA_PAGES overrides the default list with comma-separated paths, each
 * optionally suffixed with the expected status: QA_PAGES="/,/en/missing-page/=404".
 */
export function describePath(rawPath, status = 200) {
  const url = new URL(rawPath, 'http://qa.invalid');
  const segments = url.pathname.split('/').filter(Boolean);
  const locale = segments[0] in LOCALES ? segments[0] : null;
  const rest = locale ? segments.slice(1) : segments;
  const name = rest.length ? rest.join('-') : locale ? 'home' : 'root';
  const effective = locale ?? DEFAULT_LOCALE;
  return {
    path: `${url.pathname}${url.search}`,
    name,
    locale,
    lang: effective,
    dir: LOCALES[effective].dir,
    browserLocale: LOCALES[effective].browser,
    status,
    slug: [name, locale].filter(Boolean).join('-'),
    isHome: Boolean(locale) && rest.length === 0,
  };
}

export function qaPages() {
  const raw = (process.env.QA_PAGES ?? '').trim();
  const entries = raw ? raw.split(',').map((s) => s.trim()).filter(Boolean) : DEFAULT_PATHS;
  return entries.map((entry) => {
    const m = entry.match(/^(.*?)(?:=(\d{3}))?$/);
    return describePath(m[1] || '/', m[2] ? Number(m[2]) : 200);
  });
}

/** Resolves a site path against the base URL, keeping any sub-directory in the base. */
export function pageUrl(baseURL, sitePath) {
  const base = baseURL.endsWith('/') ? baseURL : `${baseURL}/`;
  return new URL(sitePath.replace(/^\//, ''), base).href;
}

export const isIgnoredUrl = (url, hosts = IGNORED_HOSTS) => {
  try {
    const { hostname } = new URL(url);
    return hosts.some((h) => hostname === h || hostname.endsWith(`.${h}`));
  } catch {
    return false;
  }
};

/**
 * Collects console errors, uncaught exceptions, failed requests and HTTP error responses from the
 * moment it is attached (attach before page.goto). The main document is exempt when its status is
 * the one the test expects (a deliberate 404 page).
 */
export function watchPage(page, { expectStatus = 200, ignoreHosts = IGNORED_HOSTS } = {}) {
  const found = { console: [], pageErrors: [], failedRequests: [] };
  const inflight = new Set();
  const expectedDocuments = new Set();
  const failedUrls = new Set();
  const consoleRaw = [];
  const ignored = (url) => isIgnoredUrl(url, ignoreHosts);

  page.on('console', (msg) => {
    if (msg.type() !== 'error' && msg.type() !== 'assert') return;
    const { url = '', lineNumber } = msg.location() ?? {};
    const text = msg.text();
    if (ignored(url) || ignoreHosts.some((h) => text.includes(h))) return;
    if (expectedDocuments.has(url) && /status of \d{3}/.test(text)) return;
    consoleRaw.push({ url, text, line: `${text}${url ? `  (${url}${Number.isInteger(lineNumber) ? `:${lineNumber + 1}` : ''})` : ''}` });
  });
  // Chrome also logs "Failed to load resource" for a failed request; list that failure once.
  const consoleErrors = () =>
    consoleRaw.filter((c) => !(failedUrls.has(c.url) && /^Failed to load resource/.test(c.text))).map((c) => c.line);
  page.on('pageerror', (error) => found.pageErrors.push(String(error?.stack || error)));
  page.on('request', (request) => {
    if (!ignored(request.url())) inflight.add(request);
  });
  page.on('requestfinished', (request) => inflight.delete(request));
  page.on('requestfailed', (request) => {
    inflight.delete(request);
    if (ignored(request.url())) return;
    failedUrls.add(request.url());
    found.failedRequests.push(`${request.failure()?.errorText ?? 'failed'}  ${request.resourceType()}  ${request.url()}`);
  });
  page.on('response', (response) => {
    const status = response.status();
    if (status < 400 || ignored(response.url())) return;
    const request = response.request();
    if (request.isNavigationRequest() && response.frame() === page.mainFrame() && status === expectStatus) {
      expectedDocuments.add(response.url());
      return;
    }
    failedUrls.add(response.url());
    found.failedRequests.push(`HTTP ${status}  ${request.resourceType()}  ${response.url()}`);
  });

  return {
    found,
    count: () => consoleErrors().length + found.pageErrors.length + found.failedRequests.length,
    report() {
      found.console = consoleErrors();
      const lines = [];
      const section = (title, list) => {
        if (list.length) lines.push(`${title} (${list.length}):`, ...list.map((l) => `  - ${l}`));
      };
      section('Console errors', found.console);
      section('Uncaught exceptions', found.pageErrors);
      section('Failed requests', found.failedRequests);
      return lines.join('\n');
    },
    /** Resolves once no (non-ignored) request has been in flight for 300 ms. */
    async idle(timeout = 8000) {
      const start = Date.now();
      let quietSince = 0;
      while (Date.now() - start < timeout) {
        if (inflight.size === 0) {
          quietSince ||= Date.now();
          if (Date.now() - quietSince >= 300) return true;
        } else {
          quietSince = 0;
        }
        await new Promise((resolve) => setTimeout(resolve, 50));
      }
      return false;
    },
  };
}

/**
 * Navigates with the in-page runtime installed as an init script (before any page script, so the
 * per-frame overflow watch sees the hero intro from its first frame). Returns the main response.
 */
export async function openPage(page, baseURL, entry) {
  await page.addInitScript(installRuntime);
  await page.addInitScript((w) => window.__qa.watchOverflow(w), widthOf(page));
  const response = await page.goto(pageUrl(baseURL, entry.path), { waitUntil: 'load' });
  await page.evaluate(installRuntime); // no-op unless the init script could not run
  return response;
}

export const statusMessage = (entry, response) =>
  `GET ${entry.path} answered ${response ? response.status() : 'nothing'} (expected ${entry.status})`;

export async function fontsReady(page) {
  await page.evaluate(() => document.fonts.ready.then(() => document.fonts.status));
}

/**
 * Fonts loaded, then (with intro) at least INTRO_MS after DOMContentLoaded, then every running
 * finite animation finished. Scroll-driven and infinite animations are ignored here.
 */
export async function settle(page, { intro = true } = {}) {
  await fontsReady(page);
  if (intro) {
    await page.waitForFunction(
      (ms) => {
        const nav = performance.getEntriesByType('navigation')[0];
        return performance.now() >= (nav ? nav.domContentLoadedEventStart : 0) + ms;
      },
      INTRO_MS,
      { timeout: 15_000, polling: 100 },
    );
  }
  await page.evaluate(() => window.__qa.settleAnimations(null, 4000));
}

/** Forces lazy images to load so full-page screenshots are complete (watched for failures too). */
export async function loadLazyImages(page) {
  await page.evaluate(async () => {
    const lazy = [...document.querySelectorAll('img[loading="lazy"]')];
    for (const img of lazy) img.loading = 'eager';
    await Promise.race([
      Promise.all(lazy.map((img) => (img.complete ? null : img.decode().catch(() => null)))),
      new Promise((resolve) => setTimeout(resolve, 5000)),
    ]);
  });
}

export const screenPath = (file) => path.join(SCREENS_DIR, file);

/** First viewport at device scale (1170×2532 on the 390 project). */
export async function foldShot(page, file) {
  mkdirSync(SCREENS_DIR, { recursive: true });
  await page.screenshot({ path: screenPath(file) });
}

/**
 * Full page at CSS-pixel scale. Chrome wraps captures taller than 16384 output pixels (the bottom
 * repeats the top), so tall pages are captured in clipped segments and stitched with sharp.
 */
const SEGMENT = 4096;
export async function fullShot(page, file) {
  mkdirSync(SCREENS_DIR, { recursive: true });
  const { width, height } = await page.evaluate(() => ({
    width: document.documentElement.clientWidth || innerWidth,
    height: Math.max(document.documentElement.scrollHeight, document.body ? document.body.scrollHeight : 0),
  }));
  const target = screenPath(file);
  if (height <= SEGMENT) {
    await page.screenshot({ path: target, fullPage: true, scale: 'css', clip: { x: 0, y: 0, width, height } });
    return;
  }
  const parts = [];
  for (let top = 0; top < height; top += SEGMENT) {
    const clip = { x: 0, y: top, width, height: Math.min(SEGMENT, height - top) };
    parts.push({ input: await page.screenshot({ fullPage: true, scale: 'css', clip }), top, left: 0 });
  }
  await sharp({ create: { width, height, channels: 4, background: '#ffffff' } })
    .composite(parts)
    .png()
    .toFile(target);
}

export function writeJson(file, data) {
  mkdirSync(path.dirname(file), { recursive: true });
  writeFileSync(file, `${JSON.stringify(data, null, 2)}\n`);
}

export const widthOf = (page) => page.viewportSize()?.width ?? 0;

/** Non-failing finding: an HTML-report annotation plus a terminal line. */
export function warn(testInfo, text) {
  testInfo.annotations.push({ type: 'warning', description: text });
  console.log(`[warning] ${testInfo.project.name} ${testInfo.titlePath.slice(1).join(' › ')}: ${text}`);
}
