/**
 * Practice Areas mega menu (<x-d2.shell.megamenu>; brief D48, §6). Alpine: d2Megamenu.
 *
 * The x-data root is the <li> holding the toggle AND the panel, and x-on:click.outside sits on it, so the
 * click that opens the menu never counts as "outside" (Design 1 review A11Y-01 / LANG-01 / SEC-01).
 *
 * - Hover intent (mouse or pen on a hover-capable device): opens 120 ms after the pointer enters, closes
 *   200 ms after it leaves, unless a click or the keyboard opened it, or focus is inside.
 * - Click toggles; a click on a hover-opened menu pins it open. A modified click (new tab / window)
 *   follows the link, which is the no-JS behaviour (the Practice Areas page).
 * - Keyboard on the toggle: Enter, Space or ArrowDown open the menu and focus the first service
 *   (ArrowUp: the last); Enter or Space close an open menu. In the list: ArrowDown / ArrowUp (wrapping),
 *   Home and End move focus and update the preview. Esc closes and returns focus to the toggle; tabbing
 *   out of the menu closes it.
 * - The preview (columns 2 and 3) follows the hovered or focused service. All previews are server-rendered
 *   approved copy; only their hidden attribute changes.
 * - One header menu is open at a time: opening broadcasts 'd2:menu-open' on window.
 * - When the header turns compact ('d2:header-compact' from modules/header.js) the row docks in the bar or tucks
 *   away: the menu closes unless focus is inside (then it stays: docked, it rides up with the row; tucked, the row
 *   stays until focus leaves it).
 * - The panel fits 1024 x 768 and larger windows whole. In a shorter one its body scrolls: while more lies below
 *   the visible part, the body gets .is-clipped (a fade at its lower edge, megamenu.css), measured on opening, on
 *   every preview change, on resize and on the body's own scroll.
 * - The 6 % ink scrim under the open panel is CSS (header.css, :has()); a click on it is a click outside.
 */
const OPEN_DELAY = 120; // ms
const CLOSE_GRACE = 200; // ms

const hoverDevice = () => window.matchMedia('(hover: hover) and (pointer: fine)').matches;
const isPointer = (e) => e.pointerType === 'mouse' || e.pointerType === 'pen';
const modified = (e) => e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey;

/** Is part of a scroller's content below its visible area (1 px of rounding slack)? */
export function clippedBelow(scrollHeight, clientHeight, scrollTop) {
    return scrollHeight - clientHeight > 1 && scrollTop + clientHeight < scrollHeight - 1;
}

export default (Alpine) => {
    Alpine.data('d2Megamenu', () => ({
        open: false,
        via: null, // 'hover' | 'click' | 'key'
        active: null,
        openTimer: null,
        closeTimer: null,

        init() {
            this.active = this.$root.dataset.megaDefault || null;
            this.onOther = (e) => {
                if (e.detail?.id !== this.panelId) this.hide();
            };
            this.onPageShow = (e) => {
                if (e.persisted) this.hide();
            };
            this.onResize = () => {
                if (this.open && !this.$refs.toggle?.getClientRects().length) this.hide();
                else if (this.open) this.measure();
            };
            this.onCompact = (e) => {
                if (e.detail?.compact && this.open && !this.$root.contains(document.activeElement)) this.hide();
            };
            window.addEventListener('d2:menu-open', this.onOther);
            window.addEventListener('d2:header-compact', this.onCompact);
            window.addEventListener('pageshow', this.onPageShow);
            window.addEventListener('resize', this.onResize, { passive: true });
        },

        destroy() {
            this.clearTimers();
            window.removeEventListener('d2:menu-open', this.onOther);
            window.removeEventListener('d2:header-compact', this.onCompact);
            window.removeEventListener('pageshow', this.onPageShow);
            window.removeEventListener('resize', this.onResize);
        },

        get expanded() {
            return this.open ? 'true' : 'false';
        },

        get closed() {
            return !this.open;
        },

        get toggleRole() {
            return 'button';
        },

        get panelId() {
            return this.$refs.panel?.id || '';
        },

        items() {
            return Array.from(this.$root.querySelectorAll('[data-mega-item]'));
        },

        clearTimers() {
            window.clearTimeout(this.openTimer);
            window.clearTimeout(this.closeTimer);
            this.openTimer = null;
            this.closeTimer = null;
        },

        show(via) {
            this.clearTimers();
            if (this.open) return;
            this.preview(this.$root.dataset.megaDefault);
            this.open = true;
            this.via = via;
            window.dispatchEvent(new CustomEvent('d2:menu-open', { detail: { id: this.panelId } }));
            this.$nextTick(() => this.measure());
        },

        /** The fade at the body's lower edge while part of it is out of view (a window shorter than the panel). */
        measure() {
            const body = this.$refs.body;
            if (!body) return;
            body.classList.toggle('is-clipped', this.open && clippedBelow(body.scrollHeight, body.clientHeight, body.scrollTop));
        },

        onBodyScroll() {
            this.measure();
        },

        hide(returnFocus = false) {
            this.clearTimers();
            if (!this.open) return;
            this.open = false;
            this.via = null;
            if (returnFocus) this.$refs.toggle?.focus();
        },

        preview(code) {
            if (!code) return;
            this.active = code;
            this.$root.querySelectorAll('[data-mega-preview]').forEach((el) => {
                el.hidden = el.dataset.megaPreview !== code;
            });
            this.items().forEach((item) => item.classList.toggle('is-active', item.dataset.code === code));
            if (this.open) this.measure();
        },

        focusItem(index) {
            const items = this.items();
            if (!items.length) return;
            items[((index % items.length) + items.length) % items.length].focus();
        },

        // ------------------------------------------------------------------ pointer
        onEnter(e) {
            if (!isPointer(e) || !hoverDevice()) return;
            window.clearTimeout(this.closeTimer);
            this.closeTimer = null;
            if (this.open) return;
            window.clearTimeout(this.openTimer);
            this.openTimer = window.setTimeout(() => this.show('hover'), OPEN_DELAY);
        },

        onLeave(e) {
            if (!isPointer(e)) return;
            window.clearTimeout(this.openTimer);
            this.openTimer = null;
            if (!this.open || this.via !== 'hover' || this.$root.contains(document.activeElement)) return;
            window.clearTimeout(this.closeTimer);
            this.closeTimer = window.setTimeout(() => this.hide(), CLOSE_GRACE);
        },

        onListPointer(e) {
            const item = e.target.closest?.('[data-mega-item]');
            if (item) this.preview(item.dataset.code);
        },

        // ------------------------------------------------------------------ click
        onToggleClick(e) {
            if (modified(e)) return;
            e.preventDefault();
            if (this.open && this.via === 'hover') {
                this.clearTimers();
                this.via = 'click';
                return;
            }
            if (this.open) this.hide();
            else this.show('click');
        },

        onOutside() {
            this.hide();
        },

        // ------------------------------------------------------------------ keyboard
        onToggleKey(e) {
            const key = e.key;
            if (key === 'Enter' || key === ' ' || key === 'Spacebar') {
                e.preventDefault();
                if (this.open) {
                    this.hide();
                    return;
                }
                this.show('key');
                this.$nextTick(() => this.focusItem(0));
            } else if (key === 'ArrowDown' || key === 'ArrowUp') {
                e.preventDefault();
                this.show('key');
                this.$nextTick(() => this.focusItem(key === 'ArrowDown' ? 0 : -1));
            }
        },

        onListKey(e) {
            const items = this.items();
            const index = items.indexOf(document.activeElement);
            if (index < 0) return;
            const next = { ArrowDown: index + 1, ArrowUp: index - 1, Home: 0, End: items.length - 1 }[e.key];
            if (next === undefined) return;
            e.preventDefault();
            this.focusItem(next);
        },

        onListFocus(e) {
            const item = e.target.closest?.('[data-mega-item]');
            if (item) this.preview(item.dataset.code);
        },

        onFocusOut(e) {
            if (!this.open) return;
            const to = e.relatedTarget;
            if (to && !this.$root.contains(to)) this.hide();
        },

        onEscape(e) {
            if (!this.open) return;
            const inside = this.$root.contains(document.activeElement);
            if (inside) e.preventDefault();
            this.hide(inside);
        },
    }));
};
