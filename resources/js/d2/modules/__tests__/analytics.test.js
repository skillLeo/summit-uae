import { describe, expect, it } from 'vitest';
import { clickEvent, consentCookie, cookieDomains, gaCookieNames, linkLocation, settle, storedConsent } from '../analytics.js';

// Lives outside modules/*.js on purpose: app.js eagerly imports every modules/*.js file.
// GA4 (docs/briefs/design2-only-black-header-ga4.md C4, C5): consent cookie, events, link_location, calculator settle.

/** A stand-in for an element: closest() answers from the selectors of the containers it sits in. */
const el = (containers = [], attrs = {}) => ({
    closest(selector) {
        if (selector === '[data-link-location]') return attrs.location ? { getAttribute: () => attrs.location } : null;
        const wanted = selector.split(',').map((s) => s.trim());
        return containers.some((c) => wanted.includes(c)) ? {} : null;
    },
});

describe('consent cookie', () => {
    it('reads only a stored granted or denied choice', () => {
        expect(storedConsent('a=1; summit_consent=granted; b=2')).toBe('granted');
        expect(storedConsent('summit_consent=denied')).toBe('denied');
        expect(storedConsent('summit_consent=yes')).toBeNull();
        expect(storedConsent('xsummit_consent=granted')).toBeNull();
        expect(storedConsent('')).toBeNull();
        expect(storedConsent(undefined)).toBeNull();
    });

    it('is first party, six months, SameSite=Lax, Secure on https', () => {
        expect(consentCookie('granted')).toBe('summit_consent=granted; Max-Age=15811200; Path=/; SameSite=Lax');
        expect(consentCookie('denied', true)).toBe('summit_consent=denied; Max-Age=15811200; Path=/; SameSite=Lax; Secure');
    });
});

describe('click events', () => {
    it('knows WhatsApp, telephone and e-mail links and nothing else', () => {
        expect(clickEvent('https://wa.me/971524666191?text=Hello')).toBe('whatsapp_click');
        expect(clickEvent('https://api.whatsapp.com/send?phone=971524666191')).toBe('whatsapp_click');
        expect(clickEvent('whatsapp://send?phone=971524666191')).toBe('whatsapp_click');
        expect(clickEvent('tel:+971524666191')).toBe('phone_click');
        expect(clickEvent('mailto:info@summitlegaluae.com')).toBe('email_click');
        expect(clickEvent('https://example.com/wa.me')).toBeNull();
        expect(clickEvent('https://wa.me.example.com/')).toBeNull();
        expect(clickEvent('/en/contact/')).toBeNull();
        expect(clickEvent('#consultation-form')).toBeNull();
        expect(clickEvent(null)).toBeNull();
    });
});

describe('link_location', () => {
    it('names the part of the page the link sits in, most specific first', () => {
        expect(linkLocation(el(['[data-wa-fab]']), 'home')).toBe('floating');
        expect(linkLocation(el(['.tabbar']), 'home')).toBe('mobile_bar');
        expect(linkLocation(el(['.d2-footer']), 'home')).toBe('footer');
        expect(linkLocation(el(['[data-sheet]']), 'home')).toBe('menu');
        expect(linkLocation(el(['.hero']), 'home')).toBe('hero');
        expect(linkLocation(el(['.inner-head']), 'service')).toBe('hero');
        // The Contact page's details card sits inside its page head: the card wins.
        expect(linkLocation(el(['.contact-card', '.inner-head']), 'contact')).toBe('contact');
        expect(linkLocation(el(['.cta-card']), 'service')).toBe('cta');
        expect(linkLocation(el([]), 'contact')).toBe('contact');
        expect(linkLocation(el([]), 'service')).toBe('content');
    });

    it('takes an explicit data-link-location when it is a plain name', () => {
        expect(linkLocation(el(['.hero'], { location: 'navigator' }), 'home')).toBe('navigator');
        expect(linkLocation(el(['.hero'], { location: 'Bad value!' }), 'home')).toBe('hero');
    });
});

describe('Google Analytics cookies on Reject', () => {
    it('finds _ga and _ga_<stream> only', () => {
        expect(gaCookieNames('_ga=GA1.1.1; _ga_ABC123=GS1; _gat=1; summit_consent=denied; x_ga=1')).toEqual(['_ga', '_ga_ABC123']);
    });

    it('tries the host and each parent domain, never the top-level one (none for an IP or localhost)', () => {
        expect(cookieDomains('www.summitlegaluae.com')).toEqual(['', '.www.summitlegaluae.com', '.summitlegaluae.com']);
        expect(cookieDomains('127.0.0.1')).toEqual(['']);
        expect(cookieDomains('localhost')).toEqual(['']);
    });
});

describe('calculator_used settles', () => {
    const fakeTimers = () => {
        let now = 0;
        let id = 0;
        const pending = new Map();
        return {
            set: (fn, t) => {
                pending.set(++id, { fn, at: now + t });
                return id;
            },
            clear: (i) => pending.delete(i),
            advance(ms) {
                now += ms;
                for (const [i, p] of [...pending]) {
                    if (p.at <= now) {
                        pending.delete(i);
                        p.fn();
                    }
                }
            },
        };
    };

    it('reports once per result shown, never per keystroke', () => {
        const timers = fakeTimers();
        let count = 0;
        const update = settle(() => count++, 1500, timers);
        // Typing: a result after each keystroke, 200 ms apart.
        for (let i = 0; i < 10; i++) {
            update(true);
            timers.advance(200);
        }
        expect(count).toBe(0);
        timers.advance(1500);
        expect(count).toBe(1);
        // More edits while the result stays: no new event.
        update(true);
        timers.advance(5000);
        expect(count).toBe(1);
    });

    it('a result that flickers away and back is the same result; one gone for a while is a new one', () => {
        const timers = fakeTimers();
        let count = 0;
        const update = settle(() => count++, 1500, timers);
        update(true);
        timers.advance(1500);
        expect(count).toBe(1);
        update(false);
        timers.advance(300);
        update(true);
        timers.advance(5000);
        expect(count).toBe(1);
        update(false);
        timers.advance(1500);
        update(true);
        timers.advance(1500);
        expect(count).toBe(2);
    });

    it('a result shown for less than the settle time is not reported', () => {
        const timers = fakeTimers();
        let count = 0;
        const update = settle(() => count++, 1500, timers);
        update(true);
        timers.advance(1000);
        update(false);
        timers.advance(5000);
        expect(count).toBe(0);
    });
});
