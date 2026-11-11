/**
 * d2Faq (<x-d2.faq.browser>): the FAQ browser (upgrade spec §12 C11) on top of the Phase A tabs (d2Tabs)
 * and accordion (<details>). The server renders every question and answer, so without JavaScript the
 * whole browser is readable (the tabs are jump links, every category shows). This adds:
 *
 * - A local search filter over every question and answer (components/faq-filter.js: normalise() + the
 *   light stemmer; every word must match). While it runs, the tab bar steps aside and the matching
 *   questions of every category show under their category headings, matching words marked. The count,
 *   or the approved "No matching question found…", is shown and announced (role="status", once per
 *   pause in typing). With no result the category tabs come back below the message ("choose a category
 *   below"). Esc in the field, "Show all questions", a tab or a deep link (#faq-42, #home-faq-3, #cat-07)
 *   leave the search.
 * - "Start here" (end of each category): prefill({ service }) for the wizard (lib/prefill.js); the link's
 *   href is the same consultation URL for a new tab and without JavaScript.
 * - Optional (P05): "Copy question link" buttons (toast with the approved confirmation or failure text)
 *   and "Show all answers" / "Hide all answers" per category.
 * - The phone category select ([data-faq-picker], Design 2.1 D41): choosing a category selects its tab (the same
 *   d2Tabs path as a press on the tab, so the panel, the hash rules and the search exit stay one behaviour), and
 *   every tab change (a deep link, a key, a press) sets the select to it.
 * - The search box ([data-faq-box], Design 2.1 D09): while the field is empty it shows the approved example
 *   ("Enter a word such as: …", the input's description) wrapped from the input's own line; .is-filled hides it
 *   once a word is typed; a press anywhere in the box focuses the field.
 *
 * Strings come from the JSON block <script type="application/json" data-faq-copy> in the browser markup.
 * The DOM is changed directly (no template expressions), as the Alpine CSP build requires.
 */
import { prefill } from '../lib/prefill.js';
import { toast } from '../lib/toast.js';
import { fragment, plural } from '../palette/copy.js';
import { filterTerms, haystack, markRuns, matches } from './faq-filter.js';

const ANNOUNCE_DELAY = 300; // ms
const SERVICE = /^S(0[1-9]|1[01])$/;

const modified = (event) => event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;

/** Wrap the matching words of every text node under `root` in <mark> (bdi and other markup are kept). */
function highlight(root, terms) {
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    for (const node of nodes) {
        const runs = markRuns(node.data, terms);
        if (!runs.some((run) => run.mark)) continue;
        const frag = document.createDocumentFragment();
        for (const run of runs) {
            if (run.mark) {
                const mark = document.createElement('mark');
                mark.textContent = run.text;
                frag.append(mark);
            } else {
                frag.append(run.text);
            }
        }
        node.replaceWith(frag);
    }
}

export default (Alpine) => {
    Alpine.data('d2Faq', () => ({
        init() {
            const root = this.$root;
            this.root = root;
            this.copy = {};
            try {
                this.copy = JSON.parse(root.querySelector(':scope > script[data-faq-copy]')?.textContent || '{}') || {};
            } catch {
                this.copy = {};
            }
            this.locale = this.copy.locale || document.documentElement.lang || 'en';
            this.input = root.querySelector('[data-faq-search]');
            this.status = root.querySelector('[data-faq-status]');
            this.resetButton = root.querySelector('[data-faq-reset]');
            this.tabs = root.querySelector('.tabs');
            this.panels = Array.from(root.querySelectorAll('[data-faq-panel]'));
            this.items = Array.from(root.querySelectorAll('[data-faq-item]')).map((el) => {
                const title = el.querySelector('.acc__title');
                const answer = el.querySelector('.acc__panel');
                return {
                    el,
                    title,
                    panel: el.closest('[data-faq-panel]'),
                    original: title ? title.cloneNode(true) : null,
                    text: haystack(title?.textContent, answer?.textContent),
                    marked: false,
                };
            });
            this.searching = false;
            root.classList.add('is-ready');

            this.onInput = () => this.filter();
            this.onKeydown = (event) => {
                if (event.key !== 'Escape' || !this.input.value) return;
                event.preventDefault();
                event.stopPropagation();
                this.clear();
            };
            this.input?.addEventListener('input', this.onInput);
            this.input?.addEventListener('keydown', this.onKeydown);

            // The search box (faq.css) draws the approved example inside the field while it is empty (.is-filled
            // hides it), wrapped, so it can be taller than the input: a press anywhere in it puts the caret in the
            // field (mousedown keeps the focus where it is meanwhile).
            this.box = root.querySelector('[data-faq-box]');
            this.syncBox();
            this.onBoxDown = (event) => {
                if (event.button === 0 && event.target !== this.input) event.preventDefault();
            };
            this.onBoxClick = (event) => {
                if (this.input && event.target !== this.input && document.activeElement !== this.input) this.input.focus();
            };
            this.box?.addEventListener('mousedown', this.onBoxDown);
            this.box?.addEventListener('click', this.onBoxClick);
            this.resetButton?.addEventListener('click', () => {
                this.clear();
                this.input?.focus();
            });
            // The approved "Search" button: the same search as typing (the status line announces the result); an
            // empty field gets the focus instead.
            root.querySelector('[data-faq-submit]')?.addEventListener('click', () => {
                if (!this.input) return;
                if (!this.input.value.trim()) {
                    this.input.focus();
                    return;
                }
                this.filter();
            });

            // A tab press or a tab change (keyboard) while searching shows that category instead.
            this.tabs?.addEventListener(
                'click',
                (event) => {
                    if (this.searching && event.target.closest?.('[data-tab]')) this.clear();
                },
                true,
            );
            this.tabs?.addEventListener('d2:tab', (event) => {
                if (this.searching) this.clear();
                if (this.picker && event.detail?.id) this.picker.value = event.detail.id;
            });

            // Phones (D41): the select picks the category by pressing its tab (d2Tabs handles it, focus stays here).
            this.picker = root.querySelector('[data-faq-picker]');
            this.picker?.addEventListener('change', () => {
                const id = this.picker.value;
                const tab = Array.from(this.tabs?.querySelectorAll('[data-tab]') ?? []).find((el) => el.dataset.tab === id);
                if (this.searching) this.clear();
                tab?.click();
            });

            // Deep links always show their target. Registered before d2Tabs and accordion-hash.js
            // (parent components initialise first, modules after Alpine), so the search is gone first.
            this.onHash = () => {
                if (this.searching) this.clear();
            };
            window.addEventListener('hashchange', this.onHash);

            this.onClick = (event) => this.click(event);
            root.addEventListener('click', this.onClick);

            this.onToggle = (event) => {
                const panel = event.target.closest?.('[data-faq-panel]');
                if (panel) this.syncExpand(panel);
            };
            root.addEventListener('toggle', this.onToggle, true);
            this.panels.forEach((panel) => this.syncExpand(panel));

            // Styleguide specimens start in a search state (data-faq-demo-query="cheque"), once d2Tabs (a
            // child component, initialised after this one) has made its first selection.
            if (root.dataset.faqDemoQuery && this.input) {
                window.requestAnimationFrame(() => {
                    this.input.value = root.dataset.faqDemoQuery;
                    this.filter();
                });
            }
        },

        destroy() {
            window.removeEventListener('hashchange', this.onHash);
            window.clearTimeout(this.sayTimer);
        },

        // ------------------------------------------------------------------ search
        /** The example drawn in the field shows only while the field is empty. */
        syncBox() {
            this.box?.classList.toggle('is-filled', Boolean(this.input?.value));
        },

        filter() {
            this.syncBox();
            const terms = filterTerms(this.input?.value ?? '');
            if (!terms.length) {
                if (this.searching) this.exit();
                return;
            }
            this.searching = true;
            this.root.classList.add('is-searching');
            const perPanel = new Map();
            let count = 0;
            for (const item of this.items) {
                const hit = matches(item.text, terms);
                item.el.hidden = !hit;
                if (hit) {
                    count += 1;
                    perPanel.set(item.panel, (perPanel.get(item.panel) || 0) + 1);
                    this.mark(item, terms);
                } else {
                    this.unmark(item);
                }
            }
            for (const panel of this.panels) panel.hidden = !perPanel.get(panel);
            this.root.classList.toggle('is-empty', count === 0);
            if (this.resetButton) this.resetButton.hidden = false;
            this.say(count ? { template: plural(this.copy.results, count, this.locale), count } : { template: this.copy.noResults || '' });
        },

        exit() {
            this.searching = false;
            this.root.classList.remove('is-searching', 'is-empty');
            for (const item of this.items) {
                item.el.hidden = false;
                this.unmark(item);
            }
            const enhanced = this.tabs?.classList.contains('is-enhanced');
            const current = this.tabs?.querySelector('[role="tab"][aria-selected="true"]')?.dataset.tab;
            for (const panel of this.panels) panel.hidden = enhanced ? panel.id !== current : false;
            if (this.resetButton) this.resetButton.hidden = true;
            this.say(null);
        },

        clear() {
            if (this.input) this.input.value = '';
            this.syncBox();
            this.exit();
        },

        say(message) {
            window.clearTimeout(this.sayTimer);
            if (!this.status) return;
            if (!message) {
                this.status.replaceChildren();
                return;
            }
            this.sayTimer = window.setTimeout(() => {
                this.status.replaceChildren(fragment(message.template, message.count === undefined ? {} : { count: String(message.count) }, { count: 'ltr' }));
            }, ANNOUNCE_DELAY);
        },

        mark(item, terms) {
            if (!item.title || !item.original) return;
            const fresh = item.original.cloneNode(true);
            highlight(fresh, terms);
            item.title.replaceChildren(...fresh.childNodes);
            item.marked = true;
        },

        unmark(item) {
            if (!item.marked) return;
            item.title.replaceChildren(...item.original.cloneNode(true).childNodes);
            item.marked = false;
        },

        // ------------------------------------------------------------------ actions
        click(event) {
            if (event.defaultPrevented) return;
            const start = event.target.closest?.('[data-faq-start]');
            if (start) {
                if (modified(event)) return;
                event.preventDefault();
                const service = start.dataset.faqStart;
                prefill(SERVICE.test(service || '') ? { service } : {});
                return;
            }
            const copy = event.target.closest?.('[data-faq-copy-link]');
            if (copy) {
                this.copyLink(copy.dataset.faqCopyLink);
                return;
            }
            const expand = event.target.closest?.('[data-faq-expand]');
            if (expand) this.toggleAll(expand.closest('[data-faq-panel]'));
        },

        async copyLink(id) {
            if (!id) return;
            // Path and anchor only: query strings never travel in a copied link.
            const url = `${window.location.origin}${window.location.pathname}#${encodeURIComponent(id)}`;
            try {
                await navigator.clipboard.writeText(url);
                toast(this.copy.linkCopied, { tone: 'success' });
            } catch {
                toast(this.copy.copyFailed, { tone: 'danger' });
            }
        },

        toggleAll(panel) {
            if (!panel) return;
            const list = Array.from(panel.querySelectorAll('[data-faq-item]'));
            const open = list.some((details) => !details.open);
            list.forEach((details) => {
                details.open = open;
            });
            this.syncExpand(panel);
        },

        syncExpand(panel) {
            const button = panel.querySelector('[data-faq-expand]');
            if (!button) return;
            const list = Array.from(panel.querySelectorAll('[data-faq-item]'));
            const allOpen = list.length > 0 && list.every((details) => details.open);
            // The label says what the button will do (no aria-pressed as well: that would contradict it).
            const label = button.querySelector('.btn__label');
            if (label) label.textContent = allOpen ? this.copy.collapseAll : this.copy.expandAll;
        },
    }));
};
