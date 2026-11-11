/**
 * Sticky consultation bar (<x-d2.shell.sticky-cta>, resources/views/components/d2/shell/sticky-cta.blade.php;
 * upgrade spec §8 "Sticky CTA", §12 E). Design 2.1 (brief §6 "Mobile"): one bottom bar on phones, so where the
 * tab bar exists ([data-tabbar], every page of the Design 2 layout) the bar never shows and nothing is observed
 * (components/sticky-cta.css does not draw it either). Elsewhere it shows `.is-visible` below 768 px once the page's hero
 * (data-target, a CSS selector) has scrolled out of view under the header (IntersectionObserver). It hides
 * again once the data-until element (the page's own form or end CTA) is reached, on screen or scrolled past
 * (scrolling back above it brings the bar back), while a text field has focus and while the on-screen
 * keyboard is open (so it never covers what the visitor types), and while a page's own bottom dock
 * ([data-dock], e.g. the gratuity estimate's total on the home) is on screen: the dock sits where the bar
 * would, so the bar steps aside instead of stacking a third layer (home-mobile). Without a target on the
 * page it shows after one screen of scrolling. Sheets and dialogs cover it (the page behind them is
 * inert), and the tab bar's hide-on-scroll moves it through CSS (components/sticky-cta.css).
 *
 * Click: a plain click on the bar's link hands the page's prefill (data-prefill JSON, the codes of
 * App\Design\D2\Prefill) to lib/prefill.js: on the page with the wizard it fills and scrolls to the form,
 * elsewhere it opens the consultation URL. Modified clicks keep the link (its href is the no-JS URL).
 *
 * Pure helpers (unit tested in sticky-cta.test.js): ctaVisible(), readPrefill().
 */
import { prefill } from '../lib/prefill.js';
import { keyboardOpen, typing } from './tabbar.js';

const KEYS = ['service', 'situation', 'audience', 'consultant', 'tool'];

/**
 * The bar shows on a phone layout, past the hero, before the `until` element, with no field, keyboard or
 * page dock in the way, and never on a page with the tab bar.
 */
export function ctaVisible({ mobile, past, covered, editing, keyboard, docked = false, tabbar = false }) {
    return !tabbar && !!mobile && !!past && !covered && !editing && !keyboard && !docked;
}

/** The data-prefill JSON as an object of known string codes (anything else is dropped). */
export function readPrefill(raw) {
    let value = null;
    try {
        value = JSON.parse(raw || 'null');
    } catch {
        return {};
    }
    if (!value || typeof value !== 'object' || Array.isArray(value)) return {};
    const out = {};
    for (const key of KEYS) {
        if (typeof value[key] === 'string' && /^[A-Za-z0-9-]{1,32}$/.test(value[key])) out[key] = value[key];
    }
    return out;
}

function find(selector) {
    if (!selector) return null;
    try {
        return document.querySelector(selector);
    } catch {
        return null; // not a valid selector: behave as if the page named none
    }
}

const modified = (event) => event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;

export default function stickyCta() {
    const bar = document.querySelector('[data-sticky-cta]');
    if (!bar) return;
    // One bottom bar, never two: the tab bar's page never shows this bar (ctaVisible({ tabbar: true }) is false).
    if (document.querySelector('[data-tabbar]')) {
        bar.classList.remove('is-visible');
        return;
    }

    const mobile = window.matchMedia('(width < 768px)');
    const hero = find(bar.dataset.target);
    const until = find(bar.dataset.until);
    const detail = readPrefill(bar.dataset.prefill);
    const state = { mobile: mobile.matches, past: false, covered: false, editing: false, keyboard: false, docked: false };

    const render = () => bar.classList.toggle('is-visible', ctaVisible(state));

    // A page's own bottom dock ([data-dock]: fixed, shown and hidden by its tool, or gone with its panel)
    // takes the bar's place while it is on screen. An IntersectionObserver sees both: [hidden] and a
    // display: none ancestor leave no box to intersect.
    const docks = Array.from(document.querySelectorAll('[data-dock]'));
    if (docks.length > 0 && 'IntersectionObserver' in window) {
        const showing = new Set();
        const dockObserver = new IntersectionObserver((entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) showing.add(entry.target);
                else showing.delete(entry.target);
            }
            state.docked = showing.size > 0;
            render();
        });
        docks.forEach((dock) => dockObserver.observe(dock));
    }

    // Past the hero: its bottom edge has gone above the header's bottom edge. Reached the `until` element: its
    // top is above the bottom of the screen (on screen, or scrolled past). The observers react as the page
    // scrolls; a jump that skips the visible state entirely (an #anchor, scrollTo, a restored position)
    // fires no observer callback, so the same measure also runs once scrolling settles.
    const header = () => parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--header-h')) || 0;
    const measure = () => {
        state.past = hero ? hero.getBoundingClientRect().bottom <= header() + 1 : window.scrollY > window.innerHeight;
        state.covered = until ? until.getBoundingClientRect().top < window.innerHeight : false;
        render();
    };
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(measure, { rootMargin: `-${Math.round(header())}px 0px 0px 0px` });
        if (hero) observer.observe(hero);
        if (until) observer.observe(until);
    }
    let settle = 0;
    window.addEventListener(
        'scroll',
        () => {
            window.clearTimeout(settle);
            settle = window.setTimeout(measure, hero ? 120 : 0);
        },
        { passive: true },
    );
    window.addEventListener('resize', measure);
    window.addEventListener('hashchange', () => window.requestAnimationFrame(measure));

    const editingCheck = () => {
        state.editing = typing(document.activeElement) && !bar.contains(document.activeElement);
        state.keyboard = state.editing && keyboardOpen(window);
        render();
    };
    document.addEventListener('focusin', editingCheck);
    document.addEventListener('focusout', () => window.requestAnimationFrame(editingCheck));
    window.visualViewport?.addEventListener('resize', editingCheck);

    mobile.addEventListener('change', (event) => {
        state.mobile = event.matches;
        render();
    });
    window.addEventListener('pageshow', () => {
        editingCheck();
        measure();
    });

    const link = bar.querySelector('[data-sticky-cta-link]');
    if (link && Object.keys(detail).length > 0) {
        link.addEventListener('click', (event) => {
            if (modified(event)) return;
            event.preventDefault();
            prefill(detail);
        });
    }

    measure();
}
