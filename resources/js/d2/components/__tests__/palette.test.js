import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// components/palette.js: how the palette answers the d2:palette-open protocol (docs/d2/engineering.md §4a),
// with the modal and the lazy engine mocked and a minimal stand-in for the DOM (Vitest runs in Node). The
// real dialog, engine and results are covered in the browser (palette QA script, storage/app/d2/final/).
const modal = vi.hoisted(() => ({ openModal: vi.fn(), closeModal: vi.fn() }));
const engine = vi.hoisted(() => ({ mount: vi.fn() }));
vi.mock('../modal.js', () => modal);
vi.mock('../../palette/engine.js', () => engine);

class FakeInput extends EventTarget {
    value = '';
    maxLength = 120;
    selection = null;
    focusCalls = 0;
    inputEvents = [];

    constructor() {
        super();
        this.addEventListener('input', () => this.inputEvents.push(this.value));
    }

    focus() {
        this.focusCalls += 1;
    }

    setSelectionRange(start, end) {
        this.selection = [start, end];
    }
}

let page;

/** A page with the palette dialog (or without it), then the component's default export run on it. */
async function boot({ withDialog = true } = {}) {
    const classes = new Set();
    const input = new FakeInput();
    const dialog = {
        showModal() {},
        classList: { contains: (name) => classes.has(name) },
        querySelector: (selector) => (selector === '[data-palette-input]' ? input : null),
    };
    const attributes = {};
    const events = [];
    globalThis.window = new EventTarget();
    window.addEventListener('d2:palette-ready', () => events.push('ready'));
    globalThis.document = {
        activeElement: null,
        body: {},
        documentElement: { setAttribute: (name, value) => (attributes[name] = value) },
        querySelector: (selector) => (withDialog && selector === 'dialog[data-modal="palette"]' ? dialog : null),
        querySelectorAll: () => [],
    };

    modal.openModal.mockImplementation((id) => {
        page.valueWhenOpened = input.value;
        classes.add('is-open');
        return dialog;
    });
    modal.closeModal.mockImplementation(() => {
        classes.delete('is-open');
        input.value = ''; // the engine clears the field on d2-modal-close
        return true;
    });
    engine.mount.mockImplementation(() => ({ render() {}, load() {} }));

    page = { dialog, input, classes, attributes, events, valueWhenOpened: null };
    vi.resetModules(); // a fresh module: its cached engine promise and window listener
    const { default: palette } = await import('../palette.js');
    palette();
    return page;
}

/** Dispatch the protocol event as a caller would; true when the palette claimed it (preventDefault). */
function request(detail) {
    const event = new CustomEvent('d2:palette-open', { cancelable: true, detail });
    window.dispatchEvent(event);
    return event.defaultPrevented;
}

beforeEach(() => {
    modal.openModal.mockReset();
    modal.closeModal.mockReset();
    engine.mount.mockReset();
});

afterEach(() => {
    delete globalThis.window;
    delete globalThis.document;
});

describe('palette: the d2:palette-open protocol', () => {
    it('announces itself once listening', async () => {
        const { attributes, events } = await boot();
        expect(attributes).toHaveProperty('data-palette', '');
        expect(events).toEqual(['ready']);
    });

    it('claims the event, opens the dialog and mounts the engine once', async () => {
        const { dialog, input } = await boot();
        expect(request({ source: 'click' })).toBe(true);
        expect(modal.openModal).toHaveBeenCalledWith('palette', null);
        await vi.dynamicImportSettled();
        expect(engine.mount).toHaveBeenCalledWith(dialog);
        expect(input.value).toBe('');
        expect(input.inputEvents).toEqual([]);
    });

    it('detail.query: opens with that text in the field, then hands it to the engine as typing does', async () => {
        const { input } = await boot();
        expect(request({ source: 'click', query: '  tenancy \n deposit ' })).toBe(true);
        expect(page.valueWhenOpened).toBe(''); // set after openModal (a reopen clears the field first)
        expect(input.value).toBe('tenancy deposit');
        expect(input.selection).toEqual([15, 15]);
        expect(input.focusCalls).toBeGreaterThan(0);
        expect(input.inputEvents).toEqual(['tenancy deposit']);
        await vi.dynamicImportSettled();
        // On the first open the engine renders the field's text itself (mount → render).
        expect(engine.mount).toHaveBeenCalledTimes(1);
    });

    it('an open palette takes a new query in place, even from Ctrl/Cmd+K', async () => {
        const { input } = await boot();
        request({ source: 'click', query: 'cheque' });
        expect(request({ source: 'shortcut-k', query: 'شيك مرتجع' })).toBe(true);
        expect(modal.closeModal).not.toHaveBeenCalled();
        expect(modal.openModal).toHaveBeenCalledTimes(1);
        expect(input.value).toBe('شيك مرتجع');
        expect(input.inputEvents).toEqual(['cheque', 'شيك مرتجع']);
    });

    it('an open palette without a query: Ctrl/Cmd+K closes it, "/" and clicks focus the field', async () => {
        const { input } = await boot();
        request({ source: 'click' });
        const focused = input.focusCalls;
        request({ source: 'shortcut-slash' });
        request({ source: 'click' });
        expect(input.focusCalls).toBe(focused + 2);
        expect(modal.closeModal).not.toHaveBeenCalled();
        request({ source: 'shortcut-k' });
        expect(modal.closeModal).toHaveBeenCalledWith('palette');
    });

    it('reopens with a new query after closing, without importing the engine again', async () => {
        const { input } = await boot();
        request({ source: 'click', query: 'gratuity' });
        await vi.dynamicImportSettled();
        request({ source: 'shortcut-k' }); // close
        expect(input.value).toBe('');
        request({ source: 'click', query: 'rent increase' });
        expect(modal.openModal).toHaveBeenCalledTimes(2);
        expect(input.value).toBe('rent increase');
        expect(input.inputEvents).toEqual(['gratuity', 'rent increase']);
        await vi.dynamicImportSettled();
        expect(engine.mount).toHaveBeenCalledTimes(1);
    });

    it('ignores a query that is not usable text', async () => {
        const { input } = await boot();
        for (const query of [['cheque'], { text: 'cheque' }, 42, '   ']) {
            page.classes.clear();
            expect(request({ source: 'click', query })).toBe(true);
            expect(input.value).toBe('');
        }
        expect(input.inputEvents).toEqual([]);
        expect(modal.openModal).toHaveBeenCalledTimes(4);
    });

    it("cuts a long query to the field's maxlength", async () => {
        const { input } = await boot();
        input.maxLength = 12;
        request({ source: 'click', query: 'end of service gratuity' });
        expect(input.value).toBe('end of servi');
    });

    it('opens nothing and claims nothing when the page has no palette dialog', async () => {
        const { attributes, events } = await boot({ withDialog: false });
        expect(request({ source: 'click', query: 'cheque' })).toBe(false);
        expect(modal.openModal).not.toHaveBeenCalled();
        expect(attributes).not.toHaveProperty('data-palette');
        expect(events).toEqual([]);
    });

    it('does not mount the engine when the dialog cannot open', async () => {
        const { input } = await boot();
        modal.openModal.mockImplementation(() => null);
        request({ source: 'click', query: 'cheque' });
        await vi.dynamicImportSettled();
        expect(engine.mount).not.toHaveBeenCalled();
        expect(input.value).toBe('');
    });
});
