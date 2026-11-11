/**
 * Calendar dates for the Design 2 date field (<x-d2.ui.date>, components/date.js, date/calendar.js). Pure: no DOM,
 * no clock (today is always passed in).
 *
 * Values are ISO strings (YYYY-MM-DD, Gregorian, counted as whole UTC days), exactly what <input type="date">
 * gives, so every consumer (the calculators, the consultation wizard, the server) keeps its contract.
 *
 * Typing (D36: "still accepts typed dates"): the visitor types day, month and year in the order of the page
 * language (all three languages write the day first) with any separator ("1/3/2018", "01.03.2018", "1-3-18",
 * "1 3 2018"), eight digits ("01032018"), or an ISO date ("2018-03-01"). Arabic-Indic and Persian digits are
 * read as 0-9. A two-digit year is 2000-2068 for 00-68 and 1969-1999 for 69-99 (the POSIX %y rule). Anything
 * else, an impossible date (31/02/2026) or a year outside 1900-2100 is not a date (the field reports badInput,
 * like the native date input).
 *
 * Display: the canonical short form of the language: "01/03/2018" (English, Arabic), "01.03.2018" (Russian),
 * Western digits (brief §3.7: the Arabic digits setting is Western by default).
 */
export const MIN_YEAR = 1900;
export const MAX_YEAR = 2100;
const DAY_MS = 86400000;

/** Per language: the separator of the short form (day first in every language). */
const SEPARATORS = { en: '/', ar: '/', ru: '.' };

/** The separator of the language's short form: "/" (English, Arabic), "." (Russian). */
export const separatorOf = (lang) => SEPARATORS[baseLang(lang)];

/** The page language's base code ('en', 'ar', 'ru'), English for anything else. */
export function baseLang(lang) {
    const base = String(lang || 'en').toLowerCase().split(/[-_]/)[0];
    return base in SEPARATORS ? base : 'en';
}

const pad = (n, size = 2) => String(n).padStart(size, '0');

/** ISO string for a year, month (1-12) and day, or null when that day does not exist or is out of range. */
export function isoOf(year, month, day) {
    if (![year, month, day].every(Number.isInteger)) return null;
    if (year < MIN_YEAR || year > MAX_YEAR || month < 1 || month > 12 || day < 1) return null;
    const date = new Date(Date.UTC(year, month - 1, day));
    if (date.getUTCFullYear() !== year || date.getUTCMonth() !== month - 1 || date.getUTCDate() !== day) return null;
    return `${pad(year, 4)}-${pad(month)}-${pad(day)}`;
}

/** { year, month, day } of a valid ISO date, or null. */
export function partsOf(iso) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(iso ?? '').trim());
    if (!match) return null;
    const [year, month, day] = [Number(match[1]), Number(match[2]), Number(match[3])];
    return isoOf(year, month, day) ? { year, month, day } : null;
}

/** True for a real calendar date between MIN_YEAR and MAX_YEAR. */
export const isIso = (iso) => partsOf(iso) !== null;

/** Arabic-Indic (٠-٩) and Persian (۰-۹) digits as 0-9; bidi marks removed. */
export function latinDigits(text) {
    return String(text ?? '')
        .replace(/[‎‏؜‪-‮⁦-⁩]/g, '')
        .replace(/[٠-٩]/g, (d) => String(d.charCodeAt(0) - 0x0660))
        .replace(/[۰-۹]/g, (d) => String(d.charCodeAt(0) - 0x06f0));
}

const fullYear = (token) => {
    if (token.length === 4) return Number(token);
    if (token.length === 2) {
        const yy = Number(token);
        return yy <= 68 ? 2000 + yy : 1900 + yy;
    }
    return NaN;
};

/**
 * A typed date as ISO, or null when the text is not a complete, real date. '' (nothing typed) is null too:
 * callers tell "empty" from "not a date" by the text itself.
 */
export function parseDate(text) {
    const s = latinDigits(text).trim();
    if (s === '') return null;
    // Eight digits in a row: DDMMYYYY.
    if (/^\d{8}$/.test(s)) return isoOf(Number(s.slice(4)), Number(s.slice(2, 4)), Number(s.slice(0, 2)));
    const tokens = s.split(/[^\d]+/).filter(Boolean);
    if (tokens.length !== 3 || /[^\d\s./\-،,\\]/.test(s)) return null;
    // A four-digit first part is an ISO-style year-month-day.
    if (tokens[0].length === 4) {
        if (tokens[1].length > 2 || tokens[2].length > 2) return null;
        return isoOf(Number(tokens[0]), Number(tokens[1]), Number(tokens[2]));
    }
    const [d, m, y] = tokens;
    if (d.length > 2 || m.length > 2) return null;
    return isoOf(fullYear(y), Number(m), Number(d));
}

/** The short form of an ISO date in the page language: "01/03/2018", "01.03.2018". '' when invalid. */
export function formatDate(iso, lang) {
    const p = partsOf(iso);
    if (!p) return '';
    const sep = separatorOf(lang);
    return `${pad(p.day)}${sep}${pad(p.month)}${sep}${pad(p.year, 4)}`;
}

/** Days since 1970-01-01 of a valid ISO date. */
const dayNumber = (iso) => {
    const p = partsOf(iso);
    return p ? Math.round(Date.UTC(p.year, p.month - 1, p.day) / DAY_MS) : null;
};

const fromDayNumber = (n) => {
    const d = new Date(n * DAY_MS);
    return `${pad(d.getUTCFullYear(), 4)}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())}`;
};

/** The date `days` later (earlier when negative). */
export function addDays(iso, days) {
    const n = dayNumber(iso);
    return n === null ? null : fromDayNumber(n + days);
}

/** Number of days in a month (1-12). */
export const daysInMonth = (year, month) => new Date(Date.UTC(year, month, 0)).getUTCDate();

/** The same day `months` later (earlier when negative), on the month's last day when it has fewer days. */
export function addMonths(iso, months) {
    const p = partsOf(iso);
    if (!p) return null;
    const index = p.year * 12 + (p.month - 1) + months;
    const year = Math.floor(index / 12);
    const month = (index % 12) + 1;
    return `${pad(year, 4)}-${pad(month)}-${pad(Math.min(p.day, daysInMonth(year, month)))}`;
}

/** ISO weekday: 1 Monday … 7 Sunday. */
export function weekday(iso) {
    const p = partsOf(iso);
    if (!p) return null;
    return new Date(Date.UTC(p.year, p.month - 1, p.day)).getUTCDay() || 7;
}

/** The first and last day of the week that holds `iso`, the week starting on `firstDay` (1-7). */
export function weekBounds(iso, firstDay = 1) {
    const day = weekday(iso);
    if (day === null) return null;
    const back = (day - firstDay + 7) % 7;
    const start = addDays(iso, -back);
    return { start, end: addDays(start, 6) };
}

/** `iso` kept inside [min, max] (either bound may be null). */
export function clampDate(iso, min, max) {
    if (min && iso < min) return min;
    if (max && iso > max) return max;
    return iso;
}

/** Whether `iso` lies inside [min, max]. */
export const inRange = (iso, min, max) => !(min && iso < min) && !(max && iso > max);

/**
 * The 42 cells (six weeks) of a month view, the week starting on `firstDay` (1 Monday … 7 Sunday): ISO dates
 * for the days of the month, null for the cells before and after it. Always six rows, so the calendar keeps
 * its height from one month to the next.
 */
export function monthCells(year, month, firstDay = 1) {
    const first = isoOf(year, month, 1);
    if (!first) return [];
    const offset = (weekday(first) - firstDay + 7) % 7;
    const total = daysInMonth(year, month);
    return Array.from({ length: 42 }, (_, i) => {
        const day = i - offset + 1;
        return day >= 1 && day <= total ? isoOf(year, month, day) : null;
    });
}

/** Today's ISO date on the visitor's clock (or in `timeZone`), for the calendar's "today" mark. */
export function todayIn(now, timeZone = undefined) {
    try {
        const parts = new Intl.DateTimeFormat('en-CA', { timeZone, year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(now);
        const get = (type) => parts.find((part) => part.type === type)?.value;
        return `${get('year')}-${get('month')}-${get('day')}`;
    } catch {
        return fromDayNumber(Math.floor(now.getTime() / DAY_MS));
    }
}
