/**
 * Money arithmetic for the home tools. Pure: no DOM.
 *
 * Amounts are dirhams (AED) as JavaScript numbers. Results are rounded to fils (2 decimals) with
 * half-up rounding on the decimal value the visitor sees (1.005 → 1.01), never on its binary
 * approximation (1.00499999… → 1.00). Comparisons that decide a legal band are done in whole fils
 * (integers), so a rent exactly 10% below the average can never be misread as 10.000000001%.
 */

/** Largest amount the tools accept (AED): keeps every product of fils well inside 2^53. */
export const MAX_AMOUNT = 1e9;

/** Half-up rounding to 2 decimals (fils). -0 becomes 0. */
export function round2(value) {
    if (!Number.isFinite(value) || value === 0) return 0;
    const abs = Math.abs(value);
    const text = String(abs);
    const rounded = text.includes('e') ? Math.round(abs * 100) / 100 : Math.round(Number(`${text}e2`)) / 100;
    return rounded === 0 ? 0 : Math.sign(value) * rounded;
}

/** Whole fils (1 AED = 100 fils) for an amount in AED. */
export const toFils = (aed) => Math.round(Number(`${round2(aed)}e2`));

/** Sum of amounts, rounded to fils (adds rounded parts, so the total equals what the rows show). */
export function sum(...values) {
    return round2(values.reduce((total, value) => total + toFils(value), 0) / 100);
}

/**
 * A number the visitor typed, already parsed (see format.js parseNumber): null when the field is empty,
 * NaN when it is not a number. Strings are accepted for convenience (tests, query values).
 */
export function readNumber(value) {
    if (value === null || value === undefined) return null;
    if (typeof value === 'number') return Number.isFinite(value) ? value : NaN;
    const text = String(value).trim();
    if (text === '') return null;
    return /^-?\d+(\.\d+)?$/.test(text) ? Number(text) : NaN;
}
