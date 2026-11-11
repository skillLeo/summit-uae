// Page chrome: language choice cookie + hash carry-over, suggestion bar, open/closed status,
// mobile service rows, bottom bar that hides on scroll-down, focus for in-page targets.
import { focusEl, isMobile, setCookie } from '../lib/util';

function languageLinks() {
    document.addEventListener('click', (e) => {
        const link = e.target.closest('a[data-lang-switch]');
        if (!link) return;
        setCookie('summit_lang', link.dataset.langSwitch);
        // Keep the visitor's place: carry the current fragment to the equivalent page.
        if (location.hash && !link.hash) link.hash = location.hash;
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('[data-suggest-dismiss]')) return;
        setCookie('summit_suggest_off', '1');
        document.documentElement.removeAttribute('data-suggest');
    });
}

// "Open now / Closed now · UAE time" (proposed copy), computed in the visitor's browser.
function openStatus() {
    const els = document.querySelectorAll('[data-open-status]');
    if (!els.length) return;
    const update = () => {
        const parts = Object.fromEntries(
            new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Dubai', weekday: 'short', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })
                .formatToParts(new Date())
                .map((p) => [p.type, p.value]),
        );
        const day = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].indexOf(parts.weekday);
        const minutes = Number(parts.hour) * 60 + Number(parts.minute);
        const open = day >= 1 && day <= 5 && minutes >= 9 * 60 && minutes < 18 * 60;
        els.forEach((el) => {
            el.textContent = open ? el.dataset.openLabel : el.dataset.closedLabel;
            el.classList.toggle('is-open', open);
            el.hidden = false;
        });
    };
    update();
    window.setInterval(update, 60000);
}

function serviceRows() {
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-svc-toggle]');
        if (!btn) return;
        const row = btn.closest('[data-svc-row]');
        const open = btn.getAttribute('aria-expanded') !== 'true';
        btn.setAttribute('aria-expanded', String(open));
        row.classList.toggle('is-open', open);
    });
}

function bottomBar() {
    const bar = document.querySelector('[data-bottombar]');
    if (!bar) return;
    let last = window.scrollY;
    let ticking = false;
    window.addEventListener('scroll', () => {
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(() => {
            const y = window.scrollY;
            const down = y > last + 6;
            const up = y < last - 6;
            if (document.documentElement.classList.contains('sheet-open')) {
                bar.classList.remove('is-hidden');
            } else if (down && y > 240) {
                bar.classList.add('is-hidden');
            } else if (up || y < 120) {
                bar.classList.remove('is-hidden');
            }
            if (down || up) last = y;
            ticking = false;
        });
    }, { passive: true });
    // Keyboard users always get the bar back.
    bar.addEventListener('focusin', () => bar.classList.remove('is-hidden'));
}

// In-page links (e.g. "Request a Legal Consultation" → #consultation-form) move focus to the
// target section after the browser scrolls, so keyboard and screen-reader users land there too.
function inPageFocus() {
    document.addEventListener('click', (e) => {
        const link = e.target.closest('a[href*="#"]');
        if (!link || link.origin !== location.origin || link.pathname !== location.pathname || !link.hash) return;
        if (link.hash.startsWith('#navigator=')) return;
        const target = document.getElementById(decodeURIComponent(link.hash.slice(1)));
        if (!target) return;
        window.setTimeout(() => focusEl(target, false), 0);
    });
}

// Keep the submit button above the on-screen keyboard while a form field is focused.
function keyboardAware() {
    const vv = window.visualViewport;
    if (!vv) return;
    const sync = () => {
        const active = document.activeElement;
        const form = active?.closest?.('form.form');
        document.querySelectorAll('.form__actions.is-keyboard').forEach((el) => el.classList.remove('is-keyboard'));
        if (!form || !isMobile()) return;
        const kb = Math.max(0, window.innerHeight - vv.height - vv.offsetTop);
        if (kb < 120) return;
        const actions = form.querySelector('.form__actions');
        actions?.style.setProperty('--kb', `${kb}px`);
        actions?.classList.add('is-keyboard');
    };
    vv.addEventListener('resize', sync);
    document.addEventListener('focusin', sync);
    document.addEventListener('focusout', () => window.setTimeout(sync, 50));
}

export function initChrome() {
    languageLinks();
    openStatus();
    serviceRows();
    bottomBar();
    inPageFocus();
    keyboardAware();
}
