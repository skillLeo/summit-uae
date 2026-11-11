/**
 * Command palette controller (upgrade spec §12 C12, §5.5). Lazy chunk: components/palette.js imports it
 * on the first open, together with MiniSearch (palette/search.js) and the index JSON, which is the only
 * request the palette ever makes. Markup: resources/views/components/d2/shell/palette.blade.php.
 *
 * - Combobox + listbox: focus stays in the field; ↑/↓ move the active option (aria-activedescendant,
 *   wrapping), Enter opens it, Esc closes the dialog (modal.js), a pointer hover moves the active option.
 *   A modified click (new tab or window) keeps the link's own behaviour.
 * - States: default (the server-rendered six practice areas), loading (skeleton, only when a query waits
 *   for the index), results (groups with type chips, matches marked), no results (message + "Describe
 *   your matter"), error (message + retry; "Describe your matter" still works). A polite status inside the
 *   dialog announces the count, "no results", loading and errors.
 * - Opening a result: same-page targets (#faq-03 on the FAQ page, and a question the page's FAQ browser also
 *   holds, e.g. faq-42 on home) close the palette and follow the hash in place (accordion-hash.js / d2Tabs
 *   open it), then focus it; other pages replace the
 *   palette's history entry, so Back returns to the page as it was. "Describe your matter" closes the
 *   palette and calls prefill({ summary }) once its history entry is gone (lib/prefill.js: the wizard on
 *   this page, or the consultation form elsewhere with the text in sessionStorage).
 * - Text is set with textContent / DOM nodes only (never innerHTML). Arabic Latin runs get <bdi dir="ltr">,
 *   the visitor's query gets <bdi> so it keeps its own direction.
 */
import { closeModal } from '../components/modal.js';
import { prefill } from '../lib/prefill.js';
import { fill, fragment, plural, readJson } from './copy.js';
import { createIndex, queryTerms, search, wordMatches } from './search.js';

const LATIN_RUN = /([A-Za-z][A-Za-z0-9./-]*(?:[ \t]+[A-Za-z0-9][A-Za-z0-9./-]*)*)/;
const ANNOUNCE_DELAY = 350; // ms: one announcement per pause in typing

function appendWords(parent, text, terms) {
    if (!terms.length) {
        parent.append(text);
        return;
    }
    for (const piece of text.split(/(\s+)/)) {
        if (!piece) continue;
        if (/^\s+$/.test(piece) || !wordMatches(piece, terms)) {
            parent.append(piece);
        } else {
            const mark = document.createElement('mark');
            mark.textContent = piece;
            parent.append(mark);
        }
    }
}

/** Approved text as DOM: Latin runs isolated on Arabic pages, matching words marked. */
function rich(text, terms, locale) {
    const frag = document.createDocumentFragment();
    const parts = locale === 'ar' ? String(text ?? '').split(LATIN_RUN) : [String(text ?? '')];
    parts.forEach((part, i) => {
        if (!part) return;
        if (i % 2 === 1) {
            const bdi = document.createElement('bdi');
            bdi.dir = 'ltr';
            appendWords(bdi, part, terms);
            frag.append(bdi);
        } else {
            appendWords(frag, part, terms);
        }
    });
    return frag;
}

function focusTarget(hash) {
    let id = '';
    try {
        id = decodeURIComponent(hash.slice(1));
    } catch {
        return;
    }
    const target = id ? document.getElementById(id) : null;
    if (!target) return;
    const summary = target.matches('details') ? target.querySelector(':scope > summary') : null;
    const el = summary || target;
    if (!summary && !el.matches('a[href], button, input, select, textarea, summary, [tabindex]')) el.setAttribute('tabindex', '-1');
    el.focus({ preventScroll: true });
}

export function mount(dialog) {
    const copy = readJson('d2-palette-copy') || {};
    const locale = copy.locale || document.documentElement.lang || 'en';
    const input = dialog.querySelector('[data-palette-input]');
    const list = dialog.querySelector('[data-palette-list]');
    const featured = dialog.querySelector('[data-palette-featured]');
    const describeOption = dialog.querySelector('[data-palette-describe]');
    const describeText = dialog.querySelector('[data-palette-describe-text]');
    const notice = dialog.querySelector('[data-palette-notice]');
    const noticeTitle = dialog.querySelector('[data-palette-notice-title]');
    const noticeText = dialog.querySelector('[data-palette-notice-text]');
    const retry = dialog.querySelector('[data-palette-retry]');
    const loading = dialog.querySelector('[data-palette-loading]');
    const status = dialog.querySelector('[data-palette-status]');
    const body = dialog.querySelector('.modal__body');
    const optionTemplate = dialog.querySelector('template[data-palette-option-template]');
    if (!input || !list || !optionTemplate) return null;

    let index = null;
    let state = 'idle';
    let pending = null;
    let options = [];
    let active = -1;
    let announceTimer = 0;
    let lastAnnounced = '';

    const announce = (text) => {
        window.clearTimeout(announceTimer);
        announceTimer = window.setTimeout(() => {
            if (text === lastAnnounced) return;
            lastAnnounced = text;
            status.textContent = '';
            window.requestAnimationFrame(() => {
                status.textContent = text;
            });
        }, ANNOUNCE_DELAY);
    };

    // ------------------------------------------------------------------ options and the active one
    const collect = () => {
        options = Array.from(list.querySelectorAll('[data-palette-option]')).filter((el) => !el.hidden && !el.closest('[hidden]'));
        input.setAttribute('aria-expanded', options.length ? 'true' : 'false');
    };

    const setActive = (i, { scroll = true } = {}) => {
        if (!options.length) {
            active = -1;
            input.removeAttribute('aria-activedescendant');
            return;
        }
        active = ((i % options.length) + options.length) % options.length;
        options.forEach((el, j) => {
            const on = j === active;
            el.classList.toggle('is-active', on);
            el.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        input.setAttribute('aria-activedescendant', options[active].id);
        if (scroll) options[active].scrollIntoView({ block: 'nearest' });
    };

    // ------------------------------------------------------------------ rendering
    const clearResults = () => list.querySelectorAll('[data-palette-result]').forEach((el) => el.remove());

    // Text rows only (Design 2.1: no decorative icons on menu items, brief 3.6 rule 5).
    const optionFor = (hit, terms) => {
        const el = optionTemplate.content.firstElementChild.cloneNode(true);
        el.id = `palette-r-${String(hit.id).replace(/[^A-Za-z0-9_-]/g, '-')}`;
        el.href = hit.url;
        el.dataset.paletteType = hit.type;
        el.querySelector('[data-slot="title"]').append(rich(hit.title, terms, locale));
        const summary = el.querySelector('[data-slot="summary"]');
        if (hit.summary) summary.append(rich(hit.summary, [], locale));
        else summary.remove();
        return el;
    };

    const show = ({ featuredOn = false, loadingOn = false, describeOn = false, noticeOn = false } = {}) => {
        if (featured) featured.hidden = !featuredOn;
        if (loading) loading.hidden = !loadingOn;
        if (describeOption) describeOption.hidden = !describeOn;
        if (notice) notice.hidden = !noticeOn;
        list.setAttribute('aria-busy', loadingOn ? 'true' : 'false');
        dialog.dataset.view = loadingOn ? 'loading' : noticeOn ? 'notice' : featuredOn ? 'default' : 'results';
    };

    const setDescribe = (query) => {
        if (!describeText) return;
        describeText.replaceChildren(fragment(copy.describeWith || '', { query }, { query: 'auto' }));
    };

    const render = () => {
        const query = input.value.trim();
        const terms = queryTerms(query);
        clearResults();

        if (!terms.length) {
            show({ featuredOn: true });
            collect();
            setActive(0, { scroll: false });
            announce('');
            return;
        }

        if (state !== 'ready' && state !== 'error') {
            show({ loadingOn: true });
            collect();
            setActive(-1);
            announce(copy.loading || '');
            return;
        }

        setDescribe(query);

        if (state === 'error') {
            show({ describeOn: true, noticeOn: true });
            noticeTitle.textContent = copy.error || '';
            noticeText.hidden = true;
            if (retry) retry.hidden = false;
            collect();
            setActive(0, { scroll: false });
            announce(copy.error || '');
            return;
        }

        const result = search(index, query);
        if (!result.count) {
            show({ describeOn: true, noticeOn: true });
            noticeTitle.replaceChildren(fragment(copy.noResults || '', { query }, { query: 'auto' }));
            noticeText.hidden = false;
            if (retry) retry.hidden = true;
            collect();
            setActive(0, { scroll: false });
            announce(fill(copy.noResults || '', { query }));
            return;
        }

        show({ describeOn: true });
        const frag = document.createDocumentFragment();
        for (const group of result.groups) {
            const template = dialog.querySelector(`template[data-palette-group="${group.type}"]`);
            if (!template) continue;
            const groupEl = template.content.firstElementChild.cloneNode(true);
            groupEl.dataset.paletteResult = group.type;
            const label = groupEl.querySelector('.palette__group-label');
            label.id = `palette-g-${group.type}`;
            groupEl.setAttribute('aria-labelledby', label.id);
            for (const hit of group.items) groupEl.append(optionFor(hit, result.terms));
            frag.append(groupEl);
        }
        list.insertBefore(frag, describeOption || null);
        collect();
        setActive(0, { scroll: false });
        if (body) body.scrollTop = 0;
        announce(fill(plural(copy.results, result.count, locale), { count: result.count }));
    };

    // ------------------------------------------------------------------ index
    const load = () => {
        if (state === 'ready') return Promise.resolve(index);
        if (pending) return pending;
        state = 'loading';
        dialog.dataset.state = 'loading';
        const url = copy.index || dialog.dataset.index;
        pending = (url ? window.fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } }) : Promise.reject(new Error('no index')))
            .then((response) => {
                if (!response.ok) throw new Error(`search index: HTTP ${response.status}`);
                return response.json();
            })
            .then((data) => {
                index = createIndex(data && data.items);
                state = 'ready';
                dialog.dataset.state = 'ready';
                render();
                return index;
            })
            .catch(() => {
                state = 'error';
                dialog.dataset.state = 'error';
                pending = null;
                render();
                return null;
            });
        return pending;
    };

    // ------------------------------------------------------------------ leaving the palette
    /** Close, wait until the palette's history entry is gone (and its scroll restored), then run fn. */
    const afterClose = (fn, { fromHistory = false } = {}) => {
        const willPop = !fromHistory && !!(window.history.state && typeof window.history.state === 'object' && window.history.state.d2Overlay);
        let done = false;
        const run = () => {
            if (done) return;
            done = true;
            window.removeEventListener('popstate', onPop);
            fn();
        };
        const onPop = () => window.setTimeout(run, 0);
        dialog.addEventListener(
            'd2-modal-close',
            () => {
                if (!willPop) {
                    run();
                    return;
                }
                window.addEventListener('popstate', onPop);
                window.setTimeout(run, 600);
            },
            { once: true },
        );
        if (!closeModal('palette', { fromHistory, restore: false })) run();
    };

    const focusForm = () => {
        const form = document.getElementById('consultation-form');
        const current = document.activeElement;
        // The wizard may already have moved focus on d2:prefill: never take it away.
        if (!form || (current && current !== document.body)) return;
        if (!form.hasAttribute('tabindex')) form.setAttribute('tabindex', '-1');
        form.focus({ preventScroll: true });
    };

    const describeMatter = () => {
        const summary = input.value.trim();
        afterClose(() => {
            prefill(summary ? { summary } : {});
            window.requestAnimationFrame(() => window.requestAnimationFrame(focusForm));
        });
    };

    const goToHash = (url) => {
        const focusSoon = () => window.requestAnimationFrame(() => window.requestAnimationFrame(() => focusTarget(url.hash)));
        if (window.location.hash === url.hash) {
            // Same hash again: no hashchange would fire, so tell the deep-link handlers ourselves.
            window.dispatchEvent(new HashChangeEvent('hashchange', { oldURL: window.location.href, newURL: url.href }));
            focusSoon();
            return;
        }
        window.addEventListener('hashchange', focusSoon, { once: true });
        window.location.replace(url.href);
    };

    const activate = (option) => {
        if (!option) return;
        if (option.hasAttribute('data-palette-describe')) {
            describeMatter();
            return;
        }
        const href = option.getAttribute('href');
        if (!href) return;
        const url = new URL(href, window.location.href);
        const here = url.origin === window.location.origin && url.pathname === window.location.pathname && url.search === window.location.search;
        if (here && url.hash) {
            afterClose(() => goToHash(url), { fromHistory: true });
            return;
        }
        // A question that this page's FAQ browser also holds (home: faq-07 … faq-74) opens in place.
        if (option.dataset.paletteType === 'faq' && url.hash.length > 1) {
            let local = null;
            try {
                local = document.getElementById(decodeURIComponent(url.hash.slice(1)));
            } catch {
                local = null;
            }
            if (local && local.closest('[data-faq-browser]')) {
                const inPlace = new URL(window.location.href);
                inPlace.hash = url.hash;
                afterClose(() => goToHash(inPlace), { fromHistory: true });
                return;
            }
        }
        // Another page: replace the palette's history entry, so Back returns to this page as it was.
        closeModal('palette', { fromHistory: true, restore: false });
        window.location.replace(url.href);
    };

    // ------------------------------------------------------------------ events
    input.addEventListener('input', () => {
        if (state === 'idle') load();
        render();
    });

    input.addEventListener('keydown', (event) => {
        if (event.isComposing || event.altKey || event.ctrlKey || event.metaKey) return;
        switch (event.key) {
            case 'ArrowDown':
            case 'ArrowUp':
                event.preventDefault();
                dialog.dataset.nav = 'keyboard';
                setActive(active + (event.key === 'ArrowDown' ? 1 : -1));
                break;
            case 'Enter':
                event.preventDefault();
                if (options[active]) activate(options[active]);
                break;
            default:
        }
    });

    list.addEventListener('pointermove', (event) => {
        const option = event.target.closest?.('[data-palette-option]');
        if (!option) return;
        dialog.dataset.nav = 'pointer';
        const i = options.indexOf(option);
        if (i !== -1 && i !== active) setActive(i, { scroll: false });
    });

    list.addEventListener('click', (event) => {
        const option = event.target.closest?.('[data-palette-option]');
        if (!option) return;
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        activate(option);
    });

    retry?.addEventListener('click', () => {
        state = 'idle';
        load();
        render();
        input.focus({ preventScroll: true });
    });

    dialog.addEventListener('d2-modal-close', () => {
        input.value = '';
        lastAnnounced = '';
        status.textContent = '';
        render();
        delete dialog.dataset.nav;
        if (body) body.scrollTop = 0;
    });

    load();
    render();
    return { render, load };
}
