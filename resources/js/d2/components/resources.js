/**
 * Legal Knowledge Centre (P03, resources/views/designs/d2/inner/resources.blade.php): x-data="d2Resources"
 * on the page root.
 *
 * - Search and topic filter (<x-d2.resources.finder>, approved labels ui('resources.*')): every word of the
 *   query must occur in a guide's title, summary, category or full article (shared normalise() + light
 *   stems, resources-kit.js); the topic chips (aria-pressed) keep one category. The guide cards, their
 *   category groups, the full guides and their entries in the inner kit's contents (side list, bar and
 *   sheet) are filtered together. The result count (proposed, plural forms rendered by the server) or the
 *   approved "no results" message is shown in a status row (with "Clear Search and Filters") and announced
 *   through the layout's polite live region. Nothing leaves the page and nothing is stored.
 * - Deep links (designer note: "restore the selected guide when following a deep link"): a link to a guide
 *   or one of its parts that the filter hides clears the filter first; every landing scrolls below the
 *   sticky header and moves keyboard focus to the target heading (resources-kit.js deepLinks). A guide's
 *   title lands with the guide's whole top (category label, phone card edge) clear of the header and the
 *   contents bar: --kc-lead, measured per guide (measureLead).
 * - Phones (below 768 px, resources.css): each guide starts as its card. Its disclosure
 *   ([data-kc-toggle], aria-expanded) opens and closes the body in place (.is-open; .is-revealed plays the
 *   entrance once); a deep link to the guide or to any anchor inside its body opens it first (revealTarget),
 *   then lands on the target. From 768 px the body always shows and nothing changes.
 * - Phone swipe rails of the directory: position dots for the visible cards.
 * - The topic has two controls (resources.css shows one): the chips from 768 px and, on phones, the native
 *   picker ([data-kc-topic-select], D30 / D41 rule: no half-cut label) whose box shows the chosen approved name
 *   in full ([data-kc-topic-value], topicLabel). apply() keeps both in step. The search box ([data-kc-box], D09)
 *   shows the approved placeholder wrapped while the field is empty (CSS only); a press anywhere in it focuses
 *   the field.
 * Without JavaScript the finder and the disclosures are not shown (CSS: html.js only) and every guide, card
 * and anchor works.
 */
import { announce } from '../lib/announce.js';
import { deepLinks, guideText, isFiltering, rail, resultParts, searchableText, topicLabel, visibleGuides } from './resources-kit.js';

const DEBOUNCE = 160;

export default (Alpine) => {
    Alpine.data('d2Resources', () => {
        // Per-instance state outside Alpine's reactive proxy (DOM references, timers, cleanups).
        let ui = null;
        let timer = 0;
        let cleanups = [];

        return {
            init() {
                const root = this.$root;
                const finder = root.querySelector('[data-kc-finder]');
                const rails = Array.from(root.querySelectorAll('[data-kc-rail]')).map((list) => rail(list, list.parentElement?.querySelector('[data-kc-dots]')));
                cleanups.push(() => rails.forEach((r) => r.destroy()));

                const articles = Array.from(root.querySelectorAll('[data-kc-guide]'));
                const cards = new Map(Array.from(root.querySelectorAll('[data-kc-card]')).map((li) => [li.dataset.kcCard, li]));
                const guides = articles.map((article) => {
                    const code = article.dataset.kcGuide;
                    const card = cards.get(code);
                    // The disclosure labels ("Read the full guide") are not guide text: they would match every guide.
                    return { code, topic: article.dataset.kcTopic || '', text: guideText(card?.textContent ?? '', searchableText(article, '[data-kc-toggle]')) };
                });

                ui = {
                    root,
                    finder,
                    rails,
                    articles,
                    cards,
                    guides,
                    groups: Array.from(root.querySelectorAll('[data-kc-group]')),
                    input: finder?.querySelector('[data-kc-query]') ?? null,
                    chips: finder ? Array.from(finder.querySelectorAll('[data-kc-topic-chip]')) : [],
                    select: finder?.querySelector('[data-kc-topic-select]') ?? null,
                    value: finder?.querySelector('[data-kc-topic-value]') ?? null,
                    status: finder?.querySelector('[data-kc-status]') ?? null,
                    clear: finder?.querySelector('[data-kc-clear]') ?? null,
                    foot: finder?.querySelector('[data-kc-foot]') ?? null,
                    read: root.querySelector('[data-kc-read]'),
                    directory: root.querySelector('[data-kc-directory]'),
                    strings: readStrings(finder),
                    query: '',
                    topic: '',
                };

                if (finder && ui.input) {
                    const onInput = () => {
                        window.clearTimeout(timer);
                        timer = window.setTimeout(() => this.apply(), DEBOUNCE);
                    };
                    const onSubmit = (event) => {
                        event.preventDefault();
                        window.clearTimeout(timer);
                        this.apply();
                    };
                    const onChip = (event) => {
                        const chip = event.currentTarget;
                        ui.topic = chip.dataset.kcTopicChip || '';
                        this.apply();
                    };
                    // Phones: the picker chooses the same topic as the chips.
                    const onPick = () => {
                        ui.topic = ui.select ? ui.select.value : '';
                        this.apply();
                    };
                    const onClear = (event) => {
                        if (event.currentTarget.getAttribute('aria-disabled') === 'true') return;
                        this.reset();
                        ui.input.focus();
                    };
                    // The search box (resources.css, the FAQ search's solution) is taller than the input while it shows
                    // the wrapped approved placeholder: a press anywhere in it puts the caret in the field.
                    const box = finder.querySelector('[data-kc-box]');
                    const onBoxDown = (event) => {
                        if (event.button === 0 && event.target !== ui.input) event.preventDefault();
                    };
                    const onBoxClick = (event) => {
                        if (event.target !== ui.input && document.activeElement !== ui.input) ui.input.focus();
                    };
                    box?.addEventListener('mousedown', onBoxDown);
                    box?.addEventListener('click', onBoxClick);
                    cleanups.push(() => {
                        box?.removeEventListener('mousedown', onBoxDown);
                        box?.removeEventListener('click', onBoxClick);
                    });
                    ui.input.addEventListener('input', onInput);
                    finder.querySelector('[data-kc-form]')?.addEventListener('submit', onSubmit);
                    ui.chips.forEach((chip) => chip.addEventListener('click', onChip));
                    ui.select?.addEventListener('change', onPick);
                    ui.clear?.addEventListener('click', onClear);
                    cleanups.push(() => {
                        ui.input.removeEventListener('input', onInput);
                        ui.chips.forEach((chip) => chip.removeEventListener('click', onChip));
                        ui.select?.removeEventListener('change', onPick);
                        ui.clear?.removeEventListener('click', onClear);
                    });
                    // A value or topic restored by the browser (back/forward cache, autofill) is applied at once.
                    if (ui.select?.value) ui.topic = ui.select.value;
                    if (ui.input.value || ui.topic) this.apply();
                }

                // Guide titles land with their card's top edge clear of the header and the contents bar (resources.css
                // --kc-lead): measured per guide, before deepLinks aims at an incoming #guide (a category name that
                // wraps on a phone makes the card's top taller); again when a head changes size and once the fonts are in.
                const leads = () => articles.forEach(measureLead);
                leads();
                if (typeof ResizeObserver === 'function') {
                    const heads = new ResizeObserver(leads);
                    articles.forEach((article) => {
                        const head = article.querySelector('.kc-guide__head');
                        if (head) heads.observe(head);
                    });
                    cleanups.push(() => heads.disconnect());
                }
                document.fonts?.ready?.then(() => (ui ? leads() : null));

                // Phone disclosures: one delegated listener for the nine guides.
                const onToggle = (event) => {
                    const toggle = event.target instanceof Element ? event.target.closest('[data-kc-toggle]') : null;
                    const article = toggle?.closest('[data-kc-guide]');
                    if (!article || !root.contains(article)) return;
                    this.openGuide(article, !article.classList.contains('is-open'), { reveal: true });
                };
                root.addEventListener('click', onToggle);
                cleanups.push(() => root.removeEventListener('click', onToggle));

                cleanups.push(deepLinks(root, { reveal: (target) => this.revealTarget(target) }));
            },

            destroy() {
                window.clearTimeout(timer);
                cleanups.forEach((fn) => fn());
                cleanups = [];
                ui = null;
            },

            /** Show only the guides that match the current query and topic. */
            apply() {
                if (!ui) return;
                ui.query = ui.input ? ui.input.value : '';
                const filter = { query: ui.query, topic: ui.topic };
                const active = isFiltering(filter);
                const visible = new Set(active ? visibleGuides(ui.guides, filter) : ui.guides.map((g) => g.code));

                for (const [code, card] of ui.cards) card.hidden = !visible.has(code);
                for (const group of ui.groups) {
                    group.hidden = !Array.from(group.querySelectorAll('[data-kc-card]')).some((card) => !card.hidden);
                }
                for (const article of ui.articles) article.hidden = !visible.has(article.dataset.kcGuide);
                this.syncContents(visible);
                // Nothing matches: the guides and the directory (its "Legal Guides" heading included) step out, so no
                // heading stands over an empty band; the finder's status row carries the approved no-results message.
                if (ui.read) ui.read.hidden = visible.size === 0;
                if (ui.directory) ui.directory.hidden = visible.size === 0;

                for (const chip of ui.chips) chip.setAttribute('aria-pressed', String((chip.dataset.kcTopicChip || '') === ui.topic));
                // The phones' picker shows the same topic, its approved name whole in the box (e.g. "All Topics" after a reset).
                if (ui.select) {
                    ui.select.value = ui.topic;
                    if (ui.value) ui.value.textContent = topicLabel(ui.select.options, ui.topic);
                }
                if (ui.clear) ui.clear.setAttribute('aria-disabled', String(!active));
                if (ui.foot) ui.foot.hidden = !active;
                this.status(active ? visible.size : null);
                ui.rails.forEach((r) => r.refresh());
            },

            /** Back to every guide: empty query, all topics. */
            reset() {
                if (!ui) return;
                window.clearTimeout(timer);
                if (ui.input) ui.input.value = '';
                ui.topic = '';
                this.apply();
            },

            /** The inner kit's contents (side list, bar list, sheet): hide the entries of hidden guides. */
            syncContents(visible) {
                const anchors = new Map(ui.articles.map((article) => [article.getAttribute('aria-labelledby'), article.dataset.kcGuide]));
                for (const link of document.querySelectorAll('.toc__link[href^="#"]')) {
                    const code = anchors.get(decodeHash(link.getAttribute('href')));
                    const item = link.closest('li');
                    if (code && item) item.hidden = !visible.has(code);
                }
            },

            /** Status line: null = nothing filtered (empty), else the count (0 = the approved message). */
            status(count) {
                const el = ui.status;
                if (!el) return;
                if (count === null) {
                    el.replaceChildren();
                    el.classList.remove('is-empty');
                    return;
                }
                const { empty, parts } = resultParts(ui.strings, count);
                el.classList.toggle('is-empty', empty);
                el.replaceChildren(...parts.map((part) => {
                    if (!part.number) return document.createTextNode(part.text);
                    const bdi = document.createElement('bdi');
                    bdi.textContent = part.text;
                    return bdi;
                }));
                // The row may have just appeared: announce through the layout's live region (always present).
                announce(el.textContent.trim());
            },

            /**
             * A deep link into a guide: clear a filter that hides it, and open a guide shown as its phone card
             * (true when the page changed, so the caller aims at the target again).
             */
            revealTarget(target) {
                if (!ui || !target) return false;
                let changed = false;
                const hidden = target.closest('[data-kc-guide][hidden], [data-kc-card][hidden], [data-kc-group][hidden], [data-kc-read][hidden], [data-kc-directory][hidden]');
                if (hidden) {
                    this.reset();
                    changed = true;
                }
                const article = target.closest('[data-kc-guide]');
                if (article && !article.classList.contains('is-open')) {
                    // Only a phone card changes the layout; from 768 px the body is already there.
                    const body = article.querySelector('[data-kc-body]');
                    const folded = !!body && body.getClientRects().length === 0;
                    this.openGuide(article, true);
                    changed = changed || folded;
                }
                return changed;
            },

            /** Open (or close) a guide's body under its card; reveal: play the entrance (the disclosure only). */
            openGuide(article, open = true, { reveal = false } = {}) {
                article.classList.toggle('is-open', open);
                article.classList.toggle('is-revealed', open && reveal);
                article.querySelector('[data-kc-toggle]')?.setAttribute('aria-expanded', String(open));
            },
        };
    });
};

/**
 * --kc-lead on a guide: the distance from its card's top edge to its title (the anchor of every link to the
 * guide), set through CSSOM (the CSP allows it). A guide hidden by the filter keeps its last value.
 */
function measureLead(article) {
    const card = article.querySelector('.kc-guide__card');
    const title = article.querySelector('.kc-guide__title');
    if (!card || !title || card.getClientRects().length === 0) return;
    const lead = Math.max(0, Math.round(title.getBoundingClientRect().top - card.getBoundingClientRect().top));
    article.style.setProperty('--kc-lead', `${lead}px`);
}

function readStrings(finder) {
    const node = finder?.querySelector('script[data-kc-strings]');
    if (!node) return { results: {}, none: '' };
    try {
        const data = JSON.parse(node.textContent || '{}');
        return { results: data.results ?? {}, none: typeof data.none === 'string' ? data.none : '' };
    } catch {
        return { results: {}, none: '' };
    }
}

function decodeHash(href) {
    try {
        return decodeURIComponent(String(href).slice(1));
    } catch {
        return '';
    }
}
