/**
 * The calendar of the Design 2.1 date field (D36). Lazy chunk: components/date.js imports it on the first open.
 *
 * A dialog (role="dialog", aria-modal, data-overlay) cloned from the field's server-rendered <template> (every
 * label and icon comes from the server), shown in the top layer (Popover API, so no card, sticky column or
 * overflow can clip it; an absolutely placed panel where the API is missing), under the field or above it when
 * the window has no room below, kept inside the window and following the field when the page scrolls.
 *
 * The month grid follows the WAI-ARIA date picker dialog pattern: role="grid", one focusable day (roving
 * tabindex), aria-selected on the chosen day, aria-current="date" on today, aria-disabled outside the field's
 * min / max. Keys: arrows by day and week (left and right swap in RTL), Home / End the first and last day of
 * the week, Page Up / Page Down the month (Shift: the year), Enter or Space picks, Escape closes; Tab cycles
 * through the dialog's controls (previous month, month, year, next month, the grid). Picking fills the field
 * and returns focus to the calendar button, like Escape. A press outside closes it without moving focus.
 * Month and weekday names come from Intl in the field's language; digits stay Western (brief §3.7). The week
 * starts on the locale's first day (Monday for en-AE, ar-AE and ru-RU). Motion: opacity and a 4 px drop on open
 * (CSS); none under reduced motion.
 */
import { addDays, addMonths, baseLang, clampDate, inRange, isoOf, MAX_YEAR, MIN_YEAR, monthCells, partsOf, todayIn, weekBounds } from './parse.js';

const LOCALES = { en: 'en-AE', ar: 'ar-AE', ru: 'ru-RU' };
let counter = 0;

const intlLocale = (lang) => LOCALES[baseLang(lang)];

function firstDayOf(locale) {
    try {
        const info = new Intl.Locale(locale);
        const week = typeof info.getWeekInfo === 'function' ? info.getWeekInfo() : info.weekInfo;
        if (week && Number.isInteger(week.firstDay)) return week.firstDay;
    } catch {
        /* older engines: Monday */
    }
    return 1;
}

const utc = (iso) => {
    const p = partsOf(iso);
    return new Date(Date.UTC(p.year, p.month - 1, p.day));
};

/** The limits of a field: its min / max attributes, inside 1900-2100. */
function limits(input) {
    const min = partsOf(input.getAttribute('min')) ? input.getAttribute('min') : `${MIN_YEAR}-01-01`;
    const max = partsOf(input.getAttribute('max')) ? input.getAttribute('max') : `${MAX_YEAR}-12-31`;
    return { min, max };
}

function build(api) {
    const template = api.root.querySelector('template[data-date-template]');
    const node = template?.content.firstElementChild?.cloneNode(true);
    if (!node) return null;

    const locale = intlLocale(api.lang);
    const id = `datepicker-${++counter}`;
    const q = (selector) => node.querySelector(selector);
    const ui = {
        node,
        title: q('[data-cal-title]'),
        month: q('[data-cal-month]'),
        year: q('[data-cal-year]'),
        prev: q('[data-cal-step="-1"]'),
        next: q('[data-cal-step="1"]'),
        grid: q('.datepicker__grid'),
        body: q('[data-cal-days]'),
        cells: [],
        locale,
        firstDay: firstDayOf(locale),
        popover: typeof node.showPopover === 'function',
        format: {
            title: new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric', timeZone: 'UTC', calendar: 'gregory', numberingSystem: 'latn' }),
            month: new Intl.DateTimeFormat(locale, { month: 'long', timeZone: 'UTC', calendar: 'gregory' }),
            day: new Intl.DateTimeFormat(locale, { dateStyle: 'full', timeZone: 'UTC', calendar: 'gregory', numberingSystem: 'latn' }),
            weekday: new Intl.DateTimeFormat(locale, { weekday: baseLang(api.lang) === 'ar' ? 'narrow' : 'short', timeZone: 'UTC' }),
            weekdayLong: new Intl.DateTimeFormat(locale, { weekday: 'long', timeZone: 'UTC' }),
        },
    };

    node.id = id;
    node.hidden = true;
    // A drag on the calendar is not a swipe between the calculators (tools/swipe.js).
    node.setAttribute('data-swipe-ignore', '');
    ui.title.id = `${id}-title`;
    node.setAttribute('aria-labelledby', ui.title.id);
    ui.grid.setAttribute('aria-labelledby', ui.title.id);
    if (ui.popover) node.setAttribute('popover', 'manual');
    else node.classList.add('datepicker--inline');
    api.button?.setAttribute('aria-controls', id);

    // Month and year selects.
    for (let m = 1; m <= 12; m++) {
        const option = document.createElement('option');
        option.value = String(m);
        option.textContent = ui.format.month.format(Date.UTC(2026, m - 1, 1));
        ui.month.append(option);
    }
    const { min, max } = limits(api.input);
    for (let y = partsOf(min).year; y <= partsOf(max).year; y++) {
        const option = document.createElement('option');
        option.value = String(y);
        option.textContent = String(y);
        ui.year.append(option);
    }

    // Weekday header: short names (narrow in Arabic), the full name for assistive technology.
    const head = q('[data-cal-weekdays]');
    // 2026-11-02 is a Monday: day d (1 Monday … 7 Sunday) is 2026-11-(1 + d).
    for (let i = 0; i < 7; i++) {
        const day = ((ui.firstDay - 1 + i) % 7) + 1;
        const date = Date.UTC(2026, 10, 1 + day);
        const th = document.createElement('th');
        th.scope = 'col';
        th.abbr = ui.format.weekdayLong.format(date);
        th.textContent = ui.format.weekday.format(date);
        head.append(th);
    }

    // Six weeks of cells, filled per month.
    for (let row = 0; row < 6; row++) {
        const tr = document.createElement('tr');
        for (let col = 0; col < 7; col++) {
            const td = document.createElement('td');
            td.className = 'datepicker__day';
            tr.append(td);
            ui.cells.push(td);
        }
        ui.body.append(tr);
    }

    api.root.append(node);
    return ui;
}

/** One calendar per field, built on its first open. */
export function open(api) {
    const state = (api.root.__calendar ??= { ui: build(api), open: false });
    const { ui } = state;
    if (!ui || state.open) return;

    const input = api.input;
    const rtl = () => getComputedStyle(api.root).direction === 'rtl';
    const { min, max } = limits(input);
    const today = todayIn(new Date());
    let selected = api.iso() || null;
    let focus = clampDate(selected || today, min, max);
    let view = partsOf(focus);

    const render = () => {
        const title = ui.format.title.format(Date.UTC(view.year, view.month - 1, 1));
        if (ui.title.textContent !== title) ui.title.textContent = title; // announced (aria-live) only when it changes
        ui.month.value = String(view.month);
        ui.year.value = String(view.year);
        const first = isoOf(view.year, view.month, 1);
        const last = addDays(addMonths(first, 1), -1);
        ui.prev.disabled = first <= min;
        ui.next.disabled = last >= max;
        // Months outside the limits of the year select cannot be chosen.
        for (const option of ui.month.options) {
            const m = Number(option.value);
            const start = isoOf(view.year, m, 1);
            const end = addDays(addMonths(start, 1), -1);
            option.disabled = end < min || start > max;
        }
        const cells = monthCells(view.year, view.month, ui.firstDay);
        ui.cells.forEach((td, i) => {
            const iso = cells[i];
            if (!iso) {
                td.textContent = '';
                for (const name of ['tabindex', 'data-iso', 'aria-label', 'aria-selected', 'aria-current', 'aria-disabled']) td.removeAttribute(name);
                td.classList.add('is-empty');
                return;
            }
            td.classList.remove('is-empty');
            td.dataset.iso = iso;
            td.textContent = String(partsOf(iso).day);
            td.setAttribute('aria-label', ui.format.day.format(utc(iso)));
            td.tabIndex = iso === focus ? 0 : -1;
            if (iso === selected) td.setAttribute('aria-selected', 'true');
            else td.removeAttribute('aria-selected');
            if (iso === today) td.setAttribute('aria-current', 'date');
            else td.removeAttribute('aria-current');
            if (inRange(iso, min, max)) td.removeAttribute('aria-disabled');
            else td.setAttribute('aria-disabled', 'true');
        });
    };

    const cellOf = (iso) => ui.cells.find((td) => td.dataset.iso === iso) ?? null;

    /** Move the focused day (another month re-renders), optionally moving keyboard focus to it. */
    const moveTo = (iso, { focusCell = true } = {}) => {
        if (!iso) return;
        focus = clampDate(iso, min, max);
        const p = partsOf(focus);
        if (p.year !== view.year || p.month !== view.month) view = { year: p.year, month: p.month };
        render();
        if (focusCell) cellOf(focus)?.focus({ preventScroll: true });
    };

    /** Show another month from the header (the focus stays on the control that was used). */
    const showMonth = (year, month) => {
        const day = Math.min(partsOf(focus).day, 28);
        const target = isoOf(year, month, day) ?? isoOf(year, month, 1);
        if (!target) return;
        focus = clampDate(target, min, max);
        view = partsOf(focus);
        view = { year: view.year, month: view.month };
        render();
    };

    const place = () => {
        if (!state.open) return;
        const anchor = api.root.querySelector('.control') ?? api.root;
        const r = anchor.getBoundingClientRect();
        const vw = document.documentElement.clientWidth;
        const vh = window.innerHeight;
        const w = ui.node.offsetWidth;
        const h = ui.node.offsetHeight;
        const gap = 8;
        const edge = 8;
        let left = rtl() ? r.right - w : r.left;
        left = Math.max(edge, Math.min(left, vw - w - edge));
        let top = r.bottom + gap;
        if (top + h > vh - edge && r.top - gap - h >= edge) top = r.top - gap - h;
        if (ui.popover) {
            ui.node.style.setProperty('left', `${Math.round(left)}px`);
            ui.node.style.setProperty('top', `${Math.round(top)}px`);
        } else {
            const box = api.root.getBoundingClientRect();
            ui.node.style.setProperty('left', `${Math.round(left - box.left)}px`);
            ui.node.style.setProperty('top', `${Math.round(top - box.top)}px`);
        }
    };
    let frame = 0;
    const schedulePlace = () => {
        if (frame) return;
        frame = window.requestAnimationFrame(() => {
            frame = 0;
            place();
        });
    };

    const focusables = () => [ui.prev, ui.month, ui.year, ui.next, cellOf(focus)].filter((el) => el && !el.disabled);

    const close = (returnFocus) => {
        if (!state.open) return;
        state.open = false;
        document.removeEventListener('pointerdown', onOutside, true);
        window.removeEventListener('resize', schedulePlace);
        window.removeEventListener('scroll', schedulePlace, true);
        ui.node.removeEventListener('keydown', onKey);
        ui.node.removeEventListener('click', onClick);
        ui.node.removeEventListener('change', onSelect);
        ui.node.removeEventListener('input', onInput);
        ui.node.removeEventListener('focusout', onFocusOut);
        if (ui.popover && ui.node.matches(':popover-open')) ui.node.hidePopover();
        ui.node.hidden = true;
        api.button?.setAttribute('aria-expanded', 'false');
        api.root.classList.remove('is-open');
        if (returnFocus) (api.button ?? input).focus();
    };

    const pick = (iso) => {
        if (!iso || !inRange(iso, min, max)) return;
        selected = iso;
        api.pick(iso);
        close(true);
    };

    function onOutside(event) {
        const target = event.target;
        if (!(target instanceof Node) || ui.node.contains(target) || api.button?.contains(target)) return;
        close(false);
    }

    function onFocusOut(event) {
        const next = event.relatedTarget;
        if (next instanceof Node && !ui.node.contains(next) && next !== api.button) close(false);
    }

    function onClick(event) {
        const target = event.target instanceof Element ? event.target : null;
        const step = target?.closest('[data-cal-step]');
        if (step && !step.disabled) {
            const to = addMonths(isoOf(view.year, view.month, 1), Number(step.dataset.calStep));
            const p = partsOf(to);
            showMonth(p.year, p.month);
            return;
        }
        const cell = target?.closest('td[data-iso]');
        if (cell && cell.getAttribute('aria-disabled') !== 'true') pick(cell.dataset.iso);
    }

    function onSelect(event) {
        // The calendar's own selects are not fields of the form or the calculator around it.
        event.stopPropagation();
        if (event.target === ui.month || event.target === ui.year) showMonth(Number(ui.year.value), Number(ui.month.value));
    }

    function onInput(event) {
        event.stopPropagation();
    }

    function onKey(event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            close(true);
            return;
        }
        if (event.key === 'Tab') {
            const list = focusables();
            const index = list.indexOf(document.activeElement);
            event.preventDefault();
            const next = index === -1 ? 0 : (index + (event.shiftKey ? -1 : 1) + list.length) % list.length;
            list[next]?.focus({ preventScroll: true });
            return;
        }
        const cell = event.target instanceof Element ? event.target.closest('td[data-iso]') : null;
        if (!cell || event.altKey || event.ctrlKey || event.metaKey) return;
        const iso = cell.dataset.iso;
        const back = rtl() ? 1 : -1;
        const week = weekBounds(iso, ui.firstDay);
        let to = null;
        switch (event.key) {
            case 'ArrowLeft':
                to = addDays(iso, back);
                break;
            case 'ArrowRight':
                to = addDays(iso, -back);
                break;
            case 'ArrowUp':
                to = addDays(iso, -7);
                break;
            case 'ArrowDown':
                to = addDays(iso, 7);
                break;
            case 'Home':
                to = week.start;
                break;
            case 'End':
                to = week.end;
                break;
            case 'PageUp':
                to = addMonths(iso, event.shiftKey ? -12 : -1);
                break;
            case 'PageDown':
                to = addMonths(iso, event.shiftKey ? 12 : 1);
                break;
            case 'Enter':
            case ' ':
                event.preventDefault();
                if (cell.getAttribute('aria-disabled') !== 'true') pick(iso);
                return;
            default:
                return;
        }
        event.preventDefault();
        if (to) moveTo(to);
    }

    render();
    state.open = true;
    ui.node.hidden = false;
    if (ui.popover) ui.node.showPopover();
    api.root.classList.add('is-open');
    api.button?.setAttribute('aria-expanded', 'true');
    place();
    cellOf(focus)?.focus({ preventScroll: true });

    document.addEventListener('pointerdown', onOutside, true);
    window.addEventListener('resize', schedulePlace);
    window.addEventListener('scroll', schedulePlace, true);
    ui.node.addEventListener('keydown', onKey);
    ui.node.addEventListener('click', onClick);
    ui.node.addEventListener('change', onSelect);
    ui.node.addEventListener('input', onInput);
    ui.node.addEventListener('focusout', onFocusOut);
}
