/**
 * d2Tabs (<x-d2.ui.tabs>): progressive tabs.
 *
 * Server HTML is a list of in-page anchor links plus every panel, all visible (works without JS).
 * init() turns it into the ARIA tabs pattern: role=tablist/tab/tabpanel, aria-selected, aria-controls,
 * aria-labelledby, a roving tabindex and hidden inactive panels. Keys: ←/→ (reversed in RTL), Home, End,
 * with automatic activation (focus = selection). The active indicator slides from the previous tab
 * with the ui spring (FLIP on transform; instant under reduced motion). The list scrolls horizontally:
 * fade edges and pointer-only scroll buttons appear where tabs are cut off, and the active tab is kept
 * clear of the fades. A #hash that points into a panel (or at it) activates that panel on load and on
 * hashchange. Emits `d2:tab` {id} on the root after each change.
 *
 * DOM is updated directly (no template expressions beyond method names, per the Alpine CSP build).
 */
import { reducedMotion, scrollBehavior } from '../lib/prefs.js';

const FADE = 48; // keep the active tab clear of the 48 px edge fade (tokens: --mask-fade-*)

function decodeHash(hash) {
    if (typeof hash !== 'string' || hash.length < 2) return '';
    try {
        return decodeURIComponent(hash.slice(1));
    } catch {
        return '';
    }
}

export default (Alpine) => {
    Alpine.data('d2Tabs', () => ({
        current: '',

        init() {
            const root = this.$root;
            this.list = root.querySelector('[data-tabs-list]');
            if (!this.list) return;
            this.tabEls = Array.from(this.list.querySelectorAll(':scope > [data-tab]'));
            this.panelEls = this.tabEls.map((tab) => document.getElementById(tab.dataset.tab));
            if (!this.tabEls.length || this.panelEls.some((panel) => !panel)) return;

            this.list.setAttribute('role', 'tablist');
            this.tabEls.forEach((tab, i) => {
                const panel = this.panelEls[i];
                tab.setAttribute('role', 'tab');
                tab.setAttribute('aria-controls', panel.id);
                panel.setAttribute('role', 'tabpanel');
                panel.setAttribute('aria-labelledby', tab.id);
                panel.tabIndex = 0;
            });
            this.scrollButtons = Array.from(root.querySelectorAll(':scope > .tabs__bar > [data-tabs-scroll]'));
            root.classList.add('is-enhanced');

            const fromHash = this.panelForHash(window.location.hash);
            this.select(fromHash || root.dataset.active || this.panelEls[0].id, { animate: false, user: false });

            this.onHash = () => {
                const id = this.panelForHash(window.location.hash);
                if (!id) return;
                if (id !== this.current) this.select(id, { user: false });
                this.revealHashTarget();
            };
            window.addEventListener('hashchange', this.onHash);

            this.updateOverflow();
            this.resizeObserver = new ResizeObserver(() => this.updateOverflow());
            this.resizeObserver.observe(this.list);
            document.fonts?.ready.then(() => {
                this.updateOverflow();
                this.keepVisible(this.tabFor(this.current), 'auto');
            });
            if (fromHash) this.revealHashTarget();
        },

        destroy() {
            if (this.onHash) window.removeEventListener('hashchange', this.onHash);
            this.resizeObserver?.disconnect();
        },

        noop() {},

        tabFor(id) {
            return this.tabEls?.find((tab) => tab.dataset.tab === id) ?? null;
        },

        /** The panel id a #hash points to (the panel itself or anything inside it), or ''. */
        panelForHash(hash) {
            const id = decodeHash(hash);
            if (!id || !this.panelEls) return '';
            const target = document.getElementById(id);
            if (!target) return '';
            const panel = this.panelEls.find((p) => p === target || p.contains(target));
            return panel ? panel.id : '';
        },

        /** Scroll a hash target into view now that its panel is shown (details are left to accordion-hash.js). */
        revealHashTarget() {
            const target = document.getElementById(decodeHash(window.location.hash));
            if (!target || target.closest('details')) return;
            const into = this.panelEls.includes(target) ? this.$root : target;
            window.requestAnimationFrame(() => into.scrollIntoView({ behavior: scrollBehavior(), block: 'start' }));
        },

        select(id, { focus = false, animate = true, user = true } = {}) {
            const next = this.tabFor(id);
            if (!next) return;
            const prev = this.tabFor(this.current);
            if (prev === next) {
                if (focus) next.focus({ preventScroll: true });
                return;
            }

            this.tabEls.forEach((tab, i) => {
                const on = tab === next;
                tab.setAttribute('aria-selected', on ? 'true' : 'false');
                tab.tabIndex = on ? 0 : -1;
                this.panelEls[i].hidden = !on;
            });

            if (animate && prev && !reducedMotion()) this.slideIndicator(prev, next);
            this.current = id;

            if (focus) next.focus({ preventScroll: true });
            this.keepVisible(next, animate ? scrollBehavior() : 'auto');

            if (user && this.$root.hasAttribute('data-sync-hash')) {
                window.history.replaceState(window.history.state, '', `#${encodeURIComponent(id)}`);
            }
            this.$root.dispatchEvent(new CustomEvent('d2:tab', { bubbles: true, detail: { id } }));
        },

        /** FLIP: start the new indicator where the old one was, then let it spring home. */
        slideIndicator(from, to) {
            const a = from.querySelector('.tabs__label')?.getBoundingClientRect();
            const b = to.querySelector('.tabs__label')?.getBoundingClientRect();
            if (!a || !b || !b.width) return;
            to.style.setProperty('--flip-x', `${a.left - b.left}px`);
            to.style.setProperty('--flip-s', String(a.width / b.width));
            to.classList.add('is-flipping');
            void to.offsetWidth; // commit the start position
            to.classList.remove('is-flipping');
        },

        /** Keep a tab inside the visible part of the list, clear of the edge fades. */
        keepVisible(tab, behavior = scrollBehavior()) {
            if (!tab || this.list.scrollWidth <= this.list.clientWidth + 1) return;
            const box = this.list.getBoundingClientRect();
            const r = tab.getBoundingClientRect();
            let delta = 0;
            if (r.left < box.left + FADE) delta = r.left - box.left - FADE;
            else if (r.right > box.right - FADE) delta = r.right - box.right + FADE;
            if (delta) this.list.scrollBy({ left: delta, behavior });
        },

        onClick(event) {
            const tab = event.target.closest('[data-tab]');
            if (!tab || tab.parentElement !== this.list) return;
            event.preventDefault();
            this.select(tab.dataset.tab, { focus: true });
        },

        onKeydown(event) {
            const tab = event.target.closest('[data-tab]');
            if (!tab || tab.parentElement !== this.list || event.altKey || event.ctrlKey || event.metaKey) return;
            const n = this.tabEls.length;
            const i = this.tabEls.indexOf(tab);
            const rtl = getComputedStyle(this.list).direction === 'rtl';
            let j;
            switch (event.key) {
                case 'ArrowRight':
                    j = i + (rtl ? -1 : 1);
                    break;
                case 'ArrowLeft':
                    j = i + (rtl ? 1 : -1);
                    break;
                case 'Home':
                    j = 0;
                    break;
                case 'End':
                    j = n - 1;
                    break;
                case ' ':
                case 'Enter':
                    event.preventDefault();
                    this.select(tab.dataset.tab, { focus: true });
                    return;
                default:
                    return;
            }
            event.preventDefault();
            this.select(this.tabEls[(j + n) % n].dataset.tab, { focus: true });
        },

        onScroll() {
            if (this.scrollFrame) return;
            this.scrollFrame = window.requestAnimationFrame(() => {
                this.scrollFrame = 0;
                this.updateOverflow();
            });
        },

        onScrollButton(event) {
            const towardEnd = event.currentTarget.dataset.tabsScroll === 'end';
            const rtl = getComputedStyle(this.list).direction === 'rtl';
            const step = Math.max(this.list.clientWidth * 0.7, 120);
            this.list.scrollBy({ left: step * (towardEnd ? 1 : -1) * (rtl ? -1 : 1), behavior: scrollBehavior() });
        },

        /** data-overflow="start end" (logical sides that have hidden tabs) drives the fades and buttons. */
        updateOverflow() {
            const el = this.list;
            const max = el.scrollWidth - el.clientWidth;
            const pos = Math.abs(el.scrollLeft);
            const start = max > 1 && pos > 1;
            const end = max > 1 && pos < max - 1;
            const value = [start ? 'start' : '', end ? 'end' : ''].filter(Boolean).join(' ');
            if (value) el.dataset.overflow = value;
            else delete el.dataset.overflow;
            this.scrollButtons?.forEach((button) => {
                button.hidden = !(button.dataset.tabsScroll === 'start' ? start : end);
            });
        },
    }));
};
