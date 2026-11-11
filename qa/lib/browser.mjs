/**
 * Browser contexts and page watching for the Design 2 harness.
 *
 * Every context carries the cookies summit_design=2 (Design 2) and summit_qa=1 (no floating design
 * switcher; never a header, which would break Turnstile's cross-origin requests), the browser locale of
 * the page language, Asia/Dubai time, and a stub for Vite's client so that other builders saving files
 * never full-reload a page mid-capture (the same stub as storage/app/d2/qa/lib.mjs).
 */
import { BASE_URL, IGNORED_HOSTS, LOCALES, VIEWPORTS } from './config.mjs';

const HMR_STUB = `const noop = () => {};
export const createHotContext = () => ({ data: {}, accept: noop, acceptExports: noop, dispose: noop, prune: noop, invalidate: noop, decline: noop, on: noop, off: noop, send: noop });
export const updateStyle = noop; export const removeStyle = noop; export const injectQuery = (url) => url;
export class ErrorOverlay {}`;

/**
 * A fresh context for one capture. variant: default (motion allowed) · reduced (prefers-reduced-motion)
 * · nojs (JavaScript disabled). `design` sets the summit_design cookie (Design 1 proof: 1).
 */
export async function newContext(browser, { width, locale = 'en', variant = 'default', baseURL = BASE_URL, design = '2', extra = {} }) {
    const context = await browser.newContext({
        ...VIEWPORTS[width],
        locale: (LOCALES[locale] ?? LOCALES.en).browser,
        timezoneId: 'Asia/Dubai',
        colorScheme: 'light',
        reducedMotion: variant === 'reduced' ? 'reduce' : 'no-preference',
        javaScriptEnabled: variant !== 'nojs',
        ...extra,
    });
    await context.addCookies([
        { name: 'summit_design', value: String(design), url: baseURL },
        { name: 'summit_qa', value: '1', url: baseURL },
    ]);
    await context.route(/\/@vite\/client(?:\?.*)?$/, (route) =>
        route.fulfill({ status: 200, contentType: 'text/javascript', body: HMR_STUB }),
    );
    return context;
}

const ignoredUrl = (url, hosts) => {
    try {
        const { hostname } = new URL(url);
        return hosts.some((h) => hostname === h || hostname.endsWith(`.${h}`));
    } catch {
        return false;
    }
};

/**
 * Collects console errors (and Alpine / CSP warnings, which are bugs in a CSP build), uncaught exceptions,
 * failed requests and HTTP >= 400 subresources from the moment it is attached (attach before goto).
 * The main document is exempt when its status is the expected one (the 404 page). `freeze()` stops
 * collecting (axe's own injected stylesheet trips the page CSP and must not count).
 */
export function watchPage(page, { expectStatus = 200, ignoreHosts = IGNORED_HOSTS, javaScript = true } = {}) {
    const errors = [];
    const warnings = [];
    const failedUrls = new Set();
    const expectedDocs = new Set();
    const inflight = new Set();
    let frozen = false;
    const ignored = (url) => ignoredUrl(url, ignoreHosts);

    page.on('console', (msg) => {
        if (frozen) return;
        const type = msg.type();
        const text = msg.text();
        const { url = '', lineNumber } = msg.location() ?? {};
        if (ignored(url) || ignoreHosts.some((h) => text.includes(h))) return;
        const where = url ? `  (${url}${Number.isInteger(lineNumber) ? `:${lineNumber + 1}` : ''})` : '';
        if (type === 'error' || type === 'assert') {
            if (expectedDocs.has(url) && /status of \d{3}/.test(text)) return;
            if (failedUrls.has(url) && /^Failed to load resource/.test(text)) return; // listed once as a failed request
            errors.push(`console.${type}: ${text}${where}`);
        } else if (type === 'warning') {
            if (/alpine|content security policy|csp/i.test(text)) errors.push(`console.warning: ${text}${where}`);
            else warnings.push(`console.warning: ${text}${where}`);
        }
    });
    page.on('pageerror', (error) => {
        if (!frozen) errors.push(`pageerror: ${String(error?.stack || error).split('\n').slice(0, 3).join(' | ')}`);
    });
    page.on('request', (request) => {
        if (!ignored(request.url())) inflight.add(request);
    });
    page.on('requestfinished', (request) => inflight.delete(request));
    page.on('requestfailed', (request) => {
        inflight.delete(request);
        if (frozen || ignored(request.url())) return;
        const failure = request.failure()?.errorText ?? 'failed';
        if (/ERR_ABORTED/.test(failure)) return; // navigation or prefetch cancelled
        if (!javaScript && request.resourceType() === 'script') return; // JavaScript disabled: script fetches are blocked
        failedUrls.add(request.url());
        errors.push(`requestfailed: ${failure} ${request.resourceType()} ${request.url()}`);
    });
    page.on('response', (response) => {
        const status = response.status();
        if (frozen || status < 400 || ignored(response.url())) return;
        const request = response.request();
        if (request.isNavigationRequest() && response.frame() === page.mainFrame() && status === expectStatus) {
            expectedDocs.add(response.url());
            return;
        }
        failedUrls.add(response.url());
        errors.push(`http ${status}: ${request.resourceType()} ${response.url()}`);
    });

    return {
        errors,
        warnings,
        freeze() {
            frozen = true;
        },
        /** Resolves once no request has been in flight for 300 ms (or after `timeout`). */
        async idle(timeout = 5000) {
            const start = Date.now();
            let quiet = 0;
            while (Date.now() - start < timeout) {
                if (inflight.size === 0) {
                    quiet ||= Date.now();
                    if (Date.now() - quiet >= 300) return true;
                } else {
                    quiet = 0;
                }
                await new Promise((resolve) => setTimeout(resolve, 50));
            }
            return false;
        },
    };
}

/** Waits until the page clock (performance.now(), from navigation start) reaches `ms`. */
export async function untilPageTime(page, ms) {
    const now = await page.evaluate(() => performance.now());
    if (now < ms) await page.waitForTimeout(ms - now);
}

/**
 * Scrolls through the whole page from Node (works with JavaScript disabled), so intersection-observer
 * entrances, lazy tools and lazy images run, then returns to the top. Returns the number of steps.
 */
export async function scrollThrough(page, { pauseMs = 60, maxSteps = 120, decode = true } = {}) {
    await page.evaluate(() => {
        for (const img of document.querySelectorAll('img[loading="lazy"]')) img.loading = 'eager';
    });
    let steps = 0;
    for (;;) {
        const { y, max, step } = await page.evaluate(() => {
            const h = Math.max(document.documentElement.scrollHeight, document.body ? document.body.scrollHeight : 0);
            return { y: window.scrollY, max: h - window.innerHeight, step: Math.max(240, Math.round(window.innerHeight * 0.8)) };
        });
        if (y >= max - 1 || steps >= maxSteps) break;
        await page.evaluate((top) => window.scrollTo({ top, left: 0, behavior: 'instant' }), Math.min(max, y + step));
        steps += 1;
        await page.waitForTimeout(pauseMs);
    }
    await page.waitForTimeout(200);
    await page.evaluate(() => window.scrollTo({ top: 0, left: 0, behavior: 'instant' }));
    await page.waitForTimeout(150);
    if (decode) {
        await Promise.race([
            page.evaluate(() => Promise.all([...document.images].filter((i) => !i.complete).map((i) => i.decode().catch(() => null)))).catch(() => null),
            page.waitForTimeout(4000),
        ]);
    }
    return steps;
}
