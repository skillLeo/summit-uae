/**
 * Design 2 modal dialog (upgrade spec §12 A4; the Phase C command palette is built on it).
 * Markup: <x-d2.ui.modal id="…" title="…"> → <dialog class="modal" id="modal-{id}" data-modal="{id}">.
 *
 * API   import { openModal, closeModal, activeModal } from '../components/modal.js';
 *   openModal(id, opener?)  showModal() + enter transition; focus goes to [autofocus] / the title / the first
 *                           control. opener (default: the focused element) gets focus back on close.
 *                           Returns the <dialog>, or null when the page has none with that id.
 *   closeModal(id?)         exit transition, then dialog.close(); without id: the top modal. Returns true
 *                           when a modal started closing.
 *   activeModal()           the open (top) <dialog>, or null
 * Markup hooks (delegated): [data-modal-open="id"] opens (aria-haspopup="dialog", aria-controls and
 * aria-expanded are kept in sync); [data-modal-close] inside the dialog closes it.
 * Events (bubbling, on the <dialog>): d2-modal-open, d2-modal-close with detail { id }.
 * Alpine: x-data="d2Modal" data-modal-target="id" provides open(), close(), toggle() and isOpen.
 *
 * The browser makes the page inert and draws the dialog in the top layer. This module adds Tab
 * wrap-around (lib/focus.js), page scroll lock without layout shift, Esc with the exit transition (the
 * cancel event; if the browser does not let it be cancelled the dialog closes at once and 'close' still
 * cleans up), backdrop click (press and release both on the backdrop), a history entry so the Back
 * gesture closes it, and focus back to the opener. Opening from inside a sheet works: the sheet's inert
 * background is lifted from the dialog while it is open.
 */
import { focusFirst, restoreFocus, trapFocus } from '../lib/focus.js';
import { reducedMotion } from '../lib/prefs.js';
import { lockScroll, pushHistory, unlockScroll } from '../lib/sheet.js';

const EXIT_FALLBACK = 500; // ms
const stack = [];
const bound = new WeakSet();
let initialised = false;

const esc = (value) => (window.CSS && CSS.escape ? CSS.escape(value) : String(value).replace(/["\\]/g, '\\$&'));
const findModal = (id) => (id ? document.querySelector(`dialog[data-modal="${esc(id)}"]`) : null);
const stateOf = (dialog) => stack.find((s) => s.dialog === dialog) || null;

export const activeModal = () => (stack.length ? stack[stack.length - 1].dialog : null);

function syncOpeners(id, expanded) {
    for (const opener of document.querySelectorAll(`[data-modal-open="${esc(id)}"]`)) {
        opener.setAttribute('aria-haspopup', 'dialog');
        opener.setAttribute('aria-controls', `modal-${id}`);
        opener.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }
}

function bindDialog(dialog) {
    if (bound.has(dialog)) return;
    bound.add(dialog);
    dialog.addEventListener('cancel', (event) => {
        // Esc / close request: run the exit transition ourselves when the browser lets us.
        const state = stateOf(dialog);
        if (!state || !event.cancelable) return;
        event.preventDefault();
        closeModal(state.id);
    });
    dialog.addEventListener('close', () => {
        const state = stateOf(dialog);
        if (state) finish(state, {});
    });
    dialog.addEventListener('pointerdown', (event) => {
        const state = stateOf(dialog);
        if (state) state.pressedBackdrop = event.target === dialog;
    });
    dialog.addEventListener('click', (event) => {
        const state = stateOf(dialog);
        // The panel fills the dialog box, so a click on the <dialog> itself is a click on the backdrop.
        if (state && event.target === dialog && state.pressedBackdrop) closeModal(state.id);
    });
}

export function openModal(id, opener = null) {
    initModals();
    const dialog = findModal(id);
    if (!dialog || typeof dialog.showModal !== 'function') return null;
    const existing = stateOf(dialog);
    if (existing && !existing.closing) return dialog;
    if (existing) finish(existing, { restore: false });
    bindDialog(dialog);

    const state = { id, dialog, opener: opener || document.activeElement, closing: false, finished: false };
    // A modal opened from inside a sheet sits in the sheet's inert background: lift that while it is open
    // (the open modal makes the rest of the page inert itself).
    state.inertHost = dialog.closest('[inert]');
    if (state.inertHost) state.inertHost.inert = false;

    lockScroll();
    state.release = trapFocus(dialog);
    try {
        dialog.showModal();
    } catch {
        state.release();
        unlockScroll();
        if (state.inertHost) state.inertHost.inert = true;
        return null;
    }
    stack.push(state);
    void dialog.offsetHeight; // start the enter transition from the closed state
    dialog.classList.add('is-open');

    const title = dialog.querySelector('.modal__title:not(.sr-only)');
    if (dialog.querySelector('[autofocus], [data-autofocus]') || !title) focusFirst(dialog.querySelector('.modal__panel') || dialog);
    else title.focus({ preventScroll: true });

    state.popHistory = pushHistory(() => closeModal(id, { fromHistory: true }));
    syncOpeners(id, true);
    dialog.dispatchEvent(new CustomEvent('d2-modal-open', { bubbles: true, detail: { id } }));
    return dialog;
}

export function closeModal(id = null, { fromHistory = false, restore = true } = {}) {
    const state = id ? stack.find((s) => s.id === id) : stack[stack.length - 1];
    if (!state || state.closing) return false;
    state.closing = true;
    state.fromHistory = fromHistory;
    state.dialog.classList.remove('is-open');
    const done = () => finish(state, { restore });
    if (reducedMotion()) {
        done();
        return true;
    }
    const onEnd = (event) => {
        if (event.target === state.dialog && event.propertyName === 'transform') done();
    };
    state.dialog.addEventListener('transitionend', onEnd);
    state.cancelExit = () => {
        state.dialog.removeEventListener('transitionend', onEnd);
        window.clearTimeout(state.exitTimer);
    };
    state.exitTimer = window.setTimeout(done, EXIT_FALLBACK);
    return true;
}

function finish(state, { restore = true }) {
    if (state.finished) return;
    state.finished = true;
    if (state.cancelExit) state.cancelExit();
    const index = stack.indexOf(state);
    if (index !== -1) stack.splice(index, 1);
    const { dialog, id } = state;
    dialog.classList.remove('is-open');
    state.release();
    if (dialog.open) dialog.close();
    unlockScroll();
    if (state.inertHost && state.inertHost.isConnected) state.inertHost.inert = true;
    if (state.popHistory) state.popHistory({ navigate: !state.fromHistory });
    syncOpeners(id, false);
    if (restore) restoreFocus(state.opener);
    dialog.dispatchEvent(new CustomEvent('d2-modal-close', { bubbles: true, detail: { id } }));
}

function onClick(event) {
    if (event.defaultPrevented || !(event.target instanceof Element)) return;
    const opener = event.target.closest('[data-modal-open]');
    if (opener) {
        const id = opener.getAttribute('data-modal-open');
        if (!findModal(id)) return;
        event.preventDefault();
        openModal(id, opener);
        return;
    }
    const closer = event.target.closest('[data-modal-close]');
    const dialog = closer ? closer.closest('dialog[data-modal]') : null;
    if (dialog && stateOf(dialog)) {
        event.preventDefault();
        closeModal(dialog.dataset.modal);
    }
}

export function initModals() {
    if (initialised) return;
    initialised = true;
    document.addEventListener('click', onClick);
    window.addEventListener('pageshow', (event) => {
        // Back/forward cache restore with a modal still open: close it silently.
        if (!event.persisted) return;
        for (const state of [...stack]) {
            state.fromHistory = true;
            finish(state, { restore: false });
        }
    });
    const ids = new Set(Array.from(document.querySelectorAll('[data-modal-open]'), (el) => el.getAttribute('data-modal-open')));
    for (const id of ids) if (findModal(id)) syncOpeners(id, false);
}

export default (Alpine) => {
    initModals();
    Alpine.data('d2Modal', () => ({
        isOpen: false,
        init() {
            this.onModalEvent = (event) => {
                if (event.detail && event.detail.id === this.$root.dataset.modalTarget) {
                    this.isOpen = event.type === 'd2-modal-open';
                }
            };
            window.addEventListener('d2-modal-open', this.onModalEvent);
            window.addEventListener('d2-modal-close', this.onModalEvent);
        },
        destroy() {
            window.removeEventListener('d2-modal-open', this.onModalEvent);
            window.removeEventListener('d2-modal-close', this.onModalEvent);
        },
        open() {
            openModal(this.$root.dataset.modalTarget, this.$el);
        },
        close() {
            closeModal(this.$root.dataset.modalTarget);
        },
        toggle() {
            if (this.isOpen) this.close();
            else this.open();
        },
    }));
};
