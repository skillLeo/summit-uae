/**
 * Language links ([data-lang-switch="ar"]) keep the current #hash and remember the choice in the
 * summit_lang cookie for 12 months, exactly like Design 1 (resources/js/modules/chrome.js): the root
 * URL "/" then redirects to that language. Never redirects on its own.
 */
const YEAR = 60 * 60 * 24 * 365;

export default function langSwitch() {
    document.addEventListener('click', (e) => {
        const link = e.target.closest?.('a[data-lang-switch]');
        if (!link) return;
        const secure = location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = `summit_lang=${encodeURIComponent(link.dataset.langSwitch)}; Max-Age=${YEAR}; Path=/; SameSite=Lax${secure}`;
        if (location.hash && !link.hash && document.documentElement.dataset.page !== 'error') link.hash = location.hash;
    });
}
