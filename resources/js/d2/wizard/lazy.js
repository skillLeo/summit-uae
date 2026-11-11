/**
 * Consultation wizard: the eager start-up behind Alpine d2Wizard (components/wizard.js). The controller
 * (wizard/controller.js with its modules, about 11 KB gzip) is a lazy chunk, imported when the wizard
 *
 *   - comes within about one viewport of the screen (IntersectionObserver, rootMargin 100%),
 *   - receives focus or a pointer press,
 *   - is the target of a d2:prefill event (lib/prefill.js: a hero, Navigator, calculator or team button),
 *   - is the target of the address: #consultation-form or one of its own ids (#wz-name …), on load or later,
 *   - or at once (data-wizard-mount="eager"): styleguide specimens and after a no-JS round trip.
 *
 * No layout change when it mounts (docs/d2/engineering.md §4b): the stepped layout is keyed on html.js
 * (components/wizard.css), the server renders the step the wizard opens on, its chips, its buttons and the
 * context lines, and the restore prompt is decided here, at start-up, from a cheap check of the stored draft
 * (draft-meta.js; the controller confirms it with the full check).
 *
 * Events that arrive before the controller is ready are queued and replayed in order: d2:prefill details, a
 * click on a wizard button (restore, delete, step chip, Back, Change, an error-summary link) and the submit
 * (Next, or Enter in a field: never posted natively while the steps show). Typing needs no queue (the values
 * stay in the fields); it dismisses a shown restore prompt, as it does later. If the chunk cannot load or
 * the wizard cannot start, the plain form comes back (plain.js) and posts without JavaScript.
 *
 *   lazyWizard(root, { load }) → { destroy() }        root: [data-wizard] (x-data="d2Wizard")
 *
 * root.dataset.wizardLoad reports waiting | loading | ready | plain (for QA scripts; no CSS keys on it).
 */
import * as storage from '../lib/storage.js';
import { isFresh, ttlFromDays } from './draft-meta.js';
import { plainFallback } from './plain.js';

/** About one viewport above and below the screen. */
const NEAR = '100% 0px';

/** Wizard buttons the controller handles by delegation: a click before it is ready waits for it. */
const DEFERRED = [
    '[data-wizard-restore-accept]',
    '[data-wizard-restore-discard]',
    '[data-wizard-back]',
    '[data-goto-step]',
    '[data-wizard-steps] button[data-step]',
    '[data-wizard-error-list] a',
    '[data-wizard-context-remove]',
    '[data-wizard-message-update]',
    '[data-wizard-new]',
].join(', ');

/** Does an address fragment point at the wizard: its section (#consultation-form) or one of its own ids? */
export function targetsWizard(hash, prefix) {
    let id = String(hash ?? '').replace(/^#/, '');
    try {
        id = decodeURIComponent(id);
    } catch {
        /* a malformed escape: compare the raw fragment */
    }
    if (id === '') return false;
    if (id === 'consultation-form') return true;
    return typeof prefix === 'string' && prefix !== '' && id.startsWith(`${prefix}-`);
}

/** The wizard's id prefix from its root id ("wz-wizard" → "wz"). */
export const prefixOf = (id) => String(id ?? '').replace(/-wizard$/, '');

/** The control a queued click meant, after the controller re-rendered it (step chips, error links). */
function current(el, root) {
    if (el.isConnected) return el;
    if (el.matches('button[data-step]')) {
        return [...root.querySelectorAll('[data-wizard-steps] button[data-step]')].find((b) => b.dataset.step === el.dataset.step) ?? null;
    }
    if (el.matches('a[href]')) {
        const href = el.getAttribute('href');
        return [...root.querySelectorAll('[data-wizard-error-list] a')].find((a) => a.getAttribute('href') === href) ?? null;
    }
    return null;
}

export function lazyWizard(root, { load = () => import('./controller.js') } = {}) {
    const form = root.querySelector('[data-wizard-form]');
    const prefix = prefixOf(root.id);
    const specimen = root.hasAttribute('data-wizard-specimen');
    const restoreEl = root.querySelector('[data-wizard-restore]');
    const queue = [];
    const offs = [];
    let state = 'waiting';
    let wizard = null;
    let observer = null;
    let prompt = 'none';
    let typed = false;

    const on = (target, type, handler, options) => {
        target.addEventListener(type, handler, options);
        offs.push(() => target.removeEventListener(type, handler, options));
    };
    const pending = () => state === 'waiting' || state === 'loading';
    const setState = (next) => {
        state = next;
        root.dataset.wizardLoad = next;
    };
    setState('waiting');

    // The restore prompt, decided before anything else (same moment as before the controller was lazy).
    if (restoreEl && root.hasAttribute('data-wizard-offer-draft') && root.dataset.draftKey) {
        if (isFresh(storage.read(root.dataset.draftKey), Date.now(), ttlFromDays(root.dataset.draftDays))) {
            restoreEl.hidden = false;
            prompt = 'shown';
        }
    }

    // The start-up listeners are only needed until the controller is ready (or the plain form is back).
    const release = () => {
        observer?.disconnect();
        observer = null;
        offs.splice(0).forEach((off) => off());
    };

    const fallback = (error) => {
        if (error) console.error(error);
        if (state === 'destroyed') return;
        queue.length = 0;
        wizard = null;
        setState('plain');
        release();
        plainFallback(root);
    };

    const replay = () => {
        for (const item of queue.splice(0)) {
            try {
                if (item.type === 'prefill') wizard.onPrefill(item.detail);
                else if (item.type === 'click') current(item.el, root)?.click();
                else if (item.type === 'submit') form?.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
            } catch (error) {
                // The wizard runs; only this one replayed action is lost.
                console.error(error);
            }
        }
    };

    const start = () => {
        if (state !== 'waiting') return;
        setState('loading');
        observer?.disconnect();
        observer = null;
        load()
            .then(({ mountWizard }) => {
                if (state === 'destroyed') return;
                wizard = mountWizard(root, { prompt, typed });
                if (!wizard) {
                    fallback();
                    return;
                }
                setState('ready');
                release();
                replay();
            })
            .catch(fallback);
    };

    // ------------------------------------------------------------------ before the controller is ready
    if (form) {
        on(form, 'submit', (event) => {
            if (!pending()) return;
            event.preventDefault();
            queue.push({ type: 'submit' });
            start();
        });
    }
    on(root, 'click', (event) => {
        if (!pending()) return;
        const el = event.target instanceof Element ? event.target.closest(DEFERRED) : null;
        if (!el || !root.contains(el)) return;
        event.preventDefault();
        queue.push({ type: 'click', el });
        start();
    });
    on(root, 'input', (event) => {
        if (!pending()) return;
        typed = true;
        if (prompt === 'shown' && restoreEl && !restoreEl.contains(event.target)) {
            restoreEl.hidden = true;
            prompt = 'dismissed';
        }
        start();
    });

    // ------------------------------------------------------------------ triggers
    const eager = root.dataset.wizardMount === 'eager';
    if (eager || (!specimen && targetsWizard(window.location.hash, prefix)) || typeof IntersectionObserver !== 'function') {
        start();
    } else {
        observer = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) start();
        }, { rootMargin: NEAR });
        observer.observe(root);
        on(root, 'focusin', start);
        on(root, 'pointerdown', start);
    }
    if (!specimen) {
        on(window, 'd2:prefill', (event) => {
            if (!pending()) return;
            queue.push({ type: 'prefill', detail: event.detail });
            start();
        });
        on(window, 'hashchange', () => {
            if (targetsWizard(window.location.hash, prefix)) start();
        });
    }

    return {
        destroy() {
            state = 'destroyed';
            release();
            wizard?.destroy();
            wizard = null;
        },
    };
}
