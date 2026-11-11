/**
 * Dubai rent increase check (upgrade spec §5.3 and §12 C6). Pure: no DOM.
 *
 * Legal basis (spec §5.3; legal review required before launch): the increase bands of Decree No. 43 of
 * 2013 (the DLD / Dubai Media Office release of the Smart Rental Index cites "Decree No. (34) of 2013":
 * open item), applied at renewal to the current rent according to how far it is below the average rent
 * of similar units in the DLD Rental Index (the visitor enters that average; nothing is fetched); and the
 * 90-day notice of Law No. 26 of 2007, Art. 14, as amended by Law No. 33 of 2008.
 *
 *   gap = (average − current) / average
 *   gap ≤ 10% → 0% · ≤ 20% → 5% · ≤ 30% → 10% · ≤ 40% → 15% · > 40% → 20%; current ≥ average → 0%
 *   notice check: days(noticeReceived → expiry) ≥ 90
 *
 * Bands are decided in whole fils with integer cross-multiplication, so a rent exactly 10% below the
 * average is always in the first band whatever the amounts.
 */
import { daysBetween, isDate } from './dates.js';
import { MAX_AMOUNT, readNumber, toFils } from './money.js';

export const NOTICE_DAYS = 90;

/** Decree No. 43 of 2013: gap up to `upTo` percent below the average → at most `increase` percent. */
export const BANDS = [
    { upTo: 10, increase: 0 },
    { upTo: 20, increase: 5 },
    { upTo: 30, increase: 10 },
    { upTo: 40, increase: 15 },
    { upTo: null, increase: 20 },
];

export const ERRORS = ['date', 'not_number', 'negative', 'positive', 'too_large'];
export const REQUIRED = ['currentAnnualRent', 'indexAverageRent'];

/** Index (0–4) of the band for a current rent against the index average. */
export function bandIndex(current, average) {
    const c = toFils(current);
    const a = toFils(average);
    if (!(a > 0) || c >= a) return 0;
    const diff = a - c;
    for (let i = 0; i < BANDS.length - 1; i += 1) {
        // diff / a ≤ upTo / 100  ⇔  diff × 100 ≤ a × upTo (integers: exact)
        if (diff * 100 <= a * BANDS[i].upTo) return i;
    }
    return BANDS.length - 1;
}

/**
 * The gap in basis points (1 bp = 0.01%), rounded UP: 10.00001% shows as 10.01%, never as 10%, so the
 * figure on screen always sits in the band that was applied. 0 when the rent is not below the average.
 */
export function gapBasisPoints(current, average) {
    const c = toFils(current);
    const a = toFils(average);
    if (!(a > 0) || c >= a) return 0;
    const n = (a - c) * 10000;
    const q = Math.floor(n / a);
    return q * a < n ? q + 1 : q;
}

/** Notice check: { days, ok, after } or null when a date is missing or invalid. */
export function noticeCheck(noticeReceived, expiry) {
    const days = daysBetween(noticeReceived, expiry);
    if (days === null) return null;
    return { days, ok: days >= NOTICE_DAYS, after: days < 0 };
}

const text = (value) => (typeof value === 'string' && value.trim() !== '' ? value.trim() : null);

export function normalise(input = {}) {
    return {
        currentAnnualRent: readNumber(input.currentAnnualRent),
        indexAverageRent: readNumber(input.indexAverageRent),
        expiryDate: text(input.expiryDate),
        noticeReceivedDate: text(input.noticeReceivedDate),
    };
}

function amountError(value) {
    if (value === null) return null;
    if (Number.isNaN(value)) return 'not_number';
    if (value < 0) return 'negative';
    if (value === 0) return 'positive';
    if (value > MAX_AMOUNT) return 'too_large';
    return null;
}

export function validate(values) {
    const errors = {};
    const missing = [];
    for (const field of REQUIRED) {
        if (values[field] === null) missing.push(field);
        else {
            const error = amountError(values[field]);
            if (error) errors[field] = error;
        }
    }
    for (const field of ['expiryDate', 'noticeReceivedDate']) {
        if (values[field] !== null && !isDate(values[field])) errors[field] = 'date';
    }
    return { errors, missing };
}

/** The check for valid, complete values. Amounts in AED, rounded to fils. */
export function calculate(values) {
    const current = values.currentAnnualRent;
    const average = values.indexAverageRent;
    const band = bandIndex(current, average);
    const increase = BANDS[band].increase;
    const currentFils = toFils(current);
    const increaseFils = Math.round((currentFils * increase) / 100);
    return {
        belowAverage: toFils(current) < toFils(average),
        gapBasisPoints: gapBasisPoints(current, average),
        band,
        increase,
        increaseAmount: increaseFils / 100,
        newRent: (currentFils + increaseFils) / 100,
        notice: noticeCheck(values.noticeReceivedDate, values.expiryDate),
    };
}

/** { status: 'invalid' | 'incomplete' | 'ok', values, errors, missing, result }. */
export function estimate(input = {}) {
    const values = normalise(input);
    const { errors, missing } = validate(values);
    if (Object.keys(errors).length > 0) return { status: 'invalid', values, errors, missing, result: null };
    if (missing.length > 0) return { status: 'incomplete', values, errors, missing, result: null };
    return { status: 'ok', values, errors, missing, result: calculate(values) };
}
