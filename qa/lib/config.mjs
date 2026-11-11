/**
 * Design 2 QA harness (Phase G): what is captured, where it goes, how a capture is named.
 * Shared by playwright.d2.config.mjs, tests/qa/d2/*.spec.mjs and scripts/qa/{d2-report,lighthouse-d2}.mjs.
 *
 * Environment (all optional):
 *   QA_BASE_URL        site under test                     default http://127.0.0.1:8042 (TEAM 2's server)
 *   QA_D2_RUN          run folder name under qa/shots/      default today's local date (YYYY-MM-DD)
 *   QA_D2_PAGES        comma list of page keys              default all (see PAGES)
 *   QA_D2_LOCALES      comma list                           default en,ar,ru
 *   QA_D2_SERVICE      service slug for the service page    default debt-recovery-cheque-cases (S01)
 *   QA_D2_VARIANTS     extra full-page variants             reduced,nojs (only with the variants-* projects)
 *   QA_D2_HERO_TIMES   hero frame times in ms               default 0,600,1200,2000,3000
 *   QA_D2_HERO_WIDTHS  hero widths                          default 1440,1280,768,390
 *   QA_D2_AXE          0 skips axe (debugging only)         default 1
 */
import path from 'node:path';
import { fileURLToPath } from 'node:url';

export const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
export const QA_DIR = path.join(ROOT, 'qa');
export const SHOTS_DIR = path.join(QA_DIR, 'shots');
export const REFERENCE_DIR = path.join(QA_DIR, 'reference');
export const PLAYWRIGHT_DIR = path.join(ROOT, 'storage/app/qa-d2');

export const BASE_URL = (process.env.QA_BASE_URL || 'http://127.0.0.1:8042').replace(/\/+$/, '');

const pad = (n) => String(n).padStart(2, '0');
/** Local calendar date, YYYY-MM-DD (the run folder name). */
export const localDate = (d = new Date()) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

export const RUN = process.env.QA_D2_RUN || localDate();
export const runDir = (...sub) => path.join(SHOTS_DIR, RUN, ...sub);

const list = (value, fallback) =>
    value && value.trim() ? value.split(',').map((s) => s.trim()).filter(Boolean) : fallback;

/** config/summit.php locales with the browser locale used to visit them (no language-suggestion bar). */
export const LOCALES = {
    en: { dir: 'ltr', browser: 'en-GB' },
    ar: { dir: 'rtl', browser: 'ar-AE' },
    ru: { dir: 'ltr', browser: 'ru-RU' },
};
export const ACTIVE_LOCALES = list(process.env.QA_D2_LOCALES, Object.keys(LOCALES)).filter((l) => l in LOCALES);

export const WIDTHS = [1440, 1280, 768, 390];

/** Same viewports as storage/app/d2/qa/lib.mjs (390 = phone with touch, 768 = tablet with touch). */
export const VIEWPORTS = {
    1440: { viewport: { width: 1440, height: 900 } },
    1280: { viewport: { width: 1280, height: 800 } },
    768: { viewport: { width: 768, height: 1024 }, hasTouch: true },
    390: { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 },
};

/**
 * Spec §12 G1 / §9: home, one service, one guide (P03 holds the guides G01–G09 as sections), one case
 * (P04 holds case-01…06), the FAQ, the contact page, the calculators page (brief 2.1 §5.1) and the 404.
 */
const SERVICE = process.env.QA_D2_SERVICE || 'debt-recovery-cheque-cases';
export const PAGES = [
    { key: 'home', path: '', label: 'Home (P01)' },
    { key: 'service', path: `practice-areas/${SERVICE}/`, label: `Service (${SERVICE})` },
    { key: 'resources', path: 'resources/', label: 'Resources and guides (P03)' },
    { key: 'case-studies', path: 'case-studies/', label: 'Case studies (P04)' },
    { key: 'faq', path: 'faq/', label: 'FAQ (P05)' },
    { key: 'contact', path: 'contact/', label: 'Contact (P07)' },
    // Brief 2.1 §5.1: the calculators moved from the home to their own page (Design 2 only, pending copy).
    { key: 'calculators', path: 'calculators/', label: 'Calculators (/{locale}/calculators/)' },
    { key: '404', path: 'no-such-page-qa/', label: '404', status: 404 },
];
export const ACTIVE_PAGES = (() => {
    const keys = list(process.env.QA_D2_PAGES, PAGES.map((p) => p.key));
    return PAGES.filter((p) => keys.includes(p.key));
})();

/** Full-page variants: default (motion allowed, JavaScript on), reduced (prefers-reduced-motion), nojs. */
export const VARIANTS = ['default', 'reduced', 'nojs'];
export const EXTRA_VARIANTS = list(process.env.QA_D2_VARIANTS, ['reduced', 'nojs']).filter((v) => v !== 'default' && VARIANTS.includes(v));
/** The variants-* projects cover the two extreme widths only (a full matrix would triple the run). */
export const VARIANT_WIDTHS = [1440, 390];

export const HERO_TIMES = list(process.env.QA_D2_HERO_TIMES, ['0', '600', '1200', '2000', '3000']).map(Number);
export const HERO_WIDTHS = list(process.env.QA_D2_HERO_WIDTHS, WIDTHS.map(String)).map(Number).filter((w) => w in VIEWPORTS);
export const HERO_MOTIONS = ['motion', 'reduce'];

export const AXE_ENABLED = process.env.QA_D2_AXE !== '0';

/** Checklist §9.1: no empty vertical band taller than this (CSS px). */
export const MAX_BAND = 160;
/** Checklist §9.2: no text below this (CSS px), sample-document fine print excepted. */
export const MIN_TEXT = 14;

/** Third-party origins outside our control (Cloudflare Turnstile). */
export const IGNORED_HOSTS = ['challenges.cloudflare.com'];

/** Capture file stem: <page>-<locale>-<width>[-<variant>] (qa/reference/ uses the same names). */
export const captureName = (pageKey, locale, width, variant = 'default') =>
    [pageKey, locale, width, variant === 'default' ? null : variant].filter(Boolean).join('-');

/** Hero frame stem: hero-<locale>-<width>-<motion|reduce>-<tttt> (time zero-padded to 4 digits). */
export const heroName = (locale, width, motion, t) =>
    `hero-${locale}-${width}-${motion}${t === undefined ? '' : `-${String(t).padStart(4, '0')}`}`;

export const pageUrl = (locale, pagePath, base = BASE_URL) => `${base}/${locale}/${pagePath}`;
