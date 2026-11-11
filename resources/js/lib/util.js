// Shared helpers (no dependencies).

export const mq = (query) => window.matchMedia(query);
export const reducedMotion = () => mq('(prefers-reduced-motion: reduce)').matches;
export const isMobile = () => mq('(max-width: 767.98px)').matches;
export const isRtl = () => document.documentElement.dir === 'rtl';

/** Accent-, case- and script-insensitive text for local filtering (EN/AR/RU). */
export function normalize(text) {
    return String(text || '')
        .toLowerCase()
        .normalize('NFKD')
        .replace(/[̀-ًͯ-ٰٟـ]/g, '') // Latin accents, Arabic tashkeel and tatweel
        .replace(/[أإآٱ]/g, 'ا')
        .replace(/ى/g, 'ي')
        .replace(/ة/g, 'ه')
        .replace(/ё/g, 'е')
        .replace(/\s+/g, ' ')
        .trim();
}

export function setCookie(name, value, days = 365) {
    const secure = location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `${name}=${encodeURIComponent(value)}; Max-Age=${days * 86400}; Path=/; SameSite=Lax${secure}`;
}

export function announce(message) {
    const region = document.querySelector('[data-announcer]');
    if (!region) return;
    region.textContent = '';
    window.setTimeout(() => {
        region.textContent = message;
    }, 60);
}

export function uuid() {
    if (window.crypto?.randomUUID) return window.crypto.randomUUID();
    const b = window.crypto.getRandomValues(new Uint8Array(16));
    b[6] = (b[6] & 0x0f) | 0x40;
    b[8] = (b[8] & 0x3f) | 0x80;
    const h = [...b].map((x) => x.toString(16).padStart(2, '0'));
    return `${h.slice(0, 4).join('')}-${h.slice(4, 6).join('')}-${h.slice(6, 8).join('')}-${h.slice(8, 10).join('')}-${h.slice(10).join('')}`;
}

/** Focus an element without letting the browser jump if it is already visible. */
export function focusEl(el, scroll = true) {
    if (!el) return;
    if (!el.hasAttribute('tabindex') && !/^(A|BUTTON|INPUT|SELECT|TEXTAREA)$/.test(el.tagName)) {
        el.setAttribute('tabindex', '-1');
    }
    el.focus({ preventScroll: !scroll });
}
