/**
 * Command palette bootstrap (upgrade spec §12 C12; docs/d2/engineering.md §4a "Search → command palette
 * protocol"). Always loaded, so it stays tiny: it claims the header's cancelable window event
 * d2:palette-open (search trigger click, "/" outside fields, Ctrl/Cmd+K anywhere) with preventDefault(),
 * opens the Phase A modal #modal-palette (<x-d2.shell.palette>) and, on the first open only, imports the
 * palette engine (palette/engine.js: MiniSearch + the index JSON). Ctrl/Cmd+K while the palette is open
 * closes it; "/" or a click focuses its field. Once listening it sets <html data-palette> and dispatches
 * d2:palette-ready, so the header triggers announce role="button" aria-haspopup="dialog".
 * Without the dialog on the page nothing is claimed and the triggers keep their link (Legal Resources).
 *
 * detail.query (optional string): the palette opens, or when already open stays open, with that text in
 * its field and its results shown (palette/query.js cleans it: strings only, at most the field's 120
 * characters). The text goes in as typing would (an input event the engine renders), so the first open
 * shows the loading state until the index arrives, then the results.
 */
import { closeModal, openModal } from './modal.js';
import { QUERY_MAX, openAction, paletteQuery } from '../palette/query.js';

let engine = null;

function mountEngine(dialog) {
    engine ??= import('../palette/engine.js')
        .then((module) => module.mount(dialog))
        .catch((error) => {
            engine = null;
            console.error(error);
            return null;
        });
    return engine;
}

/** The element focus returns to: the focused trigger, or (Safari does not focus clicked links) the visible one. */
function openerFor(source) {
    const current = document.activeElement;
    if (current && current !== document.body) return current;
    if (source !== 'click') return null;
    return Array.from(document.querySelectorAll('[data-d2-search]')).find((el) => el.getClientRects().length > 0) || null;
}

/** A caller's text into the field, caret at the end; the input event lets a mounted engine render it. */
function setQuery(input, query) {
    if (!input) return;
    input.value = query;
    input.focus({ preventScroll: true });
    try {
        input.setSelectionRange(query.length, query.length);
    } catch {
        // an input type without a selection API keeps the browser's caret
    }
    input.dispatchEvent(new Event('input', { bubbles: true }));
}

export default () => {
    const dialog = document.querySelector('dialog[data-modal="palette"]');
    if (!dialog || typeof dialog.showModal !== 'function') return;
    const input = dialog.querySelector('[data-palette-input]');

    // The search box (palette.css) draws the hint inside the field while it is empty (.is-filled hides it), wrapped,
    // so on a phone it can be taller than the input: a press anywhere in it keeps the caret in the field.
    const box = dialog.querySelector('[data-palette-box]');
    const syncBox = () => box?.classList.toggle('is-filled', Boolean(input?.value));
    input?.addEventListener('input', syncBox);
    box?.addEventListener('mousedown', (event) => {
        if (event.button !== 0 || !input || event.target === input) return;
        event.preventDefault();
        input.focus({ preventScroll: true });
    });

    window.addEventListener('d2:palette-open', (event) => {
        event.preventDefault();
        const source = event.detail?.source;
        const query = paletteQuery(event.detail?.query, input?.maxLength > 0 ? input.maxLength : QUERY_MAX);
        const action = openAction({ isOpen: dialog.classList.contains('is-open'), source, query });
        if (action === 'close') {
            closeModal('palette');
            return;
        }
        if (action === 'focus') {
            input?.focus({ preventScroll: true });
            return;
        }
        if (action === 'query') {
            setQuery(input, query);
            return;
        }
        if (!openModal('palette', openerFor(source))) return;
        // Only after openModal: reopening during the exit transition finishes the old close first, and the
        // engine clears the field on d2-modal-close (without an input event: the hint is synced here).
        if (query) setQuery(input, query);
        syncBox();
        mountEngine(dialog);
    });

    document.documentElement.setAttribute('data-palette', '');
    window.dispatchEvent(new CustomEvent('d2:palette-ready'));
};
