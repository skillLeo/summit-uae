// Disclosure (Practice Areas panel, language menu). On phones the language button opens the
// language sheet instead of the dropdown.
import { isMobile } from '../lib/util';

export function disclosure() {
    return {
        open: false,
        toggle() {
            this.open = !this.open;
        },
        close() {
            if (!this.open) return;
            this.open = false;
            this.$refs.button?.focus({ preventScroll: true });
        },
        onFocusOut(e) {
            if (this.open && !this.$el.contains(e.relatedTarget)) this.open = false;
        },
        toggleOrSheet() {
            if (isMobile()) {
                window.dispatchEvent(new CustomEvent('summit:open-sheet', { detail: { id: 'language', from: this.$refs.button } }));
                return;
            }
            this.toggle();
        },
    };
}
