#!/usr/bin/env node
/**
 * Google Analytics 4: consent and events in a real browser (docs/briefs/design2-only-black-header-ga4.md C7).
 *
 *   QA_BASE_URL=http://127.0.0.1:8044 node scripts/qa/ga4-consent.mjs [--out=<dir>] [--real-gtag]
 *
 * Needs a server of this tree in production mode with a valid test ID, for example (from public/):
 *   APP_ENV=production APP_DEBUG=false GA4_MEASUREMENT_ID=G-TEST123ABC SUMMIT_SHOW_PENDING=true SUMMIT_PWA=false \
 *     php -S 127.0.0.1:8044 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
 * and the Design 2 build (public/build-d2). SUMMIT_SHOW_PENDING=true only opens the calculators page (pending copy).
 *
 * Google is never contacted: https://www.googletagmanager.com/gtag/js is answered by a local stub, so the checks read
 * what the page hands to gtag (window.dataLayer: the consent states, the config, every event and its parameters).
 * Every other request to another host is blocked. --real-gtag loads Google's real gtag.js instead (network needed)
 * and checks the _ga cookie itself: none before Accept or after Reject, one after Accept.
 *
 * Checks: an untouched bar and Reject leave consent denied (no granted update, no _ga cookie, ad storage never
 * granted); Accept grants analytics storage only, is stored 6 months and applied before the first hit of the next
 * page; Reject after Accept deletes the _ga cookies; the footer's Privacy Settings button reopens the bar (focus on its
 * title, Escape closes it, focus returns); page_location has no query except utm_*; whatsapp_click, phone_click,
 * email_click, language_change and calculator_used fire once each with exactly their parameters; the bar never covers
 * the tab bar or the floating WhatsApp button at 390 px; axe finds nothing in the bar; no console errors (a CSP
 * violation is one). Screenshots of the bar at 1440 and 390 px in EN, AR and RU go to --out (default storage/qa/ga4).
 */
import { chromium } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import fs from 'node:fs';
import path from 'node:path';

const BASE = (process.env.QA_BASE_URL || 'http://127.0.0.1:8044').replace(/\/$/, '');
const arg = (name) => process.argv.find((a) => a.startsWith(`--${name}=`))?.split('=').slice(1).join('=');
const OUT = path.resolve(arg('out') || 'storage/qa/ga4');
const REAL = process.argv.includes('--real-gtag');
const STUB = 'window.__gtagStub = (window.__gtagStub || 0) + 1;';
const SETTLE = 1500;

fs.mkdirSync(OUT, { recursive: true });

const results = [];
const check = (name, ok, detail = '') => {
    results.push({ name, ok: !!ok, detail });
    console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? `  (${detail})` : ''}`);
};

const browser = await chromium.launch({ channel: 'chrome', headless: true });

async function context({ width = 1440, locale = 'en' } = {}) {
    const phone = width < 768;
    const ctx = await browser.newContext({
        viewport: { width, height: phone ? 844 : 900 },
        isMobile: phone,
        hasTouch: phone,
        locale,
        reducedMotion: 'reduce',
    });
    await ctx.route('**/*', (route) => {
        const url = new URL(route.request().url());
        if (url.origin === new URL(BASE).origin) return route.continue();
        if (url.hostname === 'www.googletagmanager.com' && url.pathname === '/gtag/js') {
            return REAL ? route.continue() : route.fulfill({ status: 200, contentType: 'application/javascript', body: STUB });
        }
        if (REAL && /(^|\.)google-analytics\.com$|(^|\.)analytics\.google\.com$|^www\.googletagmanager\.com$/.test(url.hostname)) {
            return route.continue();
        }
        return route.abort();
    });
    // Design 2 while Design 1 still exists (ignored once Design 2 is the only design); no floating design switch.
    await ctx.addCookies([
        { name: 'summit_design', value: '2', url: BASE },
        { name: 'summit_qa', value: '1', url: BASE },
    ]);
    return ctx;
}

async function open(ctx, url) {
    const page = await ctx.newPage();
    const errors = [];
    page.on('console', (msg) => {
        if (msg.type() === 'error') errors.push(msg.text());
    });
    page.on('pageerror', (error) => errors.push(String(error)));
    // Nothing leaves the page: contact links and language links are counted, then their navigation is cancelled.
    await page.addInitScript(() => {
        document.addEventListener('click', (event) => {
            if (window.__allowNav) return;
            const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
            if (link && /^(tel:|mailto:|https?:\/\/(wa\.me|api\.whatsapp\.com)\/)/.test(link.getAttribute('href') || '')) event.preventDefault();
            if (link && link.hasAttribute('data-lang-switch')) event.preventDefault();
        });
    });
    await page.goto(BASE + url, { waitUntil: 'load' });
    await page.waitForFunction(() => document.documentElement.classList.contains('js'));
    await page.waitForTimeout(400); // the module runs after Alpine starts
    return { page, errors };
}

/** window.dataLayer as plain arrays (each entry is an arguments object). */
const layer = (page) =>
    page.evaluate(() => (window.dataLayer || []).map((entry) => Array.from(entry).map((v) => (v instanceof Date ? 'Date' : v))));
const consentCalls = (entries) => entries.filter((e) => e[0] === 'consent');
const events = (entries, name) => entries.filter((e) => e[0] === 'event' && e[1] === name);
const cookies = async (ctx) => Object.fromEntries((await ctx.cookies()).map((c) => [c.name, c]));
const gaCookies = async (ctx) => (await ctx.cookies()).filter((c) => /^_ga(_|$)/.test(c.name)).map((c) => c.name);
const neverAdGranted = (entries) =>
    consentCalls(entries).every((e) => ['ad_storage', 'ad_user_data', 'ad_personalization'].every((k) => e[2]?.[k] !== 'granted'));

// ------------------------------------------------------------------ 1. untouched bar, Reject
{
    const ctx = await context();
    const { page, errors } = await open(ctx, '/en/?name=Jane&phone=0501234567&utm_source=newsletter&utm_campaign=autumn#faq');
    let entries = await layer(page);
    const consent = consentCalls(entries);
    check('tag: gtag.js requested and the stub ran', REAL || (await page.evaluate(() => window.__gtagStub === 1)));
    check(
        'consent default: everything denied, first',
        entries[0]?.[0] === 'consent' &&
            entries[0][1] === 'default' &&
            JSON.stringify(entries[0][2]) === JSON.stringify({ analytics_storage: 'denied', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied' }),
        JSON.stringify(entries[0]),
    );
    check('untouched: no consent update', consent.length === 1);
    const config = entries.find((e) => e[0] === 'config');
    check(
        'page_location: no personal query, no hash; utm_* kept',
        config?.[2]?.page_location === `${BASE}/en/?utm_source=newsletter&utm_campaign=autumn`,
        config?.[2]?.page_location,
    );
    check('config: Google signals and ad personalisation signals off', config?.[2]?.allow_google_signals === false && config?.[2]?.allow_ad_personalization_signals === false);
    check('untouched: bar shown', await page.locator('[data-consent]').isVisible());
    check('untouched: no _ga and no summit_consent cookie', (await gaCookies(ctx)).length === 0 && !(await cookies(ctx)).summit_consent);

    const axe = await new AxeBuilder({ page }).include('#consent').analyze();
    check('axe: no violations in the bar', axe.violations.length === 0, axe.violations.map((v) => v.id).join(', '));
    const heights = await page.$$eval('[data-consent-choice]', (els) => els.map((el) => Math.round(el.getBoundingClientRect().height)));
    const widths = await page.$$eval('[data-consent-choice]', (els) => els.map((el) => Math.round(el.getBoundingClientRect().width)));
    check('two equal buttons (1440)', heights.length === 2 && heights[0] === heights[1] && Math.abs(widths[0] - widths[1]) <= 1 && heights[0] >= 44, `${widths} x ${heights}`);

    await page.locator('[data-consent-choice="denied"]').click();
    await page.waitForTimeout(300);
    entries = await layer(page);
    const last = consentCalls(entries).at(-1);
    check('Reject: consent update denied (analytics only)', JSON.stringify(last) === JSON.stringify(['consent', 'update', { analytics_storage: 'denied' }]));
    check('Reject: never granted', !consentCalls(entries).some((e) => e[2]?.analytics_storage === 'granted') && neverAdGranted(entries));
    check('Reject: bar hidden', !(await page.locator('[data-consent]').isVisible()));
    const stored = (await cookies(ctx)).summit_consent;
    check('Reject: stored as summit_consent=denied, ~6 months, Lax', stored?.value === 'denied' && stored.sameSite === 'Lax' && stored.expires * 1000 - Date.now() > 180 * 864e5, JSON.stringify(stored));
    check('Reject: saved message', await page.locator('.toast-region').getByText((await page.locator('[data-consent]').getAttribute('data-consent-saved')) || '-').isVisible());
    if (REAL) await page.waitForTimeout(2000);
    check('Reject: no _ga cookie', (await gaCookies(ctx)).length === 0);

    const next = await open(ctx, '/en/contact/');
    entries = await layer(next.page);
    check('Reject, next page: bar stays closed, consent stays denied', !(await next.page.locator('[data-consent]').isVisible()) && consentCalls(entries).length === 1);
    check('no console errors (CSP included)', errors.length === 0 && next.errors.length === 0, [...errors, ...next.errors].join(' | '));
    await ctx.close();
}

// ------------------------------------------------------------------ 2. Accept, next page, Privacy Settings, Reject again
{
    const ctx = await context();
    const { page, errors } = await open(ctx, '/en/');
    await page.locator('[data-consent-choice="granted"]').click();
    await page.waitForTimeout(300);
    let entries = await layer(page);
    check('Accept: consent update granted for analytics storage only', JSON.stringify(consentCalls(entries).at(-1)) === JSON.stringify(['consent', 'update', { analytics_storage: 'granted' }]));
    check('Accept: ad storage, ad user data, ad personalisation never granted', neverAdGranted(entries));
    const stored = (await cookies(ctx)).summit_consent;
    check('Accept: stored as summit_consent=granted, ~6 months', stored?.value === 'granted' && stored.expires * 1000 - Date.now() > 180 * 864e5);
    if (REAL) {
        await page.waitForTimeout(3000);
        check('Accept: Google sets _ga (real gtag.js)', (await gaCookies(ctx)).includes('_ga'), (await gaCookies(ctx)).join(','));
    }

    const next = await open(ctx, '/ar/');
    entries = await layer(next.page);
    const update = entries.findIndex((e) => e[0] === 'consent' && e[1] === 'update' && e[2]?.analytics_storage === 'granted');
    const config = entries.findIndex((e) => e[0] === 'config');
    check('Accept, next page: granted before the config (first hit)', update > 0 && update < config, `update ${update}, config ${config}`);
    check('Accept, next page: bar closed', !(await next.page.locator('[data-consent]').isVisible()));

    // Privacy Settings in the footer reopens it; Escape closes it and focus returns.
    if (!REAL) await next.page.context().addCookies([{ name: '_ga', value: 'GA1.1.1.1', url: BASE }, { name: '_ga_TEST123ABC', value: 'GS1.1', url: BASE }]);
    const settings = next.page.locator('[data-consent-open]');
    await settings.scrollIntoViewIfNeeded();
    await settings.click();
    check('Privacy Settings: bar open, aria-expanded true', (await next.page.locator('[data-consent]').isVisible()) && (await settings.getAttribute('aria-expanded')) === 'true');
    check('Privacy Settings: focus on the bar title', await next.page.evaluate(() => document.activeElement?.id === 'consent-title'));
    await next.page.keyboard.press('Escape');
    check(
        'Escape: closed, focus back on Privacy Settings, choice unchanged',
        !(await next.page.locator('[data-consent]').isVisible()) &&
            (await next.page.evaluate(() => document.activeElement?.hasAttribute('data-consent-open'))) &&
            (await cookies(ctx)).summit_consent?.value === 'granted',
    );
    await settings.click();
    await next.page.keyboard.press('Tab');
    await next.page.keyboard.press('Tab');
    const focused = await next.page.evaluate(() => document.activeElement?.getAttribute('data-consent-choice'));
    check('keyboard: Tab from the title reaches the buttons', focused === 'denied' || focused === 'granted', String(focused));
    await next.page.locator('[data-consent-choice="denied"]').click();
    await next.page.waitForTimeout(REAL ? 1500 : 300);
    entries = await layer(next.page);
    check('Reject after Accept: consent update denied', JSON.stringify(consentCalls(entries).at(-1)) === JSON.stringify(['consent', 'update', { analytics_storage: 'denied' }]));
    check('Reject after Accept: _ga cookies deleted', (await gaCookies(ctx)).length === 0, (await gaCookies(ctx)).join(','));
    check('Reject after Accept: focus back on Privacy Settings', await next.page.evaluate(() => document.activeElement?.hasAttribute('data-consent-open')));
    check('no console errors', errors.length === 0 && next.errors.length === 0, [...errors, ...next.errors].join(' | '));
    await ctx.close();
}

// ------------------------------------------------------------------ 3. events (desktop)
{
    const ctx = await context();
    await ctx.addCookies([{ name: 'summit_consent', value: 'granted', url: BASE }]);
    const { page, errors } = await open(ctx, '/en/');
    const once = async (name, locator, params) => {
        const before = events(await layer(page), name).length;
        await locator.scrollIntoViewIfNeeded();
        await locator.click();
        await page.waitForTimeout(150);
        const list = events(await layer(page), name);
        const got = list.at(-1)?.[2];
        check(`${name} ${JSON.stringify(params)}: once, exactly these parameters`, list.length === before + 1 && JSON.stringify(got) === JSON.stringify(params), JSON.stringify(list.slice(before)));
    };
    await once('whatsapp_click', page.locator('.hero a[href^="https://wa.me/"]').first(), { link_location: 'hero' });
    await once('whatsapp_click', page.locator('.d2-footer a[href^="https://wa.me/"]').first(), { link_location: 'footer' });
    await once('phone_click', page.locator('.d2-footer a[href^="tel:"]').first(), { link_location: 'footer' });
    await once('email_click', page.locator('.d2-footer a[href^="mailto:"]').first(), { link_location: 'footer' });
    const contactCard = page.locator('.contact-card a[href^="https://wa.me/"]').first();
    if (await contactCard.count()) await once('whatsapp_click', contactCard, { link_location: 'contact' });
    await once('language_change', page.locator('.d2-footer a[data-lang-switch="ar"]'), { from: 'en', to: 'ar' });
    const current = events(await layer(page), 'language_change').length;
    await page.locator('.d2-footer a[data-lang-switch="en"]').click();
    await page.waitForTimeout(150);
    check('language_change: not for the current language', events(await layer(page), 'language_change').length === current);
    const all = (await layer(page)).filter((e) => e[0] === 'event');
    check('events: never a phone number, e-mail address or message', !JSON.stringify(all).match(/971|@|text=|summitlegaluae/), JSON.stringify(all));

    // A service page: the page head and the closing consultation block.
    const service = await open(ctx, '/en/practice-areas/criminal-cases/');
    for (const [selector, where] of [['.inner-head a[href^="https://wa.me/"]', 'hero'], ['.svc-cta a[href^="https://wa.me/"]', 'cta']]) {
        const link = service.page.locator(selector).first();
        if (!(await link.count())) continue;
        const before = events(await layer(service.page), 'whatsapp_click').length;
        await link.scrollIntoViewIfNeeded();
        await link.click();
        await service.page.waitForTimeout(150);
        const list = events(await layer(service.page), 'whatsapp_click');
        check(`service page: whatsapp_click {link_location: ${where}} once`, list.length === before + 1 && JSON.stringify(list.at(-1)[2]) === JSON.stringify({ link_location: where }), JSON.stringify(list.slice(before)));
    }
    check('no console errors (service page)', service.errors.length === 0, service.errors.join(' | '));

    // A real language change: the event is sent and the next page loads in Arabic.
    await page.evaluate(() => (window.__allowNav = true));
    await Promise.all([page.waitForURL(/\/ar\//), page.locator('.d2-footer a[data-lang-switch="ar"]').click()]);
    check('language change navigates', page.url().endsWith('/ar/'));

    // Calculators: one event per result shown, never per keystroke, never the amounts.
    const calc = await open(ctx, '/en/calculators/');
    const rentTab = calc.page.locator('[role="tab"][aria-controls="tool-rent"]');
    if (await rentTab.count()) await rentTab.click();
    const field = (name) => calc.page.locator(`[data-tool="rent"] input[data-input="${name}"], [data-tool="rent"] [data-input="${name}"] input`).first();
    await field('currentAnnualRent').click();
    await field('currentAnnualRent').pressSequentially('85000', { delay: 120 });
    await field('indexAverageRent').click();
    await field('indexAverageRent').pressSequentially('100000', { delay: 120 });
    check('calculator: nothing while typing', events(await layer(calc.page), 'calculator_used').length === 0);
    await calc.page.waitForTimeout(SETTLE + 600);
    let used = events(await layer(calc.page), 'calculator_used');
    check('calculator_used {calculator: rent}: once, exactly this parameter', used.length === 1 && JSON.stringify(used[0][2]) === JSON.stringify({ calculator: 'rent' }), JSON.stringify(used));
    await field('indexAverageRent').pressSequentially('5', { delay: 120 });
    await calc.page.waitForTimeout(SETTLE + 600);
    used = events(await layer(calc.page), 'calculator_used');
    check('calculator: editing the shown result sends nothing more', used.length === 1);
    check('no console errors (events)', errors.length === 0 && calc.errors.length === 0, [...errors, ...calc.errors].join(' | '));
    await ctx.close();
}

// ------------------------------------------------------------------ 4. phones: the floating button and the bar, screenshots
const overlap = (a, b) => a && b && a.x < b.x + b.width && b.x < a.x + a.width && a.y < b.y + b.height && b.y < a.y + a.height;
for (const locale of ['en', 'ar', 'ru']) {
    for (const width of [1440, 390]) {
        const ctx = await context({ width, locale });
        const { page, errors } = await open(ctx, `/${locale}/`);
        const bar = page.locator('[data-consent]');
        check(`${locale} ${width}: bar shown`, await bar.isVisible());
        await page.screenshot({ path: path.join(OUT, `consent-${locale}-${width}.png`) });
        if (width < 768) {
            // Scroll until the floating WhatsApp button shows (it steps aside while the hero's WhatsApp button is on screen).
            await page.mouse.wheel(0, 1400);
            await page.waitForTimeout(800);
            const fab = page.locator('[data-wa-fab]');
            const fabShown = await fab.evaluate((el) => !el.classList.contains('is-away'));
            const boxes = { bar: await page.locator('.consent__panel').boundingBox(), fab: await fab.boundingBox(), tabbar: await page.locator('[data-tabbar]').boundingBox() };
            check(`${locale} 390: the floating WhatsApp button shows with the bar open`, fabShown);
            check(`${locale} 390: the bar covers neither the tab bar nor the floating button`, !overlap(boxes.bar, boxes.fab) && !overlap(boxes.bar, boxes.tabbar), JSON.stringify(boxes));
            const before = events(await layer(page), 'whatsapp_click').length;
            await fab.click();
            await page.waitForTimeout(150);
            const list = events(await layer(page), 'whatsapp_click');
            check(`${locale} 390: whatsapp_click {link_location: floating} once`, list.length === before + 1 && JSON.stringify(list.at(-1)[2]) === JSON.stringify({ link_location: 'floating' }));
            await page.screenshot({ path: path.join(OUT, `consent-${locale}-${width}-fab.png`) });
            const heights = await page.$$eval('[data-consent-choice]', (els) => els.map((el) => Math.round(el.getBoundingClientRect().height)));
            check(`${locale} 390: buttons are 48 px touch targets and equal`, heights[0] >= 48 && heights[0] === heights[1], String(heights));
        }
        check(`${locale} ${width}: no horizontal overflow`, await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth));
        check(`${locale} ${width}: no console errors`, errors.length === 0, errors.join(' | '));
        await ctx.close();
    }
}

await browser.close();
const failed = results.filter((r) => !r.ok);
console.log(`\n${results.length - failed.length}/${results.length} checks passed${REAL ? ' (real gtag.js)' : ' (gtag.js stubbed)'}; screenshots in ${OUT}`);
process.exit(failed.length ? 1 : 0);
