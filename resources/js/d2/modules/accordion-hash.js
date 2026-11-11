/**
 * Deep links open accordion items (<x-d2.ui.accordion-item id="faq-01">, any <details>).
 * On load, on hashchange, and when a link to the current #hash is followed again, the <details> that
 * has or contains the target id is opened (with every closed <details> around it) and the target is
 * scrolled into view with scrollBehavior() (instant under reduced motion). The scroll waits one frame
 * so a tab panel that holds the item (d2Tabs) is already shown. Ids are matched as plain strings only.
 */
import { scrollBehavior } from '../lib/prefs.js';

function targetFor(hash) {
    if (typeof hash !== 'string' || hash.length < 2 || hash[0] !== '#') return null;
    let id;
    try {
        id = decodeURIComponent(hash.slice(1));
    } catch {
        return null;
    }
    return id ? document.getElementById(id) : null;
}

/**
 * Open the details around `target`; true when one was found. Items of the same exclusive group
 * (<details name>) that the browser closes in response collapse instantly (.is-instant): a spring
 * collapse above the target would move it after the scroll has been aimed.
 */
function openAround(target) {
    let details = target.closest('details');
    if (!details) return false;
    const instant = [];
    while (details) {
        if (!details.open) {
            const group = details.getAttribute('name');
            if (group) {
                for (const sibling of document.querySelectorAll('details[name]')) {
                    if (sibling.open && sibling !== details && sibling.getAttribute('name') === group) {
                        sibling.classList.add('is-instant');
                        instant.push(sibling);
                    }
                }
            }
            details.open = true;
        }
        details = details.parentElement?.closest('details') ?? null;
    }
    if (instant.length) {
        window.requestAnimationFrame(() => window.requestAnimationFrame(() => instant.forEach((d) => d.classList.remove('is-instant'))));
    }
    return true;
}

function reveal(hash, { onLoad = false } = {}) {
    const target = targetFor(hash);
    if (!target || !openAround(target)) return;
    window.requestAnimationFrame(() => {
        target.scrollIntoView({ behavior: scrollBehavior(), block: 'start' });
    });
    if (!onLoad || !document.fonts) return;
    // Web fonts that swap in after the scroll move the target: aim once more, unless the visitor moved.
    let moved = false;
    const stop = () => {
        moved = true;
    };
    ['wheel', 'touchstart', 'keydown', 'pointerdown'].forEach((type) => window.addEventListener(type, stop, { once: true, passive: true }));
    document.fonts.ready.then(() => {
        if (!moved) window.requestAnimationFrame(() => target.scrollIntoView({ behavior: 'auto', block: 'start' }));
    });
}

export default function accordionHash() {
    reveal(window.location.hash, { onLoad: true });

    window.addEventListener('hashchange', () => reveal(window.location.hash));

    // Following a link to the hash that is already current fires no hashchange: open the item anyway.
    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const link = event.target.closest?.('a[href*="#"]');
        if (!link || !link.hash || link.hash !== window.location.hash) return;
        if (link.pathname !== window.location.pathname || link.search !== window.location.search) return;
        window.setTimeout(() => reveal(link.hash), 0);
    });
}
