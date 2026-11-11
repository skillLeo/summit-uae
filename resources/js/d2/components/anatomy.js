/**
 * Anatomy of a Document (home section #anatomy) and the styleguide's callout pins.
 *
 * d2Anatomy   The server renders every document panel, its hotspot buttons, its parts list and every explanation;
 *             the first document and its first part are selected. This component only switches state (Design 2.1
 *             brief D28 and §6 "Anatomy": the hotspots and the parts list stay in sync):
 *             - document chips: one single-select group (toggle buttons, aria-pressed) in a toolbar with a roving
 *               tabindex; ←/→ (mirrored in RTL), ↑/↓, Home and End move focus, Enter / Space select. Selecting
 *               shows that document's panel (the others stay [hidden]);
 *             - two groups pick a part of the document, with the same pattern: the parts list beside the document
 *               ([data-anatomy-part], a vertical toolbar) and the hotspot buttons over it ([data-anatomy-hotspot],
 *               placed here from each field's box; their focus ring is drawn on the document). Choosing a part in
 *               either (click, Enter, Space; a pointer on a field of the document too) presses it in both, sets
 *               data-hot on the matching [data-field] (bronze outline + glow, components/doc.css), shows its
 *               explanation (.is-current) and announces it politely (lib/announce.js: a short message, never the
 *               whole card);
 *             - hovering or focusing a part lights its field ([data-peek]); hovering or focusing a hotspot or a
 *               field lights its row (.is-peek). Hover wins over focus while both exist;
 *             - "Request a Legal Consultation" hands the document's service to the wizard (lib/prefill.js); a
 *               modified click still follows the no-JS link.
 *             Without JavaScript the chips, the list and the hotspots are hidden and every explanation of the first
 *             document is listed.
 * d2DocPins   styleguide only: draws a numbered pin at every data-callouts anchor of the document inside.
 */
import { announce } from '../lib/announce.js';
import { dir } from '../lib/dom.js';
import { prefill } from '../lib/prefill.js';

/** The index a key moves focus to inside a group of `count` items, or -1 for any other key. */
export function stepIndex(key, index, count, direction = 1) {
    if (count < 1) return -1;
    const next = direction === 1 ? 'ArrowRight' : 'ArrowLeft';
    const prev = direction === 1 ? 'ArrowLeft' : 'ArrowRight';
    if (key === next || key === 'ArrowDown') return (index + 1) % count;
    if (key === prev || key === 'ArrowUp') return (index - 1 + count) % count;
    if (key === 'Home') return 0;
    if (key === 'End') return count - 1;
    return -1;
}

/** Parse a document root's data-callouts (contract C); never throws, drops malformed entries. */
export function parseCallouts(json) {
    let list;
    try {
        list = JSON.parse(json || '[]');
    } catch {
        return [];
    }
    if (!Array.isArray(list)) return [];
    return list.filter(
        (c) =>
            c &&
            typeof c.id === 'string' &&
            Number.isFinite(c.x) &&
            Number.isFinite(c.y) &&
            c.x >= 0 &&
            c.x <= 100 &&
            c.y >= 0 &&
            c.y <= 100 &&
            typeof c.label === 'string' &&
            (c.side === 'start' || c.side === 'end'),
    );
}

/**
 * The hotspot box of one field, in % of the document box (x from the inline-start edge, so the button is placed
 * with inset-inline-start in both directions). Both rects are viewport boxes ({left, top, width, height}); the
 * field is padded like its outline in components/doc.css (inset -3px -5px in document px, times `scale`).
 * Returns null for an empty document box or a field that is not drawn.
 */
export function hotspotBox(field, doc, { scale = 1, rtl = false, padX = 5, padY = 3 } = {}) {
    if (!field || !doc || !(doc.width > 0) || !(doc.height > 0) || !(field.width > 0) || !(field.height > 0)) return null;
    const px = padX * scale;
    const py = padY * scale;
    const left = field.left - px;
    const right = field.left + field.width + px;
    const top = field.top - py;
    const bottom = field.top + field.height + py;
    const start = rtl ? doc.left + doc.width - right : left - doc.left;
    const pct = (value, total) => Math.round((value / total) * 10000) / 100;
    return {
        x: pct(start, doc.width),
        y: pct(top - doc.top, doc.height),
        w: pct(right - left, doc.width),
        h: pct(bottom - top, doc.height),
    };
}

const modified = (event) => event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;
const PICK = '[data-anatomy-part], [data-anatomy-hotspot]';
const idOf = (el) => el?.dataset.anatomyPart ?? el?.dataset.anatomyHotspot ?? el?.dataset.field ?? null;

export default (Alpine) => {
    Alpine.data('d2Anatomy', () => ({
        init() {
            const root = this.$root;
            this.docChips = Array.from(root.querySelectorAll('[data-anatomy-doc]'));
            this.panels = Array.from(root.querySelectorAll('[data-anatomy-panel]'));
            if (!this.docChips.length || !this.panels.length) return;

            const pressed = this.docChips.find((chip) => chip.getAttribute('aria-pressed') === 'true') ?? this.docChips[0];
            this.current = pressed.dataset.anatomyDoc;
            this.rove(this.docChips, pressed);
            for (const panel of this.panels) {
                for (const group of [this.partsOf(panel), this.hotspotsOf(panel)]) {
                    this.rove(group, group.find((item) => item.getAttribute('aria-pressed') === 'true') ?? group[0]);
                }
            }
            this.hoverId = null;
            this.focusId = null;

            this.onClick = (event) => this.handleClick(event);
            this.onKey = (event) => this.handleKey(event);
            this.onOver = (event) => this.handleHover(event, true);
            this.onOut = (event) => this.handleHover(event, false);
            this.onFocusIn = (event) => this.handleFocus(event, true);
            this.onFocusOut = (event) => this.handleFocus(event, false);
            root.addEventListener('click', this.onClick);
            root.addEventListener('keydown', this.onKey);
            root.addEventListener('pointerover', this.onOver);
            root.addEventListener('pointerout', this.onOut);
            root.addEventListener('focusin', this.onFocusIn);
            root.addEventListener('focusout', this.onFocusOut);

            // The hotspot buttons follow their fields: placed now, after the web fonts, and whenever a plate
            // changes size (a document shown, a width step of the scale ladder, the 768 px switch).
            this.observer = 'ResizeObserver' in window ? new ResizeObserver((entries) => {
                for (const entry of entries) this.place(entry.target.closest('[data-anatomy-panel]'));
            }) : null;
            for (const plate of root.querySelectorAll('[data-anatomy-plate]')) this.observer?.observe(plate);
            this.placeShown();
            document.fonts?.ready.then(() => this.placeShown());

            root.classList.add('is-enhanced');
            // Transitions only after the first paint, so nothing animates on load.
            window.requestAnimationFrame(() => window.requestAnimationFrame(() => root.classList.add('is-ready')));
        },

        destroy() {
            const root = this.$root;
            root.removeEventListener('click', this.onClick);
            root.removeEventListener('keydown', this.onKey);
            root.removeEventListener('pointerover', this.onOver);
            root.removeEventListener('pointerout', this.onOut);
            root.removeEventListener('focusin', this.onFocusIn);
            root.removeEventListener('focusout', this.onFocusOut);
            this.observer?.disconnect();
        },

        partsOf(panel) {
            return panel ? Array.from(panel.querySelectorAll('[data-anatomy-part]')) : [];
        },

        hotspotsOf(panel) {
            return panel ? Array.from(panel.querySelectorAll('[data-anatomy-hotspot]')) : [];
        },

        rove(items, active) {
            for (const item of items) item.tabIndex = item === active ? 0 : -1;
        },

        shownPanel() {
            return this.panels.find((panel) => !panel.hidden) ?? null;
        },

        placeShown() {
            this.place(this.shownPanel());
        },

        /** Places the hotspot buttons of a shown panel over their fields (in % of the document box). */
        place(panel) {
            if (!panel || panel.hidden) return;
            const layer = panel.querySelector('[data-anatomy-hotspots]');
            const doc = panel.querySelector('.doc');
            if (!layer || !doc) return;
            const box = layer.getBoundingClientRect();
            if (!(box.width > 0)) return; // not drawn below 768 px
            const body = doc.querySelector('.doc__body');
            const scale = body?.offsetWidth ? doc.getBoundingClientRect().width / body.offsetWidth : 1;
            const rtl = dir() === -1;
            for (const button of this.hotspotsOf(panel)) {
                const field = doc.querySelector(`[data-field="${button.dataset.anatomyHotspot}"]`);
                const spot = field ? hotspotBox(field.getBoundingClientRect(), box, { scale, rtl }) : null;
                button.hidden = !spot;
                if (!spot) continue;
                button.style.setProperty('--hs-x', String(spot.x));
                button.style.setProperty('--hs-y', String(spot.y));
                button.style.setProperty('--hs-w', String(spot.w));
                button.style.setProperty('--hs-h', String(spot.h));
            }
            layer.classList.add('is-placed');
        },

        handleClick(event) {
            const target = event.target instanceof Element ? event.target : null;
            if (!target) return;

            const docChip = target.closest('[data-anatomy-doc]');
            if (docChip) {
                this.selectDoc(docChip.dataset.anatomyDoc, true);
                return;
            }
            const pick = target.closest(PICK);
            if (pick) {
                this.selectPart(pick.closest('[data-anatomy-panel]'), idOf(pick), true);
                return;
            }
            const cta = target.closest('[data-anatomy-cta]');
            if (cta) {
                if (modified(event) || cta.getAttribute('aria-disabled') === 'true') return;
                event.preventDefault();
                prefill(cta.dataset.service ? { service: cta.dataset.service } : {});
                return;
            }
            // A pointer on the document itself (below 768 px, where the hotspot buttons are not drawn).
            const field = target.closest('.doc [data-field]');
            if (field) this.selectPart(field.closest('[data-anatomy-panel]'), field.dataset.field, true);
        },

        handleKey(event) {
            const item = event.target instanceof Element ? event.target.closest(`[data-anatomy-doc], ${PICK}`) : null;
            if (!item) return;
            const panel = item.closest('[data-anatomy-panel]');
            const group = item.hasAttribute('data-anatomy-doc')
                ? this.docChips
                : item.hasAttribute('data-anatomy-part')
                  ? this.partsOf(panel)
                  : this.hotspotsOf(panel).filter((button) => !button.hidden);
            const next = stepIndex(event.key, group.indexOf(item), group.length, dir());
            if (next < 0) return;
            event.preventDefault();
            this.rove(group, group[next]);
            group[next].focus();
        },

        /** Pointer over a row, a hotspot or a field: light the pair (the row and the field). */
        handleHover(event, entering) {
            const target = event.target instanceof Element ? event.target : null;
            const item = target?.closest(`${PICK}, .doc [data-field]`);
            if (!item) return;
            if (!entering && event.relatedTarget instanceof Element && item.contains(event.relatedTarget)) return;
            const panel = item.closest('[data-anatomy-panel]');
            this.hoverId = entering ? idOf(item) : null;
            this.peek(panel);
        },

        handleFocus(event, entering) {
            const item = event.target instanceof Element ? event.target.closest(PICK) : null;
            if (!item) return;
            this.focusId = entering ? idOf(item) : null;
            this.peek(item.closest('[data-anatomy-panel]'));
        },

        peek(panel) {
            if (!panel) return;
            const id = this.hoverId ?? this.focusId;
            for (const field of panel.querySelectorAll('.doc [data-field]')) {
                field.toggleAttribute('data-peek', id !== null && field.dataset.field === id);
            }
            for (const row of this.partsOf(panel)) row.classList.toggle('is-peek', id !== null && row.dataset.anatomyPart === id);
        },

        selectDoc(type, user = false) {
            const panel = this.panels.find((p) => p.dataset.anatomyPanel === type);
            if (!panel || type === this.current) return;
            const previous = this.shownPanel();
            this.current = type;
            for (const chip of this.docChips) chip.setAttribute('aria-pressed', String(chip.dataset.anatomyDoc === type));
            this.rove(this.docChips, this.docChips.find((chip) => chip.dataset.anatomyDoc === type));
            for (const p of this.panels) p.hidden = p !== panel;
            this.hoverId = null;
            this.focusId = null;
            this.peek(previous);
            this.place(panel);
            const note = panel.querySelector('[data-anatomy-note].is-current');
            if (user && note) announce(note.dataset.announce);
        },

        selectPart(panel, id, user = false) {
            if (!panel || !id) return;
            const parts = this.partsOf(panel);
            const row = parts.find((p) => p.dataset.anatomyPart === id);
            if (!row) return;
            const hotspots = this.hotspotsOf(panel);
            for (const p of parts) p.setAttribute('aria-pressed', String(p === row));
            for (const h of hotspots) h.setAttribute('aria-pressed', String(h.dataset.anatomyHotspot === id));
            this.rove(parts, row);
            this.rove(hotspots, hotspots.find((h) => h.dataset.anatomyHotspot === id));
            for (const field of panel.querySelectorAll('.doc [data-field]')) {
                field.toggleAttribute('data-hot', field.dataset.field === id);
            }
            let current = null;
            for (const note of panel.querySelectorAll('[data-anatomy-note]')) {
                const on = note.dataset.anatomyNote === id;
                note.classList.toggle('is-current', on);
                if (on) current = note;
            }
            if (user && current) announce(current.dataset.announce);
        },
    }));

    Alpine.data('d2DocPins', () => ({
        init() {
            const doc = this.$root.querySelector('.doc');
            if (!doc) return;
            const layer = document.createElement('span');
            layer.className = 'sg-pins';
            layer.setAttribute('aria-hidden', 'true');
            parseCallouts(doc.dataset.callouts).forEach((callout, index) => {
                const pin = document.createElement('span');
                pin.className = `sg-pin sg-pin--${callout.side}`;
                pin.textContent = String(index + 1);
                pin.style.setProperty('--x', String(callout.x));
                pin.style.setProperty('--y', String(callout.y));
                layer.append(pin);
            });
            doc.append(layer);
        },
    }));
};
