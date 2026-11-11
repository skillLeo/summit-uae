/**
 * Live office-hours status (Design 2.1 brief §3.7; fixes D02, D11, D40; docs/d2/engineering.md §8, shared
 * contract E).
 *
 * Fills every [data-hours-status] on the page with a status dot and one line in the locale's approved time style,
 * and, for a visitor outside UAE time, the time difference on a line of its own:
 *   EN  "Open now, closes at 6 pm" · "Closed now, opens today at 9 am" / "… tomorrow at 9 am" / "… Monday at 9 am"
 *       "UAE time is 1 hour behind yours" / "UAE time is 4 hours ahead of yours"
 *   RU  "Сейчас открыто, закрывается в 18:00" (24-hour, like the approved "с 9:00 до 18:00")
 *   AR  "مفتوح الآن، يُغلق الساعة 6 مساءً" (like the approved homepage line "من 9 صباحًا إلى 6 مساءً")
 * No middle dots, no response-time promise. "today" and "tomorrow" are UAE days and are used only while the
 * visitor's own calendar day is the UAE's: when the dates differ (a visitor in New York on Monday evening, already
 * Tuesday morning in Dubai) the weekday is named instead, so the line never means a different day to the reader.
 *
 * Put the empty slot beside an approved hours sentence, which always stays plain text, so nothing depends on
 * JavaScript:  <span data-hours-status></span>  (data-hours-status="short" leaves out the time difference).
 * Each place prints the sentence of the Word file that defines it (App\Design\D2\Hours::sentence(), with each time
 * bound to its am/pm by a no-break space). A slot inside a block marked with another language than the page's is
 * left empty (inLanguage(), leave()): the copy is the page language's, and a card never mixes two languages.
 *
 * Office hours and copy come from the JSON block #d2-hours-copy (App\Design\D2\Hours::copy(), printed once per
 * page by <x-d2.shell.palette>; lang/{locale}/proposed-d2/hours.php). Numbers follow the Arabic digits setting
 * of the page (<html data-digits>, lib/digits.js). The time is computed in the browser with Intl.DateTimeFormat
 * in Asia/Dubai: nothing is requested, stored or sent. The status is refreshed at the start of every minute while
 * the page is visible; it never moves or pulses.
 *
 * zonedClock(), hoursStatus(), formatTime(), describe() and inLanguage() are pure (Vitest:
 * modules/__tests__/hours.test.js).
 */
import { fill, plural, readJson } from '../palette/copy.js';
import { digitsMode, numberLocale } from '../lib/digits.js';

const WEEKDAYS = { Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6, Sun: 7 };
const clocks = new Map();
const numbers = new Map();

function clockFormat(timeZone) {
    let fmt = clocks.get(timeZone);
    if (!fmt) {
        fmt = new Intl.DateTimeFormat('en-US', {
            timeZone,
            hourCycle: 'h23',
            weekday: 'short',
            year: 'numeric',
            month: 'numeric',
            day: 'numeric',
            hour: 'numeric',
            minute: 'numeric',
            second: 'numeric',
        });
        clocks.set(timeZone, fmt);
    }
    return fmt;
}

/**
 * Wall clock of an instant in an IANA time zone: ISO weekday (1 = Monday … 7 = Sunday), minutes since
 * midnight and the zone's offset from UTC in minutes (east positive) at that instant.
 */
export function zonedClock(epochMs, timeZone) {
    const parts = {};
    for (const part of clockFormat(timeZone).formatToParts(new Date(epochMs))) parts[part.type] = part.value;
    const hour = Number(parts.hour) % 24;
    const minute = Number(parts.minute);
    const wall = Date.UTC(Number(parts.year), Number(parts.month) - 1, Number(parts.day), hour, minute, Number(parts.second));
    const offset = Math.round((wall - Math.floor(epochMs / 1000) * 1000) / 60000);
    return { weekday: WEEKDAYS[parts.weekday], minutes: hour * 60 + minute, offset };
}

const toMinutes = (hhmm) => {
    const [h, m] = String(hhmm).split(':').map(Number);
    return (h || 0) * 60 + (m || 0);
};

/** ISO weekday (1 = Monday … 7 = Sunday) of a Date's UTC fields. */
const isoWeekday = (date) => ((date.getUTCDay() + 6) % 7) + 1;

/**
 * Pure status for an instant (epoch ms).
 * options: timeZone ('Asia/Dubai'), days ([1…5], ISO weekdays), open ('09:00'), close ('18:00'), and the
 * visitor's clock: visitorTimeZone (IANA, wins) or visitorOffset (minutes east of UTC); default: this device.
 * Returns { open, closes, opens, opensDay, opensIn, sameDay, diff }:
 *   opensDay  ISO weekday (UAE) of the next opening, null while open
 *   opensIn   UAE calendar days until that opening (0 = later today, 1 = tomorrow …), null while open
 *   sameDay   the visitor's calendar day is the UAE's at this instant (time zones are never a week apart,
 *             so equal weekdays mean equal dates)
 *   diff      UAE offset − visitor offset in minutes (positive: UAE time is ahead of the visitor's)
 */
export function hoursStatus(now, options = {}) {
    const { timeZone = 'Asia/Dubai', days = [1, 2, 3, 4, 5], open = '09:00', close = '18:00' } = options;
    const clock = zonedClock(now, timeZone);
    const start = toMinutes(open);
    const end = toMinutes(close);
    const workday = (d) => days.includes(d);
    const isOpen = workday(clock.weekday) && clock.minutes >= start && clock.minutes < end;

    let opensDay = null;
    let opensIn = null;
    if (!isOpen) {
        if (workday(clock.weekday) && clock.minutes < start) {
            opensDay = clock.weekday;
            opensIn = 0;
        } else {
            for (let i = 1; i <= 7; i += 1) {
                const day = ((clock.weekday - 1 + i) % 7) + 1;
                if (workday(day)) {
                    opensDay = day;
                    opensIn = i;
                    break;
                }
            }
        }
    }

    let visitor;
    let visitorDay;
    if (options.visitorTimeZone) {
        const own = zonedClock(now, options.visitorTimeZone);
        visitor = own.offset;
        visitorDay = own.weekday;
    } else {
        visitor = typeof options.visitorOffset === 'number' ? options.visitorOffset : -new Date(now).getTimezoneOffset();
        visitorDay = isoWeekday(new Date(now + visitor * 60000));
    }

    return { open: isOpen, closes: close, opens: open, opensDay, opensIn, sameDay: visitorDay === clock.weekday, diff: clock.offset - visitor };
}

/** A number in the page's digits (lib/digits.js): Intl.NumberFormat with the numbering system set. */
function formatNumber(n, locale, digits, options) {
    const tag = numberLocale(locale, digits);
    const key = `${tag}|${options.minimumIntegerDigits ?? ''}|${options.maximumFractionDigits ?? ''}`;
    let fmt = numbers.get(key);
    if (fmt === undefined) {
        try {
            fmt = new Intl.NumberFormat(tag, { useGrouping: false, ...options });
        } catch {
            fmt = null;
        }
        numbers.set(key, fmt);
    }
    return fmt ? fmt.format(n) : String(n);
}

/**
 * A time of day ('18:00') written with the copy's `time` forms: "at 6 pm", "в 18:00", "الساعة 6 مساءً".
 * patterns: { am, pm, amMinutes, pmMinutes } with :hour (12-hour clock), :hour_of_day (24-hour) and :minute.
 */
export function formatTime(hhmm, patterns = {}, { locale = 'en', digits = 'western' } = {}) {
    const [h = 0, m = 0] = String(hhmm)
        .split(':')
        .map((part) => Number.parseInt(part, 10) || 0);
    const hour = Math.min(23, Math.max(0, h));
    const minute = Math.min(59, Math.max(0, m));
    const half = hour >= 12 ? 'pm' : 'am';
    const template = (minute === 0 ? patterns[half] : patterns[`${half}Minutes`]) || ':hour_of_day::minute';
    const num = (n, options = {}) => formatNumber(n, locale, digits, options);
    return fill(template, {
        hour: num(hour % 12 === 0 ? 12 : hour % 12),
        hour_of_day: num(hour),
        minute: num(minute, { minimumIntegerDigits: 2 }),
    });
}

/**
 * The status as plain strings, from the copy block's `text` (hours.php): { open, text, offset }, where offset is
 * '' for a visitor on UAE time. options: { locale, digits ('western' | 'eastern') }.
 */
export function describe(status, copy, { locale = 'en', digits = 'western' } = {}) {
    const time = (hhmm) => formatTime(hhmm, copy.time, { locale, digits });
    let text;
    if (status.open) {
        text = fill(copy.open, { time: time(status.closes) });
    } else {
        let relative = null;
        if (status.sameDay && status.opensIn === 0) relative = copy.closedToday;
        else if (status.sameDay && status.opensIn === 1) relative = copy.closedTomorrow;
        const day = copy.days?.[status.opensDay] ?? copy.days?.[String(status.opensDay)] ?? '';
        text = relative ? fill(relative, { time: time(status.opens) }) : fill(copy.closed, { day, time: time(status.opens) });
    }

    let offset = '';
    if (status.diff !== 0) {
        const hours = Math.abs(status.diff) / 60;
        offset = fill(plural(status.diff > 0 ? copy.ahead : copy.behind, hours, locale), {
            count: formatNumber(hours, locale, digits, { maximumFractionDigits: 2 }),
        });
    }
    return { open: status.open, text, offset };
}

/** Writes a status into its slot (dot, text, and the time difference unless data-hours-status="short"). */
export function paint(el, view, { markProposed = false } = {}) {
    const offset = el.dataset.hoursStatus === 'short' ? '' : view.offset;
    const key = `${view.open ? 1 : 0}|${view.text}|${offset}`;
    if (el.dataset.hoursKey === key) return;
    el.dataset.hoursKey = key;

    el.classList.add('hours-status');
    el.classList.toggle('is-open', view.open);
    el.classList.toggle('is-closed', !view.open);

    const doc = el.ownerDocument;
    const dot = doc.createElement('span');
    dot.className = 'hours-status__dot';
    dot.setAttribute('aria-hidden', 'true');
    const text = doc.createElement('span');
    text.className = 'hours-status__text';
    text.textContent = view.text;
    const children = [dot, text];
    if (offset) {
        const line = doc.createElement('span');
        line.className = 'hours-status__offset';
        line.textContent = offset;
        children.push(line);
    }
    el.replaceChildren(...children);
    // The status copy is proposed: mark it as @proposed does on the server (outside production only).
    if (markProposed) el.setAttribute('data-copy-status', 'proposed');
}

const primary = (tag) => String(tag || '').trim().toLowerCase().split('-')[0];

/**
 * Whether a slot reads in the copy's language. The copy block is the page's language, so a slot inside a block
 * marked with another one (the styleguide's specimens in the other language and direction) is not written: a card
 * never mixes two languages ("Open now, closes at 6 pm" under an Arabic hours sentence).
 */
export function inLanguage(el, locale) {
    const host = typeof el.closest === 'function' ? el.closest('[lang]:not([lang=""])') : null;
    return !host || primary(host.getAttribute('lang')) === primary(locale);
}

/**
 * A slot the module does not write stays empty, so hidden ([data-hours-status]:empty); the invisible reserve copy
 * beside it (an aria-hidden .hours-status in the same parent: the contact card's status sizer) is hidden too, so no
 * empty line is held for it.
 */
export function leave(el) {
    for (const sibling of el.parentElement?.children ?? []) {
        if (sibling !== el && sibling.classList?.contains('hours-status') && sibling.getAttribute?.('aria-hidden') === 'true') {
            sibling.hidden = true;
        }
    }
}

export default function hours() {
    const data = readJson('d2-hours-copy');
    if (!data || data.enabled === false || !data.text) return;
    const locale = data.locale || document.documentElement.lang || 'en';
    const digits = digitsMode();

    const tick = () => {
        const els = [];
        for (const el of document.querySelectorAll('[data-hours-status]')) {
            if (inLanguage(el, locale)) els.push(el);
            else leave(el);
        }
        if (!els.length) return;
        let view;
        try {
            view = describe(hoursStatus(Date.now(), data.hours || {}), data.text, { locale, digits });
        } catch (error) {
            // An engine without the time zone data: leave the approved sentence alone.
            console.error(error);
            return;
        }
        els.forEach((el) => paint(el, view, { markProposed: data.proposed === true }));
    };

    let timer = 0;
    const schedule = () => {
        window.clearTimeout(timer);
        if (document.hidden) return;
        timer = window.setTimeout(() => {
            tick();
            schedule();
        }, 60000 - (Date.now() % 60000) + 50);
    };

    tick();
    schedule();
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) tick();
        schedule();
    });
    window.addEventListener('pageshow', (event) => {
        if (!event.persisted) return;
        tick();
        schedule();
    });
}
