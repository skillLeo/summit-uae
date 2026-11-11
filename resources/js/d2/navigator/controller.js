/**
 * Matter Navigator controller (Design 2 home, <section id="navigator" data-navigator>). Mounted lazily by
 * resources/js/d2/components/navigator.js. Ports Design 1's behaviour (resources/js/components/navigator.js)
 * and avoids the Design 1 review findings (docs/design-1-review.md A11Y-02/03/04/08/11/14/15, NAV-01…05,
 * SEC-10):
 *
 * - choosing a situation shows its server-rendered starting file and writes #navigator=S05-2 with
 *   history.replaceState (no history entries); the hash (and ?navigator=, the no-JS form) is read on load,
 *   and a later hashchange selects again;
 * - arrow keys in the radio group update the file but never move focus or open anything (WCAG 3.2.2);
 *   a pointer press, Space or Enter confirms: ≥ 1280 px the card is beside the list, sticky while the list
 *   scrolls with the page (navigator/sticky.js; a new file starts in view; Enter focuses its title), below
 *   1280 px the card moves into the Phase A sheet (focus trap, inert page, Esc, Back);
 * - document ticks live in localStorage (summit.navigator.v1, Design 1's value shape) and never leave
 *   the browser; "Request a Legal Consultation" calls prefill({ service, situation });
 * - Print prints only the starting file (html.is-printing-navigator, navigator.css @media print);
 *   Copy link copies the #navigator link and confirms with a toast (a failed copy says so);
 * - the search (lib/normalise.js) and the practice-area chips filter the list; the result count or the
 *   "no situation matches" message goes to a polite status, never the whole panel; below 768 px the chips
 *   are a sideways rail whose faded edges follow its scroll (.is-overflow, .at-start, .at-end);
 * - below 1280 px each service is a row that opens its situations (d2Navigator toggles it): a chosen
 *   situation opens its own row (navigator/groups.js), so the checked radio is always in view.
 */
import { announce } from '../lib/announce.js';
import { normalise } from '../lib/normalise.js';
import { prefill } from '../lib/prefill.js';
import { reducedMotion, scrollBehavior } from '../lib/prefs.js';
import { activeSheet, closeSheet, openSheet } from '../lib/sheet.js';
import { read, write } from '../lib/storage.js';
import { toast } from '../lib/toast.js';
import { setGroupOpen } from './groups.js';
import { stickyFile } from './sticky.js';
import {
    STORAGE_KEY,
    applyFilter,
    clearTicks,
    isolateParts,
    readTicks,
    readyCount,
    refFromHash,
    requestUrl,
    rovingIndex,
    setTick,
    shareUrl,
    tokenize,
} from './logic.js';

const DESKTOP = '(width >= 1280px)';
const SHEET = 'navigator';
const ACTIVATION_KEYS = new Set([' ', 'Enter']);

function readConfig(root) {
    const el = root.querySelector('script[data-navigator-config]');
    if (!el) return null;
    try {
        return JSON.parse(el.textContent || '{}');
    } catch {
        return null;
    }
}

/** Replaces el's content with `text`; its FSI … PDI runs (numbers) become <bdi> elements. */
function setIsolated(el, text) {
    if (!el) return;
    el.replaceChildren(
        ...isolateParts(text).map((part) => {
            if (!part.isolate) return document.createTextNode(part.text);
            const bdi = document.createElement('bdi');
            bdi.textContent = part.text;
            return bdi;
        }),
    );
}

/** Runs `callback` once the Back step of a closing sheet has settled (popstate), at most 400 ms later. */
function afterHistory(callback) {
    let done = false;
    let timer = 0;
    const run = () => {
        if (done) return;
        done = true;
        window.removeEventListener('popstate', run);
        window.clearTimeout(timer);
        window.requestAnimationFrame(() => callback());
    };
    window.addEventListener('popstate', run);
    timer = window.setTimeout(run, 400);
}

/** Clipboard fallback (no async Clipboard API): a hidden read-only field next to the button. */
function legacyCopy(text, near) {
    const area = document.createElement('textarea');
    area.value = text;
    area.readOnly = true;
    area.tabIndex = -1;
    area.className = 'sr-only';
    area.setAttribute('aria-hidden', 'true');
    (near?.parentElement || document.body).append(area);
    const back = document.activeElement;
    area.select();
    let ok = false;
    try {
        ok = document.execCommand('copy');
    } catch {
        ok = false;
    }
    area.remove();
    if (back instanceof HTMLElement) back.focus({ preventScroll: true });
    return ok;
}

export function mount(root) {
    const config = readConfig(root);
    const form = root.querySelector('[data-nav-form]');
    const list = root.querySelector('[data-nav-list]');
    const file = root.querySelector('[data-nav-file]');
    const slot = root.querySelector('[data-nav-slot]');
    if (!config || !form || !list || !file || !slot) return null;

    const labels = config.labels || {};
    const storageKey = config.storageKey || STORAGE_KEY;
    const search = root.querySelector('[data-nav-search]');
    const toolbar = root.querySelector('[data-nav-chips]');
    const chipsWrap = root.querySelector('[data-nav-chips-wrap]');
    const rail = root.querySelector('[data-nav-rail]');
    const chips = Array.from(root.querySelectorAll('[data-nav-chip]'));
    const empty = root.querySelector('[data-nav-empty]');
    const status = root.querySelector('[data-nav-status]');
    const fileEmpty = file.querySelector('[data-nav-file-empty]');
    const printDate = file.querySelector('[data-nav-print-date]');
    const sheet = document.querySelector(`[data-sheet="${SHEET}"]`);
    const sheetSlot = sheet?.querySelector('[data-nav-sheet-slot]') || null;
    const sheetActions = sheet?.querySelector('[data-nav-sheet-actions]') || null;
    const desktop = window.matchMedia(DESKTOP);
    const rtl = () => getComputedStyle(root).direction === 'rtl';

    const groups = new Map(Array.from(root.querySelectorAll('[data-nav-group]'), (el) => [el.dataset.navGroup, el]));
    const groupTitle = (code) => groups.get(code)?.querySelector('[data-nav-group-title]')?.textContent.trim() || '';
    const chipLabel = new Map(chips.map((chip) => [chip.dataset.navChip, chip.textContent.trim()]));
    const items = Array.from(list.querySelectorAll('[data-nav-item]'), (el) => {
        const ref = el.dataset.navItem || '';
        const code = ref.split('-')[0];
        const input = el.querySelector('input[type="radio"]');
        const label = el.querySelector('.radio__label');
        const text = normalise(`${label?.textContent || ''} ${groupTitle(code)} ${chipLabel.get(code) || ''}`);
        return { el, ref, code, input, label, text };
    }).filter((item) => item.input && item.label);
    const byRef = new Map(items.map((item) => [item.ref, item]));
    // Live files (the server's choice, if any) and the inert templates of the other services.
    const files = new Map(Array.from(file.querySelectorAll('[data-file]'), (el) => [el.dataset.file, el]));
    const templates = new Map(Array.from(file.querySelectorAll('template[data-nav-file-template]'), (el) => [el.dataset.navFileTemplate, el]));
    const fileActions = file.querySelector('[data-nav-file-actions]');

    let current = null;
    let service = '';
    let ticks = readTicks(read(storageKey, {}));
    let lastKey = '';
    let lastKeyAt = -Infinity;
    let lastPointerAt = -Infinity;
    let statusTimer = 0;
    const cleanups = [];
    // ≥ 1280 px: the card's slot is sticky beside the list (navigator.css); a taller file stays readable to its end.
    const sticky = stickyFile(slot, { active: () => desktop.matches });
    cleanups.push(() => sticky.destroy());
    const on = (target, type, handler, options) => {
        if (!target) return;
        target.addEventListener(type, handler, options);
        cleanups.push(() => target.removeEventListener(type, handler, options));
    };
    const headerHeight = () => parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--header-h')) || 72;

    // ------------------------------------------------------------------------------ checklist
    function renderProgress(code) {
        const article = files.get(code);
        if (!article) return;
        const total = article.querySelectorAll('input[data-nav-doc]').length;
        const ready = readyCount(ticks, code, total);
        const bar = article.querySelector('[data-nav-progress]');
        if (bar) {
            bar.max = Math.max(total, 1);
            bar.value = ready;
        }
        setIsolated(article.querySelector('.progress__label'), config.progress?.[total]?.[ready] ?? `${ready} / ${total}`);
        const clear = article.querySelector('[data-nav-clear-ticks]');
        if (clear) clear.hidden = ready === 0;
    }

    function applyTicksTo(code, article) {
        for (const box of article.querySelectorAll('input[data-nav-doc]')) {
            box.checked = ticks[code]?.[Number(box.dataset.navDoc)] === true;
        }
        renderProgress(code);
    }

    function applyTicks() {
        for (const [code, article] of files) applyTicksTo(code, article);
    }

    /** The file of `code`, instantiated from its <template> the first time it is needed. */
    function fileFor(code) {
        if (files.has(code)) return files.get(code);
        const template = templates.get(code);
        const article = template?.content.firstElementChild?.cloneNode(true);
        if (!article) return null;
        template.replaceWith(article);
        templates.delete(code);
        files.set(code, article);
        applyTicksTo(code, article);
        return article;
    }

    // ------------------------------------------------------------------------------ selection
    function writeHash(ref) {
        const hash = `#navigator=${ref}`;
        if (window.location.hash === hash) return;
        try {
            // Keep history.state: an open sheet stores its Back entry there.
            window.history.replaceState(window.history.state, '', `${window.location.pathname}${window.location.search}${hash}`);
        } catch {
            /* sandboxed document: the link still works through "Copy link" */
        }
    }

    function updateRequests(href) {
        const links = [...file.querySelectorAll('[data-nav-request]'), ...(sheetActions ? sheetActions.querySelectorAll('[data-nav-request]') : [])];
        for (const link of links) if (link.getAttribute('aria-disabled') !== 'true') link.setAttribute('href', href);
    }

    function select(ref, { source = 'user' } = {}) {
        const item = byRef.get(ref);
        if (!item) return false;
        const previous = current;
        current = item;
        if (!item.input.checked) item.input.checked = true;
        setGroupOpen(groups.get(item.code), true);
        const article = fileFor(item.code);
        if (fileEmpty) fileEmpty.hidden = true;
        if (fileActions) fileActions.hidden = false;
        for (const [code, el] of files) el.hidden = code !== item.code;
        if (article) {
            const quote = article.querySelector('[data-nav-quote]');
            if (quote) quote.replaceChildren(...Array.from(item.label.childNodes, (node) => node.cloneNode(true)));
            const situation = article.querySelector('[data-nav-situation]');
            if (situation) situation.hidden = false;
            renderProgress(item.code);
            if (previous && previous.code !== item.code && !reducedMotion()) {
                article.classList.remove('is-entering');
                void article.offsetWidth; // restart the entrance fade
                article.classList.add('is-entering');
            }
        }
        updateRequests(requestUrl(config.request, item.code, item.ref));
        writeHash(item.ref);
        // Beside the list (≥ 1280 px) nothing moves: say briefly which file is now shown.
        if (source === 'user' && desktop.matches && previous?.code !== item.code && labels.updated) {
            announce(labels.updated.replace(':service', groupTitle(item.code)));
        }
        // Beside the list the new file shows from its start (the sticky offset of a tall file goes back to 96 px).
        if (previous?.code !== item.code) sticky.reset();
        return true;
    }

    function placeInline() {
        if (file.parentElement !== slot) slot.append(file);
    }

    function openFile(opener) {
        if (!sheet || !sheetSlot) return;
        if (file.parentElement !== sheetSlot) sheetSlot.append(file);
        openSheet(SHEET, opener);
    }

    function focusFile() {
        const title = current ? files.get(current.code)?.querySelector('.nav-file__title') : null;
        if (!title) return;
        title.focus({ preventScroll: true });
        const box = title.getBoundingClientRect();
        if (box.top < headerHeight() || box.bottom > window.innerHeight) title.scrollIntoView({ block: 'start', behavior: scrollBehavior() });
    }

    /** An explicit choice (pointer, Space, Enter): bring the file into view. */
    function confirmChoice(item, { enter = false } = {}) {
        if (!desktop.matches) {
            openFile(item.input);
            return;
        }
        if (enter) {
            focusFile();
            return;
        }
        // The sticky file shows its start (sticky.reset in select); only past the end of the list, where the card
        // leaves with its column, can its top be out of view: then bring it back.
        sticky.reset();
        if (file.getBoundingClientRect().top < headerHeight()) file.scrollIntoView({ block: 'start', behavior: scrollBehavior() });
    }

    /** Deep link: scroll the section into view (again after web fonts settle) and show the file. */
    function reveal({ open = false } = {}) {
        const item = current;
        root.scrollIntoView({ block: 'start', behavior: 'auto' });
        if (!desktop.matches && open && item) openFile(item.input);
        if (!document.fonts) return;
        let moved = false;
        const stop = () => {
            moved = true;
        };
        ['wheel', 'touchstart', 'keydown', 'pointerdown'].forEach((type) => window.addEventListener(type, stop, { once: true, passive: true }));
        document.fonts.ready.then(() => {
            if (!moved && desktop.matches) window.requestAnimationFrame(() => root.scrollIntoView({ block: 'start', behavior: 'auto' }));
        });
    }

    on(list, 'pointerdown', () => {
        lastPointerAt = performance.now();
        lastKey = '';
    });
    on(list, 'keydown', (event) => {
        lastKey = event.key;
        lastKeyAt = performance.now();
        lastPointerAt = -Infinity;
        const input = event.target;
        if (!(input instanceof HTMLInputElement) || input.type !== 'radio') return;
        if (event.key === 'Enter') {
            event.preventDefault(); // no implicit form submission: the file is already there
            const item = byRef.get(input.value);
            if (item && select(item.ref)) confirmChoice(item, { enter: true });
        } else if (event.key === ' ' && input.checked && !event.repeat) {
            // Space on the radio that is already chosen fires no click in Chrome: confirm it here.
            const item = byRef.get(input.value);
            if (item && select(item.ref)) confirmChoice(item);
        }
    });
    on(list, 'change', (event) => {
        const input = event.target;
        if (input instanceof HTMLInputElement && input.type === 'radio') select(input.value);
    });
    on(list, 'click', (event) => {
        const input = event.target;
        if (!(input instanceof HTMLInputElement) || input.type !== 'radio') return;
        const now = performance.now();
        const pointer = now - lastPointerAt < 1500;
        const key = ACTIVATION_KEYS.has(lastKey) && now - lastKeyAt < 800;
        if (!pointer && !key) return; // arrow keys: the file follows, focus stays in the list
        const item = byRef.get(input.value);
        if (item && select(item.ref)) confirmChoice(item);
    });
    on(form, 'submit', (event) => {
        event.preventDefault();
        const checked = form.querySelector('input[name="navigator"]:checked');
        const item = checked ? byRef.get(checked.value) : null;
        if (item && select(item.ref)) confirmChoice(item, { enter: true });
    });

    // ------------------------------------------------------------------------------ filter
    function runFilter() {
        const result = applyFilter(items, { tokens: tokenize(search?.value || '', normalise), service });
        for (const item of items) item.el.hidden = !result.visible.has(item.ref);
        for (const [code, group] of groups) group.hidden = (result.groups.get(code) || 0) === 0;
        if (empty) empty.hidden = result.count > 0;
        root.classList.toggle('is-filtered', result.filtered);
        window.clearTimeout(statusTimer);
        statusTimer = window.setTimeout(() => {
            if (!status) return;
            if (!result.filtered) status.textContent = '';
            else status.textContent = result.count === 0 ? labels.noResults || '' : config.matches?.[result.count] || '';
        }, 400);
    }

    function railEdges() {
        if (!rail || !chipsWrap) return;
        const max = rail.scrollWidth - rail.clientWidth;
        const overflow = max > 1;
        const position = Math.abs(rail.scrollLeft); // RTL scrollLeft runs from 0 to negative values
        chipsWrap.classList.toggle('is-overflow', overflow);
        chipsWrap.classList.toggle('at-start', !overflow || position <= 1);
        chipsWrap.classList.toggle('at-end', !overflow || position >= max - 1);
    }

    function revealChip(chip) {
        if (!rail || rail.scrollWidth <= rail.clientWidth + 1) return;
        const edge = 48;
        const r = rail.getBoundingClientRect();
        const c = chip.getBoundingClientRect();
        if (c.left < r.left + edge) rail.scrollBy({ left: c.left - r.left - edge, behavior: scrollBehavior() });
        else if (c.right > r.right - edge) rail.scrollBy({ left: c.right - r.right + edge, behavior: scrollBehavior() });
    }

    function setService(code) {
        service = code;
        for (const chip of chips) {
            const pressed = chip.dataset.navChip === code;
            chip.setAttribute('aria-pressed', pressed ? 'true' : 'false');
            chip.tabIndex = pressed ? 0 : -1;
        }
        runFilter();
    }

    on(search, 'input', runFilter);
    on(toolbar, 'click', (event) => {
        const chip = event.target.closest?.('[data-nav-chip]');
        if (!chip || chip.getAttribute('aria-disabled') === 'true') return;
        setService(chip.dataset.navChip || '');
        revealChip(chip);
    });
    on(toolbar, 'keydown', (event) => {
        const index = chips.indexOf(event.target.closest?.('[data-nav-chip]'));
        if (index < 0) return;
        const next = rovingIndex(event.key, index, chips.length, rtl());
        if (next < 0) return;
        event.preventDefault();
        chips.forEach((chip, i) => {
            chip.tabIndex = i === next ? 0 : -1;
        });
        chips[next].focus();
        revealChip(chips[next]);
    });
    on(rail, 'scroll', railEdges, { passive: true });
    on(root.querySelector('[data-nav-clear]'), 'click', () => {
        if (search) search.value = '';
        setService('');
        (search || chips[0])?.focus();
    });

    // ------------------------------------------------------------------------------ card
    function clearFor(button) {
        const article = button.closest('[data-file]');
        const code = article?.dataset.file;
        if (!code) return;
        ticks = clearTicks(ticks, code);
        write(storageKey, ticks);
        for (const box of article.querySelectorAll('input[data-nav-doc]')) box.checked = false;
        renderProgress(code);
        article.querySelector('input[data-nav-doc]')?.focus(); // the button has just hidden itself
    }

    function request() {
        const detail = current ? { service: current.code, situation: current.ref } : {};
        if (activeSheet() === SHEET) {
            closeSheet({ restore: false });
            afterHistory(() => prefill(detail));
            return;
        }
        prefill(detail);
    }

    function printFile() {
        const html = document.documentElement;
        if (printDate && labels.printDate) {
            let date = '';
            try {
                date = new Intl.DateTimeFormat(config.dateLocale || undefined, { dateStyle: 'long', timeZone: 'Asia/Dubai' }).format(new Date());
            } catch {
                date = '';
            }
            printDate.textContent = date ? labels.printDate.replace(':date', date) : '';
        }
        let done = false;
        const finish = () => {
            if (done) return;
            done = true;
            html.classList.remove('is-printing-navigator');
            window.removeEventListener('afterprint', finish);
        };
        html.classList.add('is-printing-navigator');
        window.addEventListener('afterprint', finish);
        try {
            window.print();
        } finally {
            window.setTimeout(finish, 1500);
        }
    }

    async function copyLink(button) {
        if (!current) return;
        const url = shareUrl(window.location, current.ref);
        let ok = false;
        try {
            if (!window.navigator.clipboard?.writeText) throw new Error('clipboard unavailable');
            await window.navigator.clipboard.writeText(url);
            ok = true;
        } catch {
            ok = legacyCopy(url, button);
        }
        toast(ok ? labels.linkCopied : labels.copyFailed, { tone: ok ? 'success' : 'danger' });
    }

    function runAction(button, event) {
        if (button.getAttribute('aria-disabled') === 'true') return;
        const name = button.dataset.navAction;
        if (name === 'request') {
            event.preventDefault();
            request();
        } else if (name === 'print') {
            printFile();
        } else if (name === 'copy') {
            copyLink(button);
        }
    }

    on(file, 'change', (event) => {
        const box = event.target;
        if (!(box instanceof HTMLInputElement) || !box.matches('[data-nav-doc]')) return;
        const code = box.closest('[data-file]')?.dataset.file;
        if (!code) return;
        ticks = setTick(ticks, code, Number(box.dataset.navDoc), box.checked);
        write(storageKey, ticks);
        renderProgress(code);
    });
    on(file, 'click', (event) => {
        const clear = event.target.closest?.('[data-nav-clear-ticks]');
        if (clear) {
            if (clear.getAttribute('aria-disabled') !== 'true') clearFor(clear);
            return;
        }
        const action = event.target.closest?.('[data-nav-action]');
        if (action) runAction(action, event);
    });
    on(sheetActions, 'click', (event) => {
        const action = event.target.closest?.('[data-nav-action]');
        if (action) runAction(action, event);
    });

    // ------------------------------------------------------------------------------ window
    on(window, 'hashchange', () => {
        const ref = refFromHash(window.location.hash);
        if (!ref || ref === current?.ref || !byRef.has(ref)) return;
        select(ref, { source: 'hash' });
        reveal({ open: true });
    });
    on(window, 'storage', (event) => {
        if (event.key !== storageKey) return;
        ticks = readTicks(read(storageKey, {}));
        applyTicks();
    });
    const onMedia = () => {
        if (desktop.matches) {
            if (activeSheet() === SHEET) closeSheet();
            placeInline();
        }
        sticky.measure();
        railEdges();
    };
    desktop.addEventListener('change', onMedia);
    cleanups.push(() => desktop.removeEventListener('change', onMedia));
    if ('ResizeObserver' in window && rail) {
        const observer = new ResizeObserver(() => railEdges());
        observer.observe(rail);
        if (toolbar) observer.observe(toolbar);
        cleanups.push(() => observer.disconnect());
    }

    // ------------------------------------------------------------------------------ start
    applyTicks();
    if (search?.value) runFilter(); // typed before the controller arrived
    const fromHash = refFromHash(window.location.hash);
    const checked = form.querySelector('input[name="navigator"]:checked');
    const initial = fromHash && byRef.has(fromHash) ? fromHash : checked && byRef.has(checked.value) ? checked.value : null;
    if (initial) {
        select(initial, { source: 'init' });
        reveal({ open: true });
    }
    if (desktop.matches) placeInline();
    root.classList.add('is-ready');
    railEdges();

    return {
        destroy() {
            window.clearTimeout(statusTimer);
            cleanups.splice(0).forEach((fn) => fn());
            root.classList.remove('is-ready');
        },
    };
}
