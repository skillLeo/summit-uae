/**
 * Language menu (<x-d2.shell.langmenu>; brief §3.7, D12). Alpine: d2Langmenu, a disclosure: the trigger (the
 * current language's approved label and a chevron) opens the list of the three approved labels.
 *
 * The x-data root contains the toggle AND the panel, and x-on:click.outside sits on it (Design 1 review
 * A11Y-01 / LANG-01 / SEC-01: a click.outside on the panel closed it in the click that opened it).
 * - Click toggles; a modified click (new tab) follows the no-JS link (the footer's language list or
 *   navigation).
 * - Enter or Space toggle and, when opening, focus the current option (else the first); ArrowDown /
 *   ArrowUp open and focus the first / last option. In the list: arrows (wrapping), Home and End.
 * - Esc closes and returns focus to the toggle; tabbing out or clicking outside closes.
 * - One header menu is open at a time ('d2:menu-open' on window).
 * Language links themselves are handled by modules/lang-switch.js (hash + summit_lang cookie).
 */
const modified = (e) => e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey;

function popover() {
    return {
        open: false,

        init() {
            this.onOther = (e) => {
                if (e.detail?.id !== this.panelId) this.hide();
            };
            this.onPageShow = (e) => {
                if (e.persisted) this.hide();
            };
            // A new width can hide the trigger (the language menu moves into the menu sheet): never leave it open unseen.
            this.onResize = () => {
                if (this.open && !this.$refs.toggle?.getClientRects().length) this.hide();
            };
            window.addEventListener('d2:menu-open', this.onOther);
            window.addEventListener('pageshow', this.onPageShow);
            window.addEventListener('resize', this.onResize, { passive: true });
        },

        destroy() {
            window.removeEventListener('d2:menu-open', this.onOther);
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

        /** The rendered options. */
        options() {
            const panel = this.$refs.panel;
            if (!panel) return [];
            return Array.from(panel.querySelectorAll('a[href], button:not([disabled])')).filter((el) => el.getClientRects().length > 0);
        },

        show() {
            if (this.open) return;
            this.open = true;
            window.dispatchEvent(new CustomEvent('d2:menu-open', { detail: { id: this.panelId } }));
        },

        hide(returnFocus = false) {
            if (!this.open) return;
            this.open = false;
            if (returnFocus) this.$refs.toggle?.focus();
        },

        focusOption(index) {
            const options = this.options();
            if (!options.length) return;
            const target = index === 'current'
                ? options.find((el) => el.hasAttribute('aria-current')) || options[0]
                : options[((index % options.length) + options.length) % options.length];
            target.focus();
        },

        onToggleClick(e) {
            if (modified(e)) return;
            e.preventDefault();
            if (this.open) this.hide();
            else this.show();
        },

        onToggleKey(e) {
            const key = e.key;
            if (key === 'Enter' || key === ' ' || key === 'Spacebar') {
                e.preventDefault();
                if (this.open) {
                    this.hide();
                    return;
                }
                this.show();
                this.$nextTick(() => this.focusOption('current'));
            } else if (key === 'ArrowDown' || key === 'ArrowUp') {
                e.preventDefault();
                this.show();
                this.$nextTick(() => this.focusOption(key === 'ArrowDown' ? 0 : -1));
            }
        },

        onListKey(e) {
            const options = this.options();
            const index = options.indexOf(document.activeElement);
            if (index < 0) return;
            const next = { ArrowDown: index + 1, ArrowUp: index - 1, Home: 0, End: options.length - 1 }[e.key];
            if (next === undefined) return;
            e.preventDefault();
            this.focusOption(next);
        },

        onFocusOut(e) {
            if (!this.open) return;
            const to = e.relatedTarget;
            if (to && !this.$root.contains(to)) this.hide();
        },

        onOutside() {
            this.hide();
        },

        onEscape(e) {
            if (!this.open) return;
            const inside = this.$root.contains(document.activeElement);
            if (inside) e.preventDefault();
            this.hide(inside);
        },
    };
}

export default (Alpine) => {
    Alpine.data('d2Langmenu', popover);
};
