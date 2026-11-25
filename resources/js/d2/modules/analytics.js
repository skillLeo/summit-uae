/**
 * Google Analytics 4: the consent bar and the events (docs/briefs/design2-only-black-header-ga4.md C4, C5). Does
 * nothing unless the page printed the tag (resources/views/designs/d2/partials/analytics.blade.php defines
 * window.gtag: production, a valid measurement ID, a public page, nobody signed in).
 *
 * Consent (d2::partials.consent, [data-consent]): analytics starts denied (the tag's consent default). Without a stored
 * choice the bar shows; the footer's "Privacy Settings" button ([data-consent-open]) shows it again and moves focus to
 * its title, Escape closes it then and focus returns to the button. "Accept Optional Tools" →
 * gtag('consent', 'update', { analytics_storage: 'granted' }); "Reject Optional Tools" → 'denied' and the _ga cookies
 * are deleted. Ad storage, ad user data and ad personalisation are never granted. The choice is a first-party cookie
 * (summit_consent=granted|denied, 6 months, SameSite=Lax, Secure on https), read again by the tag on the next page
 * before its first hit. A choice that cannot be stored (cookies blocked) grants nothing, keeps the bar and says so in
 * the approved failure message; a stored one closes the bar with the approved saved message (a toast).
 *
 * Events, each once per action and with exactly these parameters (never a name, number, address, message, input,
 * result, amount or reference):
 *   whatsapp_click   { link_location }  a wa.me / api.whatsapp.com / whatsapp: link: the main lead event
 *   phone_click      { link_location }  a tel: link
 *   email_click      { link_location }  a mailto: link
 *   language_change  { from, to }       the language switcher ([data-lang-switch]) to another language
 *   calculator_used  { calculator }     a calculator ([data-tool]: gratuity | rent) shows a result: once per result,
 *                                       when it has stood for SETTLE_MS (never per keystroke); again only after the
 *                                       result has been gone for SETTLE_MS
 * link_location: the link's own data-link-location (or an ancestor's), else the part of the page it sits in
 * (LOCATIONS: floating, mobile_bar, sticky_bar, menu, search, header, footer, contact, hero, cta), else "contact" on
 * the Contact page and "content" anywhere else.
 *
 * Pure helpers (unit tested in __tests__/analytics.test.js): storedConsent(), consentCookie(), clickEvent(),
 * linkLocation(), gaCookieNames(), cookieDomains(), settle().
 */
import { toast } from '../lib/toast.js';

export const CONSENT_COOKIE = 'summit_consent';
export const SIX_MONTHS = 60 * 60 * 24 * 183; // seconds
export const SETTLE_MS = 1500;

const WHATSAPP_HOSTS = ['wa.me', 'api.whatsapp.com', 'web.whatsapp.com'];

/**
 * Where a link sits: the first rule whose container holds it wins (most specific first). Class and data hooks of
 * elements only, never one that may sit on <html> or <body> (the palette marks <html data-palette> once it listens).
 */
export const LOCATIONS = [
    ['[data-wa-fab]', 'floating'],
    ['[data-tabbar], .tabbar', 'mobile_bar'],
    ['[data-sticky-cta]', 'sticky_bar'],
    ['[data-sheet], .sheet', 'menu'],
    ['.palette', 'search'],
    ['.d2-header', 'header'],
    ['.d2-footer', 'footer'],
    ['.contact-card, .kc-contact, .wizard__channels', 'contact'],
    ['.hero, .inner-head', 'hero'],
    ['.cta-card, .svc-cta', 'cta'],
];

/** The stored choice ('granted' | 'denied') in a cookie string, or null. */
export function storedConsent(cookie) {
    const match = /(?:^|;\s*)summit_consent=(granted|denied)(?:;|$)/.exec(String(cookie ?? ''));
    return match ? match[1] : null;
}

/** The Set-Cookie string of a choice. */
export function consentCookie(value, secure = false) {
    return `${CONSENT_COOKIE}=${value}; Max-Age=${SIX_MONTHS}; Path=/; SameSite=Lax${secure ? '; Secure' : ''}`;
}

/** The event of a link's href: whatsapp_click, phone_click, email_click or null. */
export function clickEvent(href) {
    let url;
    try {
        url = new URL(String(href ?? ''), 'https://invalid.invalid/');
    } catch {
        return null;
    }
    if (url.protocol === 'tel:') return 'phone_click';
    if (url.protocol === 'mailto:') return 'email_click';
    if (url.protocol === 'whatsapp:') return 'whatsapp_click';
    if ((url.protocol === 'https:' || url.protocol === 'http:') && WHATSAPP_HOSTS.includes(url.hostname)) return 'whatsapp_click';
    return null;
}

const NAME = /^[a-z][a-z0-9_]{0,39}$/;

/** The link_location of a link (an element with closest()) on a page (the html data-page). */
export function linkLocation(link, page) {
    const own = link.closest('[data-link-location]')?.getAttribute('data-link-location');
    if (own && NAME.test(own)) return own;
    for (const [selector, name] of LOCATIONS) {
        if (link.closest(selector)) return name;
    }
    return page === 'contact' ? 'contact' : 'content';
}

/** The Google Analytics cookies (_ga, _ga_<stream>) in a cookie string. */
export function gaCookieNames(cookie) {
    return String(cookie ?? '')
        .split(';')
        .map((part) => part.split('=')[0].trim())
        .filter((name) => /^_ga(?:_|$)/.test(name));
}

/** The domains a cookie of this host may have been set on ('' = host only; then each parent domain). */
export function cookieDomains(hostname) {
    const host = String(hostname ?? '');
    if (host === '' || host === 'localhost' || /^[\d.]+$/.test(host) || host.includes(':')) return [''];
    const parts = host.split('.');
    const out = [''];
    for (let i = 0; i < parts.length - 1; i++) out.push(`.${parts.slice(i).join('.')}`);
    return out;
}

/**
 * Calls report() once a watched value has been `true` for `ms` without a change, then not again until it has been
 * `false` for `ms`. Returns update(isOn), to call on every change. Timers are injectable for the tests.
 */
export function settle(report, ms = SETTLE_MS, timers = { set: (fn, t) => window.setTimeout(fn, t), clear: (id) => window.clearTimeout(id) }) {
    let timer = null;
    let sent = false;
    return (on) => {
        if (timer !== null) timers.clear(timer);
        timer = null;
        if (on && !sent) {
            timer = timers.set(() => {
                timer = null;
                sent = true;
                report();
            }, ms);
        } else if (!on && sent) {
            timer = timers.set(() => {
                timer = null;
                sent = false;
            }, ms);
        }
    };
}

function track(name, params) {
    try {
        window.gtag('event', name, params);
    } catch {
        /* analytics never breaks the page */
    }
}

// ------------------------------------------------------------------ consent

function bindConsent() {
    const bar = document.querySelector('[data-consent]');
    if (!bar) return;
    const title = bar.querySelector('#consent-title');
    let opener = null;

    const setExpanded = (open) => {
        for (const button of document.querySelectorAll('[data-consent-open]')) button.setAttribute('aria-expanded', String(open));
    };
    const show = (focus) => {
        bar.hidden = false;
        setExpanded(true);
        if (focus) title?.focus({ preventScroll: true });
    };
    const hide = () => {
        const inside = bar.contains(document.activeElement);
        bar.hidden = true;
        setExpanded(false);
        if (inside) (opener ?? document.getElementById('main-content'))?.focus({ preventScroll: true });
        opener = null;
    };

    const choose = (value) => {
        const secure = window.location.protocol === 'https:';
        try {
            document.cookie = consentCookie(value, secure);
        } catch {
            /* reported below */
        }
        if (storedConsent(document.cookie) !== value) {
            if (value === 'denied') window.gtag('consent', 'update', { analytics_storage: 'denied' });
            if (bar.dataset.consentFailed) toast(bar.dataset.consentFailed, { tone: 'danger' });
            return;
        }
        window.gtag('consent', 'update', { analytics_storage: value });
        if (value === 'denied') {
            for (const name of gaCookieNames(document.cookie)) {
                for (const domain of cookieDomains(window.location.hostname)) {
                    document.cookie = `${name}=; Max-Age=0; Path=/${domain ? `; Domain=${domain}` : ''}${secure ? '; Secure' : ''}`;
                }
            }
        }
        hide();
        if (bar.dataset.consentSaved) toast(bar.dataset.consentSaved, { tone: 'success' });
    };

    bar.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-consent-choice]') : null;
        const value = button?.getAttribute('data-consent-choice');
        if (value === 'granted' || value === 'denied') choose(value);
    });
    bar.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && opener && storedConsent(document.cookie)) {
            event.preventDefault();
            hide();
        }
    });
    document.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-consent-open]') : null;
        if (!button) return;
        event.preventDefault();
        opener = button;
        show(true);
    });

    if (!storedConsent(document.cookie)) show(false);
}

// ------------------------------------------------------------------ events

function bindLinks() {
    const page = document.documentElement.dataset.page;
    const onClick = (event) => {
        if (event.type === 'auxclick' && event.button !== 1) return;
        const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
        if (!link || link.getAttribute('aria-disabled') === 'true') return;

        const lang = link.getAttribute('data-lang-switch');
        if (lang !== null) {
            const from = document.documentElement.lang;
            if (page !== 'chooser' && NAME.test(lang) && NAME.test(from) && lang !== from) track('language_change', { from, to: lang });
            return;
        }
        const name = clickEvent(link.getAttribute('href'));
        if (name) track(name, { link_location: linkLocation(link, page) });
    };
    // Capture: counted even when a later handler stops the click from bubbling.
    document.addEventListener('click', onClick, true);
    document.addEventListener('auxclick', onClick, true);
}

function bindCalculators() {
    for (const tool of document.querySelectorAll('[data-tool]')) {
        const calculator = tool.getAttribute('data-tool');
        if (calculator !== 'gratuity' && calculator !== 'rent') continue;
        const update = settle(() => track('calculator_used', { calculator }));
        new MutationObserver(() => update(tool.getAttribute('data-state') === 'result')).observe(tool, {
            attributes: true,
            attributeFilter: ['data-state'],
        });
        if (tool.getAttribute('data-state') === 'result') update(true);
    }
}

export default function analytics() {
    if (typeof window.gtag !== 'function') return;
    bindConsent();
    bindLinks();
    bindCalculators();
}
