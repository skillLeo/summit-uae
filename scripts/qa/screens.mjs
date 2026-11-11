#!/usr/bin/env node
/**
 * Screenshot matrix (docs/briefs/design-2.1-and-admin-cms.md §7.3) for Design 1 and Design 2.
 * Phase 0 keeps its run as the "before" set; every later phase runs it again into its own folder, and the
 * Design 1 regression of §7.4 compares two runs taken with --mask: this tree, and a server of the frozen tag
 * (:8041 serves design-1.1, docs/d2/running.md §8).
 *
 *   node scripts/qa/screens.mjs --phase=phase-0 --design=both              everything (npm run qa:screens -- …)
 *   node scripts/qa/screens.mjs --phase=p1 --design=2 --pages=home,faq --locales=ar --widths=390 --states=off
 *   node scripts/qa/screens.mjs --phase=d1-tag --design=1 --mask --states=off --base=http://127.0.0.1:8041
 *   node scripts/qa/screens.mjs --phase=phase-0 --sheets-only              rebuild sheets + index.html only
 *
 * Options
 *   --phase=<name>        output folder storage/qa/screens/<name>/ (required: letters, digits, ". _ -")
 *   --design=1|2|both     default both
 *   --locales=en,ar,ru    default en,ar,ru
 *   --widths=1440,1280,768,390   default all four (any other width: below 768 px a touch phone, else desktop)
 *   --pages=<keys>|none   default all (PAGES below)
 *   --states=on|off       default on (STATES below)
 *   --state-list=<keys>   default all states
 *   --base=<url>          default http://127.0.0.1:8040 (a server of this working tree: the design comes from
 *                         the cookie summit_design, the floating switcher stays out with summit_qa=1)
 *   --mask                paint a flat magenta box over the copyright line (the dynamic year) and every live
 *                         office-hours status (Design 1 [data-open-status], Design 2 [data-hours-status])
 *   --concurrency=<n>     parallel captures, default 4
 *   --clock=<ISO>|real    the page clock (Date, Date.now) starts at this instant and runs on; default
 *                         2026-11-10T10:30:00+04:00 (a Tuesday inside the 09:00-18:00 office hours), so the
 *                         live status reads the same in every capture of every run; "real" leaves Date alone
 *   --timezone=<IANA>     browser time zone, default Asia/Dubai (the client's; Design 2's "UAE is N h
 *                         ahead/behind you" line only renders in another zone)
 *   --out=<dir>           parent folder of the phase folders, default storage/qa/screens
 *   --sheets-only         no captures: rebuild the contact sheets and index.html from manifest.json
 *   --no-sheets           captures and manifest only
 *   QA_MIN_FREE_GIB=<n>   environment: the free-space floor in GiB (default 3, minimum 0.5)
 *
 * Capture rules: Google Chrome (channel chrome), one fresh context per capture with the cookies
 * summit_design=<1|2> and summit_qa=1, prefers-reduced-motion: reduce (deterministic final states), the
 * browser locale of the page language, device scale 1 (390 = isMobile + hasTouch, 768 = hasTouch, as in
 * qa/lib/config.mjs), Vite's reload client stubbed (qa/lib/browser.mjs), requests to other hosts than
 * 127.0.0.1 / localhost blocked (Turnstile), any non-GET request to /enquiries blocked (nothing is ever
 * sent). Touch widths run in a second Chrome started with a touch screen's pointer and hover
 * (TOUCH_BLINK_SETTINGS below): Playwright starts Chrome with a mouse's, and while Chrome paints a capture
 * taller than the viewport it resets the page to them, so a touch page used to be laid out for a mouse in
 * the middle of its capture (44 px targets instead of 48, hover styles, a shorter page and a band of
 * background below the footer). Every capture checks that the page sees the input of its width (touch:
 * pointer coarse, hover none; desktop: pointer fine, hover hover). Each page: load, document.fonts.ready,
 * one scroll through the page (lazy content) and back to the top, fonts again, scripts and animations
 * settled, network idle; then a full-page capture, stitched from 8,000 px segments when the page is taller
 * than 16,000 px (Chrome's limit is 16,384) at the heights Chrome returned, saved as JPEG quality 80. Every
 * full-page and element capture is checked: the document height is re-measured once the page has settled
 * again, before each segment (the last one included) and after the capture; the page reports any pointer or
 * hover change while Chrome paints; and the image must be as tall as the region asked for. Any difference
 * fails the capture (one retry) and is recorded in manifest.json (layoutCheck), so a capture never ends in
 * rows the page does not have. Chrome draws position: fixed elements (the phone tab bar, Design 1's bottom
 * bar, floating buttons) once, where they sit in the first screen: on phones they cover the bottom of the
 * first screen of a full-page capture and appear nowhere else, exactly as a visitor sees that first screen.
 * Element captures (#tools on the calculators page, the form, the anatomy) are clipped from the full page at
 * scroll 0 with 24 px of the page above and below; position: fixed elements (the tab bar, floating buttons, a
 * tool's mobile summary bar) are hidden while the element is captured, so a target in the first screen is never
 * covered by them; overlay states are viewport captures.
 *
 * Output (storage/qa/screens/<phase>/, gitignored):
 *   d1/ d2/                    <page>-<locale>-<width>.jpg and state-<state>-<locale>-<width>.jpg
 *   sheets/<design>-<page>.jpg one labelled grid per page and design (rows = locales, columns = widths)
 *   sheets/<design>-state-<state>.jpg
 *   manifest.json              every capture: url, status, size, height, time, console errors, notes, the
 *                              input the page saw (pointer, hover, touch points) and the layout check
 *                              (height measured, re-measured before every segment and after, pointer or
 *                              hover changes while painted, segment and image heights, difference in px);
 *                              and every page or state that does not exist in a design, with the reason
 *   index.html                 links to every sheet and capture
 * Re-runs into the same folder replace only the captures they make (the manifest is merged).
 *
 * Exit 0: every requested capture was made or recorded as not existing; 1: a capture failed; 2: could not run.
 */
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, statfsSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { chromium } from '@playwright/test';
import sharp from 'sharp';
import { newContext, scrollThrough, untilPageTime, watchPage } from '../../qa/lib/browser.mjs';
import { IGNORED_HOSTS, ROOT } from '../../qa/lib/config.mjs';
import { freezeAnimations, script, settleAnimations, settleStyles } from '../../qa/lib/runtime.mjs';

// ------------------------------------------------------------------------------------------ options
const argv = Object.fromEntries(
    process.argv.slice(2).map((a) => {
        const [k, ...v] = a.replace(/^--/, '').split('=');
        return [k, v.length ? v.join('=') : 'true'];
    }),
);
const HELP = readFileSync(new URL(import.meta.url), 'utf8').match(/\/\*\*([\s\S]*?)\*\//)[1].replace(/^ \* ?/gm, '');
if (argv.help) {
    console.log(HELP);
    process.exit(0);
}
const list = (value, fallback) => (value && value !== 'true' ? value.split(',').map((s) => s.trim()).filter(Boolean) : fallback);

const PHASE = argv.phase;
if (!PHASE || !/^[A-Za-z0-9._-]+$/.test(PHASE)) {
    console.error('Usage: node scripts/qa/screens.mjs --phase=<name> [--design=1|2|both] … (--help for every option)');
    process.exit(2);
}
const DESIGNS = { 1: [1], 2: [2], both: [1, 2] }[argv.design ?? 'both'];
if (!DESIGNS) {
    console.error(`--design must be 1, 2 or both (got ${argv.design})`);
    process.exit(2);
}
const LOCALES = list(argv.locales, ['en', 'ar', 'ru']).filter((l) => ['en', 'ar', 'ru'].includes(l));
const WIDTHS = list(argv.widths, ['1440', '1280', '768', '390']).map(Number).filter((w) => Number.isInteger(w) && w >= 320 && w <= 2560);
const BASE = (argv.base || 'http://127.0.0.1:8040').replace(/\/+$/, '');
const MASK = argv.mask === 'true';
const CONCURRENCY = Math.max(1, Math.min(8, Number(argv.concurrency) || 4));
const STATES_ON = (argv.states ?? 'on') !== 'off';
const SHEETS_ONLY = argv['sheets-only'] === 'true';
const SHEETS = argv['no-sheets'] !== 'true';
const TIMEZONE = argv.timezone || 'Asia/Dubai';
const CLOCK = argv.clock === 'real' ? null : argv.clock || '2026-11-10T10:30:00+04:00';
const CLOCK_MS = CLOCK === null ? null : Date.parse(CLOCK);
if (CLOCK !== null && !Number.isFinite(CLOCK_MS)) {
    console.error(`--clock must be an ISO date-time or "real" (got ${CLOCK})`);
    process.exit(2);
}

const OUT = path.resolve(ROOT, argv.out && argv.out !== 'true' ? argv.out : 'storage/qa/screens', PHASE);
const MANIFEST = path.join(OUT, 'manifest.json');
// Stop scheduling captures below 3 GiB free (3.2 GB: the shared disk's floor is 3 GB). QA_MIN_FREE_GIB lowers or
// raises it for one run (a full matrix of both designs writes about 160 MB), never below 0.5 GiB.
const MIN_FREE = Math.max(0.5, Number(process.env.QA_MIN_FREE_GIB) || 3) * 2 ** 30;
const MAX_SINGLE = 16_000; // taller full-page captures are stitched from segments
const SEGMENT = 8_000;
const ELEMENT_MARGIN = 24; // page kept above and below an element capture
const JPEG = { quality: 80, mozjpeg: true };
const DETERMINISTIC = ['--disable-gpu', '--disable-gpu-rasterization', '--disable-partial-raster', '--disable-skia-runtime-opts', '--disable-lcd-text', '--force-color-profile=srgb'];
/**
 * The pointer and hover of a touch screen, as Blink start-up settings (pointer types: 2 coarse, 4 fine;
 * hover types: 1 none, 2 hover) for the Chrome that captures touch widths. Headless Playwright starts Chrome
 * with a mouse's (primaryPointerType=4, primaryHoverType=2) and emulates touch per page through DevTools; a
 * capture beyond the viewport makes Chrome reset the page to the start-up values while it paints (Playwright
 * only switches touch back on afterwards). Measured on the EN home at 768: 23,006 px laid out for touch,
 * 22,955 px painted for a mouse, a 51 px band of background below the footer. These settings apply to every
 * page of that browser, so desktop widths keep their own.
 */
const TOUCH_BLINK_SETTINGS = 'primaryPointerType=2,availablePointerTypes=2,primaryHoverType=1,availableHoverTypes=1';

// ------------------------------------------------------------------------------------------ site map
/** Paths from config/summit.php (pages P01-P08 / L01-L02, services, guides), read from the file itself. */
function siteMap() {
    const fallback = {
        pages: { P01: '', P02: 'practice-areas/', P03: 'resources/', P04: 'case-studies/', P05: 'faq/', P06: 'about/', P07: 'contact/', P08: 'legal-disclaimer/', L01: 'privacy-policy/', L02: 'terms-and-conditions/' },
        services: { S06: 'labor-law' },
        guides: { G01: 'unpaid-salary' },
        source: 'built-in copy of config/summit.php',
    };
    try {
        const src = readFileSync(path.join(ROOT, 'config/summit.php'), 'utf8');
        const pages = Object.fromEntries([...src.matchAll(/'([PL]\d\d)'\s*=>\s*\[\s*'path'\s*=>\s*'([^']*)'/g)].map((m) => [m[1], m[2]]));
        const block = (name) => src.match(new RegExp(`'${name}'\\s*=>\\s*\\[([\\s\\S]*?)\\]`))?.[1] ?? '';
        const services = Object.fromEntries([...block('services').matchAll(/'(S\d\d)'\s*=>\s*'([^']+)'/g)].map((m) => [m[1], m[2]]));
        const guides = Object.fromEntries([...block('guides').matchAll(/'(G\d\d)'\s*=>\s*'([^']+)'/g)].map((m) => [m[1], m[2]]));
        if (pages.P02 && services.S06 && guides.G01) return { pages, services, guides, source: 'config/summit.php' };
    } catch {
        // fall back
    }
    return fallback;
}
const MAP = siteMap();

/**
 * Brief §7.3 pages. capture: full (default) | viewport (scrolled to `anchor`) | element (`target`, clipped).
 * `missingIf`: a selector that must exist, else the page is recorded as not existing in that design.
 */
const PAGES = [
    { key: 'home', code: 'P01', label: 'Home (P01)' },
    { key: 'practice-areas', code: 'P02', label: 'Practice areas index (P02)' },
    { key: 'practice-labor-law', service: 'S06', label: `Practice page: Employment law (S06, ${MAP.services.S06})` },
    { key: 'resources', code: 'P03', label: 'Legal Resources (P03)' },
    { key: 'guide-unpaid-salary', code: 'P03', anchor: MAP.guides.G01, capture: 'viewport', label: `Guide anchor: G01 #${MAP.guides.G01} on P03 (viewport)` },
    { key: 'case-studies', code: 'P04', label: 'Case studies (P04)' },
    { key: 'faq', code: 'P05', label: 'FAQ (P05)' },
    { key: 'about', code: 'P06', label: 'About (P06)' },
    { key: 'contact', code: 'P07', label: 'Contact (P07)' },
    {
        key: 'calculators',
        path: 'calculators/',
        designs: [2],
        label: 'Calculators page (/{locale}/calculators/, Design 2 only)',
        note: 'Brief 2.1 §5.1: the calculators live on their own page (pending copy; it renders while pending copy is shown). Design 1 has none (the address redirects to its home page).',
    },
    { key: 'legal-notice', code: 'P08', label: 'Legal notice (P08)' },
    { key: 'privacy', code: 'L01', label: 'Privacy policy (L01)' },
    { key: 'terms', code: 'L02', label: 'Terms and conditions (L02)' },
    { key: '404', path: 'no-such-page-qa/', status: 404, label: '404 (an unmatched URL)' },
];
const pathOf = (def) => def.path ?? (def.service ? `${MAP.pages.P02}${MAP.services[def.service]}/` : MAP.pages[def.code]);
/** The page a state runs on: its `page` key, else the home page. */
const statePage = (def) => (def.page ? PAGES.find((p) => p.key === def.page) : PAGES[0]);
const urlOf = (locale, def) => `${BASE}/${locale}/${pathOf(def)}${def.anchor ? `#${def.anchor}` : ''}`;

// ------------------------------------------------------------------------------------------ page helpers
const visible = (page, selector) => page.locator(selector).first().isVisible().catch(() => false);
const exists = async (page, selector) => (await page.locator(selector).count()) > 0;
const ok = (notes = []) => ({ ok: true, notes });
const unavailable = (reason) => ({ ok: false, reason });

async function scrollToTarget(page, selector, block = 'start') {
    await page.evaluate(
        ([sel, b]) => document.querySelector(sel)?.scrollIntoView({ block: b, inline: 'nearest', behavior: 'instant' }),
        [selector, block],
    );
    await page.waitForTimeout(450); // IntersectionObserver-driven lazy chunks (wizard, calculators) start here
}

async function waitOpen(page, selector, timeout = 6000) {
    await page.locator(selector).first().waitFor({ state: 'visible', timeout });
    await page.waitForTimeout(500); // sheet and popover transitions (none under reduced motion, but be safe)
}

/** The menu sheet of either design from the header's menu button (Design 1: also the phone bottom bar). */
async function openMenuSheet(page, design) {
    if (design === 2) {
        if (!(await visible(page, '.d2-header__menu'))) return unavailable('the header shows no menu button at this width (the full navigation is inline)');
        await page.locator('.d2-header__menu').first().click();
        await waitOpen(page, '[data-sheet="menu"].is-open');
        return ok();
    }
    if (await visible(page, '.site-header__menu-btn')) {
        await page.locator('.site-header__menu-btn').first().click();
        await waitOpen(page, '[data-sheet="menu"].is-open');
        return ok();
    }
    if (await visible(page, '.bottombar [data-sheet-open="services"]')) {
        await page.locator('.bottombar [data-sheet-open="services"]').first().click();
        await waitOpen(page, '[data-sheet="services"].is-open');
        return ok(["Design 1 has no menu button below 768 px: its bottom bar is the navigation; this is the bottom bar's Practice Areas sheet."]);
    }
    return unavailable('Design 1 shows neither a menu button nor the bottom bar at this width (the full navigation is inline)');
}

/** Gratuity estimator inputs: a typical full-time mainland employee (about six years, AED 12,000 basic). */
const GRATUITY_VALUES = [
    ['gr_start', '2019-03-01'],
    ['gr_end', '2025-02-28'],
    ['gr_absence', '0'],
    ['gr_basic', '12000'],
    ['gr_total', '18000'],
    ['gr_leave', '10'],
    ['gr_unpaid', '0'],
    ['gr_notice', '30'],
    ['gr_served', '30'],
];

/**
 * Brief §7.3 states, all on the home page. Each `prepare` gets the page settled at the top and returns
 * ok(notes) or unavailable(reason) (a state the design does not have at that width or at all).
 */
const STATES = [
    {
        key: 'mega-menu',
        label: 'Practice Areas mega menu open (home, viewport)',
        widths: [1440, 1280],
        capture: 'viewport',
        async prepare(page, design) {
            const toggle = design === 1 ? '.site-nav__toggle[aria-controls="nav-panel-services"]' : '.d2-nav__item--mega > .d2-nav__toggle';
            const panel = design === 1 ? '#nav-panel-services' : '#d2-megamenu';
            if (!(await visible(page, toggle))) {
                return unavailable('the desktop navigation with the Practice Areas menu is not shown at this width in this language (the header shows its menu button instead)');
            }
            await page.locator(toggle).first().click();
            await waitOpen(page, panel);
            return ok();
        },
    },
    {
        key: 'mobile-menu',
        label: 'Menu sheet open (home, viewport)',
        widths: [768, 390],
        capture: 'viewport',
        prepare: (page, design) => openMenuSheet(page, design),
    },
    {
        key: 'lang-menu',
        label: 'Language menu open (Design 2: the header popover; the menu sheet\'s language group where the header hides it) (home, viewport)',
        widths: [1440, 1280, 768, 390],
        capture: 'viewport',
        async prepare(page, design, width) {
            if (design === 1) {
                if (!(await visible(page, '.lang-menu__button'))) return unavailable('no language button at this width');
                await page.locator('.lang-menu__button').first().click();
                if (width < 768) {
                    await waitOpen(page, '[data-sheet="language"].is-open');
                    return ok(['Below 768 px the language button opens the language sheet.']);
                }
                await waitOpen(page, '#lang-menu-list');
                return ok();
            }
            // Design 2.1 header (<x-d2.shell.langmenu class="d2-header__lang">): the trigger shows the current
            // language's approved label and a chevron and opens the popover #d2-langmenu (the header shows it from
            // 768 px, components/header.css).
            const trigger = '.d2-header__lang .langmenu__trigger';
            if (await visible(page, trigger)) {
                await page.locator(trigger).first().click();
                await waitOpen(page, '#d2-langmenu');
                return ok(['The header language menu (popover #d2-langmenu) opened from its trigger.']);
            }
            // Only where the header hides the trigger (phones): the menu sheet's "Choose language" row opens the
            // language sheet (brief 2.1 §6: the language is a sheet, not a segmented group).
            if (!(await visible(page, '.d2-header__menu'))) {
                return unavailable('the header shows neither the language menu trigger nor the menu button at this width');
            }
            const sheet = await openMenuSheet(page, 2);
            if (!sheet.ok) return sheet;
            const row = '[data-sheet="menu"] [data-sheet-open="lang"]';
            if (!(await visible(page, row))) return unavailable('the menu sheet has no language row (data-sheet-open="lang")');
            await page.locator(row).first().click();
            await waitOpen(page, '[data-sheet="lang"].is-open');
            return ok(['The header hides its language menu at this width: the menu sheet\'s "Choose language" row opened the language sheet.']);
        },
    },
    {
        key: 'form-step1',
        label: 'Consultation form as first shown (home #consultation-form: Design 2 wizard step 1; Design 1 single form)',
        capture: 'element',
        target: '#consultation-form',
        async prepare(page, design) {
            if (!(await exists(page, '#consultation-form'))) return unavailable('no #consultation-form on the home page');
            await scrollToTarget(page, '#consultation-form');
            if (design === 2) {
                await page.locator('[data-wizard][data-wizard-load="ready"]').first().waitFor({ state: 'attached', timeout: 20_000 });
                await page.waitForTimeout(400);
                return ok(['Design 2: the wizard, step 1 of 5 ("You"), after its lazy controller mounted.']);
            }
            return ok(['Design 1 has one single form (no steps): this is that form, empty.']);
        },
    },
    {
        key: 'form-error',
        label: 'Consultation form error state (Design 2: Next on an empty step 1; Design 1: empty submit)',
        capture: 'element',
        target: '#consultation-form',
        async prepare(page, design) {
            if (!(await exists(page, '#consultation-form'))) return unavailable('no #consultation-form on the home page');
            await scrollToTarget(page, '#consultation-form');
            if (design === 2) {
                await page.locator('[data-wizard][data-wizard-load="ready"]').first().waitFor({ state: 'attached', timeout: 20_000 });
                await page.locator('[data-wizard-next]').first().click();
                await page.locator('[data-wizard] [data-field-error]:not([hidden])').first().waitFor({ state: 'visible', timeout: 6000 });
                await page.waitForTimeout(500);
                return ok(['Design 2: "Next" pressed on an empty step 1.']);
            }
            await page.locator('[data-consultation] [data-submit]').first().click();
            await page.locator('[data-consultation] [data-error-for]:not([hidden])').first().waitFor({ state: 'visible', timeout: 6000 });
            await page.waitForTimeout(500);
            return ok(['Design 1: the submit button pressed on the empty form (browser-side validation; nothing is sent).']);
        },
    },
    {
        key: 'calculator-values',
        label: 'Gratuity calculator with typical values (calculators page #tools)',
        page: 'calculators',
        designs: [2],
        capture: 'element',
        target: '#tools',
        async prepare(page) {
            if (!(await exists(page, '#tools [data-tool="gratuity"]'))) return unavailable('the calculators page has no #tools gratuity calculator');
            await scrollToTarget(page, '#tools');
            await page.locator('.tool--gratuity.is-ready').first().waitFor({ state: 'attached', timeout: 20_000 });
            for (const [name, value] of GRATUITY_VALUES) {
                const field = page.locator(`#tools [name="${name}"]`).first();
                if (!(await field.count())) continue;
                // D36 date picker (components/d2/ui/date.blade.php): with JavaScript the name moves to a hidden ISO
                // input, and the visible text field takes an ISO date through its .value (the native contract).
                const viaPicker = await field.evaluate((el, iso) => {
                    const root = el.closest('[data-datepicker]');
                    const input = el.type === 'hidden' ? root?.querySelector('input:not([type="hidden"])') : null;
                    if (!input) return false;
                    input.value = iso;
                    for (const target of [input, el]) {
                        target.dispatchEvent(new Event('input', { bubbles: true }));
                        target.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    return true;
                }, value);
                if (viaPicker) continue;
                await field.fill(value);
                await field.dispatchEvent('change');
            }
            await page.evaluate(() => document.activeElement?.blur?.()); // commit the last field (no click: it could hit the sticky header)
            await page.locator('.tool--gratuity[data-state="result"]').first().waitFor({ state: 'attached', timeout: 8000 });
            await page.waitForTimeout(600);
            return ok([`Inputs: ${GRATUITY_VALUES.map(([n, v]) => `${n.replace(/^gr_/, '')} ${v}`).join(', ')}; scope Mainland, full-time contract (defaults).`]);
        },
    },
    {
        key: 'anatomy-part',
        label: 'Anatomy of a Document with the second part selected (home #anatomy)',
        capture: 'element',
        target: '#anatomy',
        async prepare(page) {
            if (!(await exists(page, '#anatomy [data-anatomy-part]'))) return unavailable('this design has no Anatomy of a Document section');
            await scrollToTarget(page, '#anatomy');
            const part = page.locator('#anatomy [data-anatomy-panel]:not([hidden]) [data-anatomy-part]').nth(1);
            const name = (await part.textContent())?.trim();
            await part.click();
            await page.waitForFunction(
                () => document.querySelectorAll('#anatomy [data-anatomy-panel]:not([hidden]) [data-anatomy-part]')[1]?.getAttribute('aria-pressed') === 'true',
                null,
                { timeout: 5000 },
            );
            await page.waitForTimeout(500);
            return ok([`Selected part: "${name}" (the second part of the first document).`]);
        },
    },
];

// ------------------------------------------------------------------------------------------ browser
/** Date and Date.now start at --clock and run on; timers and performance.now are left alone. */
const clockShim = (targetMs) => `(() => {
    const RealDate = Date;
    const offset = ${targetMs} - RealDate.now();
    const now = () => RealDate.now() + offset;
    function ShiftedDate(...args) {
        if (!new.target) return new RealDate(now()).toString();
        return args.length === 0 ? new RealDate(now()) : new RealDate(...args);
    }
    ShiftedDate.prototype = RealDate.prototype;
    ShiftedDate.now = now;
    ShiftedDate.parse = RealDate.parse;
    ShiftedDate.UTC = RealDate.UTC;
    globalThis.Date = ShiftedDate;
})();`;

const isTouch = (width) => Boolean(viewportFor(width).hasTouch);
const inputOf = (width) => (isTouch(width) ? { pointer: 'coarse', hover: 'none' } : { pointer: 'fine', hover: 'hover' });

function viewportFor(width) {
    const known = {
        1440: { viewport: { width: 1440, height: 900 } },
        1280: { viewport: { width: 1280, height: 800 } },
        768: { viewport: { width: 768, height: 1024 }, hasTouch: true, isMobile: false },
        390: { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true },
    };
    if (known[width]) return known[width];
    return width < 768 ? { viewport: { width, height: 844 }, isMobile: true, hasTouch: true } : { viewport: { width, height: 900 } };
}

async function openContext(browser, { design, locale, width }) {
    const context = await newContext(browser, {
        width,
        locale,
        variant: 'reduced',
        baseURL: BASE,
        design: String(design),
        extra: { ...viewportFor(width), deviceScaleFactor: 1, timezoneId: TIMEZONE },
    });
    if (CLOCK_MS !== null) await context.addInitScript({ content: clockShim(CLOCK_MS) });
    const blocked = [];
    // Third parties (Cloudflare Turnstile) never load: deterministic captures, nothing leaves this Mac.
    await context.route(/^[a-z]+:\/\/(?!(?:127\.0\.0\.1|localhost|\[::1\])(?::\d+)?\/)/i, (route) => {
        blocked.push(route.request().url());
        return route.abort('blockedbyclient');
    });
    // Belt and braces: a capture never sends an enquiry.
    await context.route(/\/enquiries(?:[/?#]|$)/, (route) => (route.request().method() === 'GET' ? route.fallback() : route.abort('blockedbyclient')));
    return { context, blocked };
}

/** Load, fonts, (home) the intro window, one scroll through the page and back, then everything settled. */
async function load(page, watch, url, { home }) {
    const response = await page.goto(url, { waitUntil: 'load', timeout: 60_000 });
    await page.waitForFunction(() => document.documentElement.classList.contains('js'), null, { timeout: 5000 }).catch(() => {});
    await page.evaluate(() => document.fonts.ready.then(() => true)).catch(() => null);
    if (home) await untilPageTime(page, 1700);
    const scrollSteps = await scrollThrough(page, { pauseMs: 60 });
    await settle(page, watch);
    return { response, scrollSteps };
}

async function settle(page, watch) {
    await page.evaluate(() => document.fonts.ready.then(() => true)).catch(() => null);
    await page.evaluate(script(settleStyles, { quietMs: 300, maxMs: 3000 })).catch(() => null);
    await page.evaluate(script(settleAnimations, 3000)).catch(() => null);
    await watch.idle(4000);
    await page.evaluate(script(freezeAnimations)).catch(() => null);
}

const SHOT = { type: 'png', scale: 'css', animations: 'disabled', caret: 'hide' };

/** In the page: the document height as Playwright measures a full page (it trims clips to this height). */
function pageHeight() {
    return Math.max(...[document.documentElement, document.body].filter(Boolean).flatMap((el) => [el.scrollHeight, el.offsetHeight, el.clientHeight]));
}

/**
 * In the page, just before Chrome paints a capture: records any change of pointer or hover until the capture
 * is over (the sign of a page re-laid out for another input while it was painted). Calling
 * window.__qaInputWatch() stops it and returns the changes. Heights are not watched here: Chrome's capture
 * passes through states it never paints (a 1x1 viewport, then the page's resize handlers catching up), so
 * heights are compared once the page has settled again (settledHeight).
 */
function watchInput() {
    const changes = [];
    const queries = ['(pointer: coarse)', '(pointer: fine)', '(hover: none)', '(hover: hover)'].map((q) => window.matchMedia(q));
    const onChange = (event) => changes.push(`${event.media} ${event.matches ? 'on' : 'off'}`);
    for (const query of queries) query.addEventListener('change', onChange);
    window.__qaInputWatch = () => {
        for (const query of queries) query.removeEventListener('change', onChange);
        delete window.__qaInputWatch;
        return changes;
    };
    return true;
}

const docSize = (page) => page.evaluate(script((_, { pageHeight }) => ({ width: document.documentElement.clientWidth || window.innerWidth, height: pageHeight() }), null, { pageHeight }));

/**
 * The document height once the page has settled: polled every 50 ms until it reads `expected` three times in
 * a row (a capture's resize events can re-lay the page out for a moment), or the last reading after `timeout`.
 */
async function settledHeight(page, expected, timeout = 3000) {
    const start = Date.now();
    let height = (await docSize(page)).height;
    let streak = height === expected ? 1 : 0;
    while (streak < 3 && Date.now() - start < timeout) {
        await page.waitForTimeout(50);
        height = (await docSize(page)).height;
        streak = height === expected ? streak + 1 : 0;
    }
    return height;
}

/** What one full-page or element capture saw; verdict() turns it into the manifest's layoutCheck. */
const newCheck = (measured, requested) => ({ measured, requested, before: [], media: [], segments: [], after: null, unwatched: false });

/** One Playwright capture with the page's pointer and hover watched while Chrome paints it. */
async function watchedShot(page, options, check) {
    const watching = await page.evaluate(script(watchInput)).catch(() => false);
    try {
        return await page.screenshot(options);
    } finally {
        const changes = watching ? await page.evaluate(() => (typeof window.__qaInputWatch === 'function' ? window.__qaInputWatch() : null)).catch(() => null) : null;
        if (changes) check.media.push(...changes);
        else check.unwatched = true;
    }
}

/**
 * The layout check of a capture: the settled page kept the measured height before every segment (the last
 * one included) and after the capture, pointer and hover never changed while Chrome painted, and the image is
 * exactly the region asked for. difference: the largest gap in px between any of these heights and the
 * measured one, or between the image and the region.
 */
function verdict(check) {
    const imageHeight = check.segments.reduce((sum, h) => sum + h, 0);
    const seen = [...check.before, check.after].filter(Number.isFinite);
    const drift = Math.max(0, ...seen.map((h) => Math.abs(h - check.measured)));
    const result = {
        measured: check.measured,
        beforeSegments: check.before,
        beforeLastSegment: check.before.at(-1) ?? null,
        after: check.after,
        mediaChanges: [...new Set(check.media)],
        segmentHeights: check.segments,
        requestedHeight: check.requested,
        imageHeight,
        difference: Math.max(drift, Math.abs(imageHeight - check.requested)),
        watched: !check.unwatched,
    };
    result.ok = result.difference === 0 && result.mediaChanges.length === 0 && result.watched;
    return result;
}

/** A capture that does not show the page as it is laid out fails (capture() retries it once). */
function checked(shot, check) {
    const layoutCheck = verdict(check);
    if (layoutCheck.ok) return { ...shot, layoutCheck };
    const problems = [];
    if (layoutCheck.difference) problems.push(`page ${layoutCheck.measured} px when measured, ${layoutCheck.beforeSegments.join('/')} px before the segments, ${layoutCheck.after} px after; image ${layoutCheck.imageHeight} of ${layoutCheck.requestedHeight} px`);
    if (layoutCheck.mediaChanges.length) problems.push(`pointer or hover changed while Chrome painted (${layoutCheck.mediaChanges.join(', ')})`);
    if (!layoutCheck.watched) problems.push('pointer and hover could not be watched during the capture');
    throw Object.assign(new Error(`the capture does not match the page: ${problems.join('; ')}`), { layoutCheck });
}

/**
 * A region of the full page (document coordinates), segmented above MAX_SINGLE. Before every segment the
 * page settles back to the measured height (re-measured); the segments are stitched at the heights Chrome
 * returned, so the composite never holds rows no segment painted.
 */
async function clipBuffer(page, clip, check) {
    const step = clip.height <= MAX_SINGLE ? clip.height : SEGMENT;
    const parts = [];
    let stitched = 0;
    for (let top = 0; top < clip.height; top += step) {
        const height = Math.min(step, clip.height - top);
        check.before.push(await settledHeight(page, check.measured));
        const input = await watchedShot(page, { ...SHOT, fullPage: true, clip: { ...clip, y: clip.y + top, height } }, check);
        const returned = (await sharp(input).metadata()).height;
        check.segments.push(returned);
        parts.push({ input, top: stitched, left: 0 });
        stitched += returned;
    }
    check.after = await settledHeight(page, check.measured);
    if (parts.length === 1) return { buffer: parts[0].input, segments: 1 };
    const buffer = await sharp({ create: { width: clip.width, height: stitched, channels: 3, background: '#ffffff' } }).composite(parts).png().toBuffer();
    return { buffer, segments: parts.length };
}

async function fullPageCapture(page) {
    const size = await docSize(page);
    const check = newCheck(size.height, size.height);
    if (size.height <= MAX_SINGLE) {
        // Unclipped, so a page wider than the viewport shows its overflow; Playwright measures the height itself.
        check.before.push(size.height);
        const buffer = await watchedShot(page, { ...SHOT, fullPage: true }, check);
        check.segments.push((await sharp(buffer).metadata()).height);
        check.after = await settledHeight(page, check.measured);
        return checked({ buffer, origin: { x: 0, y: 0 }, pageHeight: size.height, segments: 1 }, check);
    }
    const shot = await clipBuffer(page, { x: 0, y: 0, width: size.width, height: size.height }, check);
    return checked({ ...shot, origin: { x: 0, y: 0 }, pageHeight: size.height }, check);
}

async function elementCapture(page, selector) {
    await page.evaluate(() => window.scrollTo({ top: 0, left: 0, behavior: 'instant' }));
    await page.waitForTimeout(300);
    const size = await docSize(page);
    const rect = await page.evaluate((sel) => {
        const el = document.querySelector(sel);
        if (!el) return null;
        const r = el.getBoundingClientRect();
        return { x: r.left + window.scrollX, y: r.top + window.scrollY, width: r.width, height: r.height };
    }, selector);
    if (!rect || rect.width < 1 || rect.height < 1) throw new Error(`${selector} has no box to capture`);
    // ELEMENT_MARGIN px of the page above and below: a heading set tight (line height about 1) draws its
    // glyphs above its own box, and a section without top padding starts with its heading.
    const x = Math.max(0, Math.floor(rect.x));
    const y = Math.max(0, Math.floor(rect.y) - ELEMENT_MARGIN);
    const bottom = Math.min(size.height, Math.ceil(rect.y + rect.height) + ELEMENT_MARGIN);
    const clip = { x, y, width: Math.min(size.width - x, Math.ceil(rect.x + rect.width) - x), height: bottom - y };
    // Chrome paints position: fixed elements once, where they sit in the first screen. A target that starts in the
    // first screen (the calculators page's #tools) would get the phone tab bar, the floating buttons and its own
    // mobile summary bar painted across it: they are hidden for the capture (visibility only, no layout change) and
    // restored. Their own states and the full-page captures show them.
    const hidden = await page.evaluate(() => {
        let n = 0;
        for (const el of document.querySelectorAll('body *')) {
            if (getComputedStyle(el).position !== 'fixed' || el.closest('[data-qa-fixed-hidden]')) continue;
            el.setAttribute('data-qa-fixed-hidden', el.style.getPropertyValue('visibility') || '-');
            el.style.setProperty('visibility', 'hidden', 'important');
            n += 1;
        }
        return n;
    });
    const check = newCheck(size.height, clip.height);
    try {
        const shot = await clipBuffer(page, clip, check);
        return checked({ ...shot, origin: { x, y }, pageHeight: size.height, fixedHidden: hidden }, check);
    } finally {
        await page.evaluate(() => {
            for (const el of document.querySelectorAll('[data-qa-fixed-hidden]')) {
                const before = el.getAttribute('data-qa-fixed-hidden');
                if (before === '-') el.style.removeProperty('visibility');
                else el.style.setProperty('visibility', before);
                el.removeAttribute('data-qa-fixed-hidden');
            }
        }).catch(() => {});
    }
}

async function viewportCapture(page) {
    const buffer = await page.screenshot({ ...SHOT });
    const scroll = await page.evaluate(() => ({ x: window.scrollX, y: window.scrollY }));
    const size = await docSize(page);
    return { buffer, origin: scroll, pageHeight: size.height, segments: 1 };
}

/** --mask: the copyright line (any element whose text holds "© <year>") and every live office-hours status. */
function maskTargets() {
    const set = new Set(document.querySelectorAll('[data-open-status], [data-hours-status], .d2-footer__copyright'));
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    while (walker.nextNode()) {
        const node = walker.currentNode;
        if (/©\s*\d{4}/.test(node.nodeValue || '') && node.parentElement) set.add(node.parentElement);
    }
    const out = [];
    for (const el of set) {
        const r = el.getBoundingClientRect();
        if (r.width < 1 || r.height < 1 || getComputedStyle(el).visibility === 'hidden') continue;
        out.push({ x: r.left + window.scrollX, y: r.top + window.scrollY, w: r.width, h: r.height });
    }
    return out;
}

async function applyMask(page, shot) {
    const rects = await page.evaluate(script(maskTargets));
    const meta = await sharp(shot.buffer).metadata();
    const layers = [];
    for (const r of rects) {
        const left = Math.max(0, Math.floor(r.x - shot.origin.x) - 2);
        const top = Math.max(0, Math.floor(r.y - shot.origin.y) - 2);
        const width = Math.min(meta.width - left, Math.ceil(r.w) + 4);
        const height = Math.min(meta.height - top, Math.ceil(r.h) + 4);
        if (width < 1 || height < 1 || left >= meta.width || top >= meta.height) continue;
        layers.push({ input: { create: { width, height, channels: 4, background: '#FF00FF' } }, left, top });
    }
    if (!layers.length) return { ...shot, masked: 0 };
    return { ...shot, buffer: await sharp(shot.buffer).composite(layers).png().toBuffer(), masked: layers.length };
}

// ------------------------------------------------------------------------------------------ jobs
const designDir = (design) => `d${design}`;
const idOf = (job) => `${designDir(job.design)}/${job.kind === 'state' ? `state-${job.key}` : job.key}-${job.locale}-${job.width}`;

function buildJobs() {
    const jobs = [];
    const pageKeys = argv.pages === 'none' ? [] : list(argv.pages, PAGES.map((p) => p.key));
    const unknownPages = pageKeys.filter((k) => !PAGES.some((p) => p.key === k));
    const stateKeys = STATES_ON ? list(argv['state-list'], STATES.map((s) => s.key)) : [];
    const unknownStates = stateKeys.filter((k) => !STATES.some((s) => s.key === k));
    if (unknownPages.length || unknownStates.length) {
        console.error(`Unknown ${unknownPages.length ? `pages: ${unknownPages.join(', ')}` : ''}${unknownStates.length ? ` states: ${unknownStates.join(', ')}` : ''}`);
        console.error(`Pages: ${PAGES.map((p) => p.key).join(', ')}\nStates: ${STATES.map((s) => s.key).join(', ')}`);
        process.exit(2);
    }
    for (const design of DESIGNS) {
        for (const def of PAGES.filter((p) => pageKeys.includes(p.key))) {
            for (const locale of LOCALES) for (const width of WIDTHS) jobs.push({ kind: 'page', key: def.key, def, design, locale, width });
        }
        for (const def of STATES.filter((s) => stateKeys.includes(s.key))) {
            for (const locale of LOCALES) {
                for (const width of WIDTHS) if (!def.widths || def.widths.includes(width)) jobs.push({ kind: 'state', key: def.key, def, design, locale, width });
            }
        }
    }
    return jobs;
}

async function runJob(browser, job) {
    const id = idOf(job);
    const def = job.def;
    const expectStatus = def.status ?? 200;
    const url = job.kind === 'page' ? urlOf(job.locale, def) : urlOf(job.locale, statePage(def));
    const record = {
        id,
        design: job.design,
        kind: job.kind,
        key: job.key,
        label: def.label,
        locale: job.locale,
        width: job.width,
        url,
        capture: def.capture ?? 'full',
        target: def.target ?? (def.anchor ? `#${def.anchor}` : null),
        capturedAt: new Date().toISOString(),
        available: true,
        notes: [],
    };
    if (def.designs && !def.designs.includes(job.design)) {
        // A page or state of one design only (the calculators page is Design 2's; Design 1 redirects the address home).
        return Object.assign(record, { available: false, reason: `Design ${job.design} has no ${def.page ?? def.key} page (Design ${def.designs.join(', ')} only)`, ms: 0 });
    }
    const { context, blocked } = await openContext(browser, job);
    const page = await context.newPage();
    const watch = watchPage(page, { expectStatus, ignoreHosts: IGNORED_HOSTS });
    const started = Date.now();
    try {
        const { response, scrollSteps } = await load(page, watch, url, { home: pathOf(job.kind === 'page' ? def : statePage(def)) === '' });
        record.status = response?.status() ?? null;
        record.expectedStatus = expectStatus;
        record.finalUrl = page.url();
        record.scrollSteps = scrollSteps;
        record.html = await page.evaluate(() => ({ lang: document.documentElement.lang, dir: document.documentElement.dir, design: document.documentElement.dataset.design ?? null }));
        // Design 2's layout sets html[data-design="2"]; a view Design 2 lacks falls back to Design 1's (no attribute).
        const rendered = record.html.design === '2' ? 2 : 1;
        if (rendered !== job.design) record.notes.push(`Rendered by Design ${rendered}'s layout, not Design ${job.design}'s (view fallback).`);
        // The input the CSS sees must be the device's: touch styles (48 px targets, no hover) on touch widths.
        record.input = await page.evaluate(() => ({
            pointer: ['coarse', 'fine', 'none'].find((v) => window.matchMedia(`(pointer: ${v})`).matches) ?? null,
            hover: ['none', 'hover'].find((v) => window.matchMedia(`(hover: ${v})`).matches) ?? null,
            maxTouchPoints: navigator.maxTouchPoints,
        }));
        const want = inputOf(job.width);
        if (record.input.pointer !== want.pointer || record.input.hover !== want.hover) {
            throw new Error(`the page sees pointer: ${record.input.pointer}, hover: ${record.input.hover}; a ${isTouch(job.width) ? 'touch' : 'desktop'} capture at ${job.width} px needs pointer: ${want.pointer}, hover: ${want.hover}`);
        }

        let shot;
        if (job.kind === 'state') {
            const prepared = await def.prepare(page, job.design, job.width);
            if (!prepared.ok) {
                Object.assign(record, { available: false, reason: prepared.reason });
                return record;
            }
            record.notes.push(...prepared.notes);
            await settle(page, watch);
            shot = def.capture === 'element' ? await elementCapture(page, def.target) : await viewportCapture(page);
        } else if (def.capture === 'element') {
            if (!(await exists(page, def.target))) {
                Object.assign(record, { available: false, reason: `the page has no ${def.target} element in this design` });
                return record;
            }
            shot = await elementCapture(page, def.target);
        } else if (def.capture === 'viewport') {
            if (!(await exists(page, `[id="${def.anchor}"]`))) {
                Object.assign(record, { available: false, reason: `the Legal Resources page has no #${def.anchor} anchor in this design` });
                return record;
            }
            // Back to the anchor the way a visitor's link lands there (hashchange included), after the scroll-through.
            await page.evaluate((anchor) => {
                window.location.hash = '';
                window.location.hash = anchor;
            }, def.anchor);
            await page.waitForTimeout(600);
            await settle(page, watch);
            shot = await viewportCapture(page);
        } else {
            shot = await fullPageCapture(page);
        }
        if (job.kind === 'page' && def.note) record.notes.unshift(def.note);
        if (MASK) shot = await applyMask(page, shot);
        const file = path.join(OUT, `${id}.jpg`);
        mkdirSync(path.dirname(file), { recursive: true });
        const info = await sharp(shot.buffer).jpeg(JPEG).toFile(file);
        watch.freeze();
        Object.assign(record, {
            file: `${id}.jpg`,
            imageWidth: info.width,
            imageHeight: info.height,
            bytes: info.size,
            pageHeight: shot.pageHeight,
            segments: shot.segments,
            origin: shot.origin,
            masked: MASK ? shot.masked ?? 0 : null,
            layoutCheck: shot.layoutCheck ?? null, // viewport captures paint only the viewport: nothing to check
        });
        return record;
    } finally {
        record.ms = Date.now() - started;
        const blockedSet = [...new Set(blocked)];
        record.blocked = blockedSet;
        // Requests this script blocked are not the page's errors.
        record.consoleErrors = watch.errors.filter((e) => !blockedSet.some((u) => e.includes(u))).slice(0, 40);
        record.consoleWarnings = watch.warnings.slice(0, 20);
        await context.close().catch(() => {});
    }
}

async function pool(items, size, fn) {
    let next = 0;
    await Promise.all(
        Array.from({ length: Math.min(size, items.length) }, async () => {
            while (next < items.length) {
                const i = next++;
                await fn(items[i], i);
            }
        }),
    );
}

const freeBytes = () => {
    const s = statfsSync(ROOT);
    return s.bavail * s.bsize;
};
const gib = (b) => `${(b / 2 ** 30).toFixed(2)} GiB`;

function readManifest() {
    try {
        return JSON.parse(readFileSync(MANIFEST, 'utf8'));
    } catch {
        return { phase: PHASE, runs: [], captures: [] };
    }
}

function git(args) {
    try {
        return execFileSync('git', args, { cwd: ROOT, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim();
    } catch {
        return null;
    }
}

async function capture() {
    const jobs = buildJobs();
    try {
        const res = await fetch(`${BASE}/en/`, { headers: { cookie: 'summit_qa=1' }, redirect: 'manual', signal: AbortSignal.timeout(20_000) });
        if (res.status >= 500) throw new Error(`HTTP ${res.status}`);
    } catch (error) {
        console.error(`Nothing answers at ${BASE} (${error.message}). Start a server of this tree (docs/d2/running.md) or pass --base.`);
        return { code: 2 };
    }
    // The brief's calculators page: record what its obvious URL answers today.
    const calculatorsRoute = await fetch(`${BASE}/en/calculators/`, { headers: { cookie: 'summit_qa=1' }, redirect: 'manual' })
        .then((r) => r.status)
        .catch(() => null);

    mkdirSync(OUT, { recursive: true });
    const freeBefore = freeBytes();
    console.log(`Screens ${PHASE}: ${jobs.length} jobs (designs ${DESIGNS.join(', ')}; ${LOCALES.join(', ')}; ${WIDTHS.join(', ')} px), base ${BASE}, ${CONCURRENCY} at a time, clock ${CLOCK ?? 'real'}, ${TIMEZONE}${MASK ? ', masked' : ''}; ${gib(freeBefore)} free`);
    if (freeBefore < MIN_FREE) {
        console.error(`Only ${gib(freeBefore)} free (minimum ${gib(MIN_FREE)}): nothing captured.`);
        return { code: 2 };
    }
    // Two browsers: desktop widths, and touch widths with a touch screen's pointer and hover (TOUCH_BLINK_SETTINGS).
    const touchArgs = [...DETERMINISTIC, `--blink-settings=${TOUCH_BLINK_SETTINGS}`];
    const browsers = new Map();
    const browserFor = (width) => {
        const kind = isTouch(width) ? 'touch' : 'desktop';
        if (!browsers.has(kind)) browsers.set(kind, chromium.launch({ channel: 'chrome', headless: true, args: kind === 'touch' ? touchArgs : DETERMINISTIC }));
        return browsers.get(kind);
    };
    const records = [];
    const started = Date.now();
    let chromeVersion = null;
    let stoppedForDisk = false;
    try {
        if (jobs.length) chromeVersion = (await browserFor(jobs[0].width)).version();
        await pool(jobs, CONCURRENCY, async (job) => {
            const id = idOf(job);
            if (stoppedForDisk || freeBytes() < MIN_FREE) {
                stoppedForDisk = true;
                records.push({ id, design: job.design, kind: job.kind, key: job.key, label: job.def.label, locale: job.locale, width: job.width, error: `skipped: less than ${gib(MIN_FREE)} free` });
                return;
            }
            let record;
            for (let attempt = 1; attempt <= 2; attempt += 1) {
                try {
                    record = await runJob(await browserFor(job.width), job);
                    record.attempts = attempt;
                    record.error = null;
                    break;
                } catch (error) {
                    record = { id, design: job.design, kind: job.kind, key: job.key, label: job.def.label, locale: job.locale, width: job.width, attempts: attempt, error: String(error?.message || error).split('\n')[0] };
                    if (error?.layoutCheck) record.layoutCheck = error.layoutCheck;
                }
            }
            records.push(record);
            const done = records.length;
            const mark = record.error ? 'FAIL' : record.available === false ? 'N/A ' : 'OK  ';
            const detail = record.error
                ? record.error
                : record.available === false
                  ? record.reason
                  : `${record.status} ${record.imageWidth}x${record.imageHeight} ${(record.bytes / 1024).toFixed(0)} KB ${(record.ms / 1000).toFixed(1)} s${record.consoleErrors.length ? `, ${record.consoleErrors.length} console error(s)` : ''}`;
            console.log(`${String(done).padStart(4)}/${jobs.length} ${mark} ${id}  ${detail}`);
        });
    } finally {
        for (const launching of browsers.values()) await (await launching.catch(() => null))?.close();
    }

    const manifest = readManifest();
    const byId = new Map(manifest.captures.map((c) => [c.id, c]));
    for (const r of records) byId.set(r.id, r);
    manifest.phase = PHASE;
    manifest.updatedAt = new Date().toISOString();
    manifest.captures = [...byId.values()].sort((a, b) => a.id.localeCompare(b.id, 'en', { numeric: true }));
    const failed = records.filter((r) => r.error);
    manifest.runs.push({
        startedAt: new Date(started).toISOString(),
        finishedAt: new Date().toISOString(),
        seconds: Math.round((Date.now() - started) / 1000),
        options: { designs: DESIGNS, locales: LOCALES, widths: WIDTHS, pages: argv.pages ?? 'all', states: STATES_ON ? argv['state-list'] ?? 'all' : 'off', base: BASE, mask: MASK, concurrency: CONCURRENCY, clock: CLOCK ?? 'real', timezone: TIMEZONE },
        rules: 'Google Chrome (channel chrome, software raster); touch widths in a second Chrome with a touch screen\'s pointer and hover (--blink-settings), checked per capture; cookies summit_design + summit_qa=1; reduced motion; device scale 1; fonts ready; one scroll-through; settled; full page (segments of 8,000 px above 16,000 px, stitched at the heights Chrome returned), viewport, or element (clipped at scroll 0, 24 px margin above and below); full-page and element captures fail when the settled page height differs before a segment or after the capture, pointer or hover change while Chrome paints, or the image is not the region asked for (layoutCheck); JPEG q80 (mozjpeg)',
        touchBlinkSettings: TOUCH_BLINK_SETTINGS,
        git: { head: git(['rev-parse', '--short', 'HEAD']), branch: git(['rev-parse', '--abbrev-ref', 'HEAD']), changedFiles: (git(['status', '--porcelain']) ?? '').split('\n').filter(Boolean).length },
        chrome: chromeVersion,
        siteMap: MAP.source,
        calculatorsRouteStatus: calculatorsRoute,
        jobs: jobs.length,
        captured: records.filter((r) => !r.error && r.available !== false).length,
        notAvailable: records.filter((r) => r.available === false).length,
        failed: failed.length,
        stoppedForDisk,
        bytesWritten: records.reduce((n, r) => n + (r.bytes || 0), 0),
        freeBefore,
        freeAfter: freeBytes(),
    });
    writeFileSync(MANIFEST, `${JSON.stringify(manifest, null, 2)}\n`);
    const run = manifest.runs.at(-1);
    console.log(`\nDone in ${run.seconds} s: ${run.captured} captured, ${run.notAvailable} not existing (recorded), ${run.failed} failed; ${(run.bytesWritten / 2 ** 20).toFixed(1)} MiB written; ${gib(run.freeAfter)} free.`);
    for (const r of failed) console.log(`  FAIL ${r.id}: ${r.error}`);
    return { code: failed.length ? 1 : 0, manifest };
}

// ------------------------------------------------------------------------------------------ contact sheets
const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
const FONT = 'Helvetica, Arial, sans-serif';

function svgLines(width, height, lines, { size = 14, weight = 400, color = '#26262B', anchor = 'start', lineHeight = 1.35 } = {}) {
    const x = anchor === 'middle' ? width / 2 : 0;
    const text = lines
        .map((t, i) => `<text x="${x}" y="${Math.round(size + i * size * lineHeight)}" font-family="${FONT}" font-size="${size}" font-weight="${weight}" fill="${color}" text-anchor="${anchor}">${esc(t)}</text>`)
        .join('');
    return Buffer.from(`<svg xmlns="http://www.w3.org/2000/svg" width="${Math.max(1, Math.round(width))}" height="${Math.max(1, Math.round(height))}">${text}</svg>`);
}

function wrap(text, chars) {
    const words = String(text).split(/\s+/);
    const lines = [];
    let line = '';
    for (const w of words) {
        if ((line ? `${line} ${w}` : w).length > chars && line) {
            lines.push(line);
            line = w;
        } else {
            line = line ? `${line} ${w}` : w;
        }
    }
    if (line) lines.push(line);
    return lines;
}

/** Widths present in the manifest, widest first. */
const orderedWidths = (caps) => [...new Set(caps.map((c) => c.width))].sort((a, b) => b - a);

const SHEET = { pad: 28, colGap: 28, rowGap: 30, stripGap: 8, stripMax: 2000, minWidth: 1100, colHead: 36, rowLabel: 70, caption: 44, bg: '#E7E7EA', frame: '#B9B9C0' };

/**
 * One labelled grid: rows = locales, columns = widths, every capture at the same scale. A capture taller
 * than SHEET.stripMax (after scaling) is cut into equal strips laid side by side, read top to bottom, then
 * left to right (start of the page in the first strip).
 */
async function buildSheet(file, { title, subtitle, locales, widths, cells, scale }) {
    const tiles = new Map();
    for (const locale of locales) {
        for (const width of widths) {
            const e = cells.get(`${locale}|${width}`);
            const key = `${locale}|${width}`;
            const source = e?.file ? path.join(OUT, e.file) : null;
            if (!e || e.error || e.available === false || !source || !existsSync(source)) {
                const why = !e ? 'not captured in this run' : e.error ? `failed: ${e.error}` : e.available === false ? `does not exist: ${e.reason}` : 'file missing';
                const w = Math.max(150, Math.round(width * scale));
                tiles.set(key, { missing: true, w, h: 150, lines: wrap(why, Math.max(18, Math.floor(w / 7.2))).slice(0, 8) });
                continue;
            }
            const tw = Math.max(1, Math.round(e.imageWidth * scale));
            const th = Math.max(1, Math.round(e.imageHeight * scale));
            const n = Math.max(1, Math.ceil(th / SHEET.stripMax));
            const sh = Math.ceil(th / n);
            const resized = await sharp(source).resize(tw, th, { fit: 'fill' }).png().toBuffer();
            const strips = [];
            for (let i = 0; i < n; i += 1) {
                const top = i * sh;
                const h = Math.min(sh, th - top);
                if (h < 1) break;
                strips.push({ input: n === 1 ? resized : await sharp(resized).extract({ left: 0, top, width: tw, height: h }).png().toBuffer(), w: tw, h });
            }
            const caption = `${e.status ?? ''}${e.status ? ' ' : ''}${e.imageWidth}x${e.imageHeight}${e.consoleErrors?.length ? `, ${e.consoleErrors.length} console err` : ''}${strips.length > 1 ? `, ${strips.length} strips` : ''}`;
            tiles.set(key, { strips, w: strips.length * tw + (strips.length - 1) * SHEET.stripGap, h: sh, caption, name: path.basename(e.file) });
        }
    }
    const colW = widths.map((width) => Math.max(150, ...locales.map((l) => tiles.get(`${l}|${width}`).w)));
    const rowH = locales.map((l) => Math.max(...widths.map((w) => tiles.get(`${l}|${w}`).h)) + SHEET.caption);
    const W = Math.max(SHEET.minWidth, SHEET.pad * 2 + SHEET.rowLabel + colW.reduce((a, b) => a + b, 0) + SHEET.colGap * (widths.length - 1));
    const inner = W - SHEET.pad * 2;
    const titleLines = wrap(title, Math.floor(inner / 13.5)).slice(0, 3);
    const subLines = wrap(subtitle, Math.floor(inner / 7.4)).slice(0, 3);
    const head = titleLines.length * 32 + subLines.length * 19 + 24;
    const H = SHEET.pad * 2 + head + SHEET.colHead + rowH.reduce((a, b) => a + b, 0) + SHEET.rowGap * (locales.length - 1);
    const layers = [
        { input: svgLines(inner, titleLines.length * 32 + 4, titleLines, { size: 24, weight: 700, lineHeight: 1.3 }), left: SHEET.pad, top: SHEET.pad },
        { input: svgLines(inner, subLines.length * 19 + 6, subLines, { size: 14, color: '#4A4A52' }), left: SHEET.pad, top: SHEET.pad + titleLines.length * 32 + 8 },
    ];
    let x = SHEET.pad + SHEET.rowLabel;
    const colX = colW.map((w) => {
        const at = x;
        x += w + SHEET.colGap;
        return at;
    });
    widths.forEach((width, c) => layers.push({ input: svgLines(colW[c], 28, [`${width} px`], { size: 18, weight: 700 }), left: colX[c], top: SHEET.pad + head }));
    let y = SHEET.pad + head + SHEET.colHead;
    for (const [r, locale] of locales.entries()) {
        layers.push({ input: svgLines(SHEET.rowLabel - 10, 30, [locale.toUpperCase()], { size: 22, weight: 700 }), left: SHEET.pad, top: y });
        for (const [c, width] of widths.entries()) {
            const tile = tiles.get(`${locale}|${width}`);
            if (tile.missing) {
                layers.push({ input: { create: { width: tile.w, height: tile.h, channels: 3, background: '#D4D4D9' } }, left: colX[c], top: y });
                layers.push({ input: svgLines(tile.w - 16, tile.h - 16, tile.lines, { size: 13, color: '#3A3A40' }), left: colX[c] + 8, top: y + 8 });
                continue;
            }
            let sx = colX[c];
            for (const strip of tile.strips) {
                layers.push({ input: { create: { width: strip.w + 2, height: strip.h + 2, channels: 3, background: SHEET.frame } }, left: sx - 1, top: y - 1 });
                layers.push({ input: strip.input, left: sx, top: y });
                sx += strip.w + SHEET.stripGap;
            }
            const cw = Math.max(tile.w, 150);
            layers.push({ input: svgLines(cw, SHEET.caption - 6, [tile.name, tile.caption], { size: 12, color: '#3A3A40', lineHeight: 1.3 }), left: colX[c], top: y + tile.h + 6 });
        }
        y += rowH[r] + SHEET.rowGap;
    }
    mkdirSync(path.dirname(file), { recursive: true });
    await sharp({ create: { width: W, height: H, channels: 3, background: SHEET.bg }, limitInputPixels: false }).composite(layers).jpeg(JPEG).toFile(file);
    return { width: W, height: H };
}

async function sheetsAndIndex(manifest) {
    const caps = manifest.captures;
    const lastRun = manifest.runs.at(-1) ?? {};
    const locales = ['en', 'ar', 'ru'].filter((l) => caps.some((c) => c.locale === l));
    const allWidths = orderedWidths(caps);
    const sheets = [];
    for (const design of [1, 2]) {
        const groups = [
            ...PAGES.map((p) => ({ kind: 'page', def: p })),
            ...STATES.map((s) => ({ kind: 'state', def: s })),
        ];
        for (const { kind, def } of groups) {
            const entries = caps.filter((c) => c.design === design && c.kind === kind && c.key === def.key);
            if (!entries.length) continue;
            const widths = allWidths.filter((w) => entries.some((e) => e.width === w));
            const cells = new Map(entries.map((e) => [`${e.locale}|${e.width}`, e]));
            const name = `d${design}-${kind === 'state' ? `state-${def.key}` : def.key}`;
            const file = path.join(OUT, 'sheets', `${name}.jpg`);
            const capture = def.capture ?? 'full';
            const scale = capture === 'full' ? 0.25 : 0.4;
            const pathLabel = kind === 'page' ? `/xx/${pathOf(def)}${def.anchor ? `#${def.anchor}` : ''}${def.target ? ` ${def.target}` : ''}` : `/xx/ ${def.target ?? ''}`;
            const available = entries.filter((e) => e.file && !e.error && e.available !== false).length;
            const subtitle = `${pathLabel.trim()}  |  ${capture} capture, thumbnails at ${Math.round(scale * 100)} %  |  ${available} of ${entries.length} captured  |  phase ${PHASE}, ${(lastRun.options?.clock ?? 'clock ?')}, ${lastRun.options?.timezone ?? ''}${lastRun.options?.mask ? ', masked' : ''}`;
            const size = await buildSheet(file, { title: `Design ${design}: ${def.label}`, subtitle, locales: locales.filter((l) => entries.some((e) => e.locale === l)), widths, cells, scale });
            sheets.push({ design, kind, key: def.key, label: def.label, file: `sheets/${name}.jpg`, ...size, captured: available, total: entries.length });
            console.log(`sheet ${path.relative(ROOT, file)} (${size.width}x${size.height})`);
        }
    }
    writeFileSync(path.join(OUT, 'index.html'), indexHtml(manifest, sheets));
    console.log(`index ${path.relative(ROOT, path.join(OUT, 'index.html'))}`);
    return sheets;
}

function indexHtml(manifest, sheets) {
    const caps = manifest.captures;
    const run = manifest.runs.at(-1) ?? {};
    const widths = orderedWidths(caps);
    const cell = (c) => {
        if (!c) return '<td class="none">-</td>';
        if (c.error) return `<td class="fail" title="${esc(c.error)}">failed</td>`;
        if (c.available === false) return `<td class="na" title="${esc(c.reason)}">n/a</td>`;
        const err = c.consoleErrors?.length ? ` <span class="err" title="${esc(c.consoleErrors.join('\n'))}">${c.consoleErrors.length} err</span>` : '';
        return `<td><a href="${esc(c.file)}">${c.status ?? ''} ${c.imageWidth}x${c.imageHeight}</a>${err}</td>`;
    };
    const table = (design, kind, defs) => {
        const rows = defs
            .filter((d) => caps.some((c) => c.design === design && c.kind === kind && c.key === d.key))
            .map((d) => {
                const tds = ['en', 'ar', 'ru'].flatMap((l) => widths.map((w) => cell(caps.find((c) => c.design === design && c.kind === kind && c.key === d.key && c.locale === l && c.width === w))));
                const sheet = sheets.find((s) => s.design === design && s.kind === kind && s.key === d.key);
                return `<tr><th scope="row">${esc(d.label)}${sheet ? `<br><a href="${esc(sheet.file)}">contact sheet</a>` : ''}</th>${tds.join('')}</tr>`;
            });
        if (!rows.length) return '';
        const head = ['en', 'ar', 'ru'].map((l) => `<th colspan="${widths.length}" scope="colgroup">${l.toUpperCase()}</th>`).join('');
        const sub = ['en', 'ar', 'ru'].flatMap(() => widths.map((w) => `<th scope="col">${w}</th>`)).join('');
        return `<table><thead><tr><th rowspan="2">${kind === 'state' ? 'State' : 'Page'}</th>${head}</tr><tr>${sub}</tr></thead><tbody>${rows.join('')}</tbody></table>`;
    };
    const notAvailable = caps.filter((c) => c.available === false);
    const failed = caps.filter((c) => c.error);
    const designs = [1, 2].filter((d) => caps.some((c) => c.design === d));
    return `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Screens ${esc(manifest.phase)}</title>
<style>
:root { --ink: #0b0b0c; --ink-2: #3f3f46; --line: #e4e4e7; --band: #f6f6f7; --bad: #b42318; color-scheme: light; }
body { margin: 0; padding: 24px 32px 64px; font: 15px/1.5 -apple-system, system-ui, sans-serif; color: var(--ink); background: #fff; }
h1 { font-size: 28px; margin: 0 0 4px; font-weight: 600; } h2 { font-size: 22px; margin: 40px 0 12px; font-weight: 600; } h3 { font-size: 17px; margin: 28px 0 8px; font-weight: 600; }
p, li { color: var(--ink-2); max-width: 90ch; }
.sheets { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
.sheets a { display: block; border: 1px solid var(--line); border-radius: 8px; padding: 10px; color: var(--ink); text-decoration: none; background: var(--band); }
.sheets img { display: block; width: 100%; height: 180px; object-fit: cover; object-position: top; border-radius: 4px; background: #ddd; }
.sheets span { display: block; margin-top: 6px; font-size: 13px; }
table { border-collapse: collapse; font-size: 12px; margin-block: 8px 24px; }
th, td { border: 1px solid var(--line); padding: 4px 6px; text-align: start; vertical-align: top; white-space: nowrap; }
tbody th { white-space: normal; min-width: 220px; font-weight: 500; }
td.na { color: #6b6b73; background: var(--band); } td.fail { color: var(--bad); font-weight: 600; } td.none { color: #a1a1aa; }
.err { color: var(--bad); }
code { font-size: 13px; }
</style>
</head>
<body>
<h1>Screenshot matrix: ${esc(manifest.phase)}</h1>
<p>Brief <code>docs/briefs/design-2.1-and-admin-cms.md</code> section 7.3. Last run ${esc(run.startedAt ?? '')} to ${esc(run.finishedAt ?? '')} against <code>${esc(run.options?.base ?? '')}</code>, git ${esc(run.git?.head ?? '?')} (${esc(run.git?.branch ?? '?')}, ${run.git?.changedFiles ?? '?'} changed files), clock ${esc(run.options?.clock ?? '')}, time zone ${esc(run.options?.timezone ?? '')}${run.options?.mask ? ', masked' : ''}. ${caps.filter((c) => c.file && !c.error).length} captures, ${notAvailable.length} pages or states that do not exist, ${failed.length} failed. Manifest: <a href="manifest.json">manifest.json</a>.</p>
<h2>Contact sheets</h2>
${designs
    .map(
        (d) => `<h3>Design ${d}</h3><div class="sheets">${sheets
            .filter((s) => s.design === d)
            .map((s) => `<a href="${esc(s.file)}"><img src="${esc(s.file)}" alt="" loading="lazy"><span>${esc(s.kind === 'state' ? `State: ${s.label}` : s.label)} (${s.captured}/${s.total})</span></a>`)
            .join('')}</div>`,
    )
    .join('\n')}
${designs.map((d) => `<h2>Design ${d}: captures</h2>${table(d, 'page', PAGES)}${table(d, 'state', STATES)}`).join('\n')}
<h2>Pages and states that do not exist</h2>
<ul>${notAvailable.map((c) => `<li><code>${esc(c.id)}</code>: ${esc(c.reason)}</li>`).join('') || '<li>none</li>'}</ul>
<h2>Failed captures</h2>
<ul>${failed.map((c) => `<li><code>${esc(c.id)}</code>: ${esc(c.error)}</li>`).join('') || '<li>none</li>'}</ul>
</body>
</html>
`;
}

// ------------------------------------------------------------------------------------------ main
async function main() {
    if (!existsSync(path.join(ROOT, 'node_modules/@playwright/test'))) {
        console.error('Playwright is not installed (npm ci).');
        return 2;
    }
    let code = 0;
    let manifest;
    if (SHEETS_ONLY) {
        manifest = readManifest();
        if (!manifest.captures.length) {
            console.error(`No captures in ${path.relative(ROOT, MANIFEST)}.`);
            return 2;
        }
    } else {
        const result = await capture();
        code = result.code;
        manifest = result.manifest;
        if (!manifest) return code;
    }
    if (SHEETS) await sheetsAndIndex(manifest);
    return code;
}

main().then(
    (code) => process.exit(code),
    (error) => {
        console.error(`screens could not run: ${error?.stack || error}`);
        process.exit(2);
    },
);
