/**
 * Calendar dates for the home tools (gratuity estimator, rent increase check). Pure: no DOM, no clock.
 *
 * Dates are ISO strings (YYYY-MM-DD), the value of <input type="date"> in every language (the visible
 * format is the browser's; the value is always Gregorian ISO). They are counted as whole UTC days, so a
 * daylight-saving change on the visitor's computer can never add or lose a day.
 */
const ISO = /^(\d{4})-(\d{2})-(\d{2})$/;
const DAY_MS = 86400000;

/** Plausible years: a browser date field passes through 0002, 0020, 0202 while the year is typed. */
export const MIN_YEAR = 1900;
export const MAX_YEAR = 2100;

/**
 * Days since 1970-01-01 for a real calendar date, or null (empty, malformed, 2026-02-30, out of range).
 * @param {unknown} iso
 * @returns {number|null}
 */
export function dayNumber(iso) {
    if (typeof iso !== 'string') return null;
    const match = ISO.exec(iso.trim());
    if (!match) return null;
    const year = Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);
    if (year < MIN_YEAR || year > MAX_YEAR || month < 1 || month > 12 || day < 1) return null;
    const time = Date.UTC(year, month - 1, day);
    const check = new Date(time);
    if (check.getUTCFullYear() !== year || check.getUTCMonth() !== month - 1 || check.getUTCDate() !== day) return null;
    return Math.round(time / DAY_MS);
}

/** True for a real calendar date between MIN_YEAR and MAX_YEAR. */
export const isDate = (iso) => dayNumber(iso) !== null;

/**
 * Calendar days from `start` to `end`, both included (the first and the last working day both count):
 * 2026-01-01 → 2026-01-01 is 1 day. Null when either date is invalid.
 */
export function daysInclusive(start, end) {
    const a = dayNumber(start);
    const b = dayNumber(end);
    return a === null || b === null ? null : b - a + 1;
}

/** Days from `from` to `to` (negative when `to` is earlier): 2026-01-01 → 2026-04-01 is 90. */
export function daysBetween(from, to) {
    const a = dayNumber(from);
    const b = dayNumber(to);
    return a === null || b === null ? null : b - a;
}
