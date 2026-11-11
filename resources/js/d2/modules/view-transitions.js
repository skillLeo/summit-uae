/**
 * Cross-document view transitions (brief 2.1 §6 "Page changes": View Transitions where supported, no full-page
 * fades). The opt-in (@view-transition { navigation: auto }) is inlined in the head by d2::layouts.app and
 * d2::pages.chooser (an external stylesheet arrives too late for it); what moves lives in
 * resources/css/d2/base/motion.css: the page itself is replaced at once (no crossfade, no slide), and only the
 * current-page mark of the tab bar (phones) and of the header's navigation row slides from the old item to the new.
 *
 * Switching language has no transition at all: the page mirrors between the reading directions, so the marks would
 * cross the whole bar. On `pageswap`, when the destination is in another language (or the page is the root
 * chooser), the outgoing page skips the transition (event.viewTransition.skipTransition()). The destination comes
 * from event.activation (Navigation API); without it, from the language link the visitor just clicked
 * ([data-lang-switch], plain primary click only).
 *
 * Progressive enhancement only: without cross-document view transitions, or under reduced motion (the opt-in is
 * then navigation: none), `pageswap` carries no viewTransition and nothing changes.
 */
const PENDING_MS = 4000;

/**
 * The language segment of a same-origin URL ("/ar/faq/" → "ar"), '' for a path outside the languages
 * (the root chooser "/"), or null for another origin or an invalid URL.
 */
export function localeOf(href, base, locales) {
    let url;
    let origin;
    try {
        url = new URL(href, base);
        origin = new URL(base).origin;
    } catch {
        return null;
    }
    if (url.origin !== origin) return null;
    const match = /^\/([a-z]{2})(?:\/|$)/.exec(url.pathname);
    return match && locales.includes(match[1]) ? match[1] : '';
}

/**
 * Is the navigation from `from` to `to` a language switch? Yes when the destination is a page of another
 * site language than the current one, or when the current page is the root chooser (every choice is one).
 */
export function isLanguageSwitch(from, to, { chooser = false, locales = ['en', 'ar', 'ru'] } = {}) {
    if (typeof from !== 'string' || typeof to !== 'string' || !from || !to) return false;
    const target = localeOf(to, from, locales);
    if (!target) return false;
    if (chooser) return true;
    const current = localeOf(from, from, locales);
    return current !== '' && current !== null && current !== target;
}

/** The site languages: every [data-lang-switch] link on the page plus the page's own language. */
function siteLocales(root) {
    const codes = new Set([root.lang]);
    root.querySelectorAll('a[data-lang-switch]').forEach((link) => codes.add(link.dataset.langSwitch));
    return [...codes].filter((code) => /^[a-z]{2}$/.test(code || ''));
}

export default function viewTransitions() {
    const root = document.documentElement;
    let pending = null;

    document.addEventListener('click', (event) => {
        const link = event.target.closest?.('a[data-lang-switch]');
        if (!link || event.defaultPrevented || event.button !== 0) return;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        if (link.target && link.target !== '_self') return;
        pending = { href: link.href, at: performance.now() };
    });

    window.addEventListener('pageswap', (event) => {
        const transition = event.viewTransition;
        const recent = pending && performance.now() - pending.at < PENDING_MS ? pending.href : null;
        pending = null;
        if (!transition) return;
        const to = event.activation?.entry?.url || recent;
        if (isLanguageSwitch(window.location.href, to, { chooser: root.dataset.page === 'chooser', locales: siteLocales(root) })) {
            transition.skipTransition();
        }
    });
}
