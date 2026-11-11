/**
 * UAE end-of-service gratuity and final-settlement estimate (upgrade spec §5.2 and §12 C6). Pure: no DOM.
 *
 * Legal basis (spec §5.2; legal review required before launch): Federal Decree-Law No. 33 of 2021,
 * Art. 51 (gratuity: 21 days' basic wage for each of the first five years, 30 days for each further year,
 * part years in proportion after one year of continuous service, unpaid absence not counted, capped at
 * two years' wage), Art. 67 (a year is 365 days, a month 30 days), Art. 43 (notice 30–90 days and its
 * compensation), Art. 29 (leave); Cabinet Resolution No. 1 of 2022, Art. 19 (leave cash at the basic
 * wage) and Art. 30 (part-time: in proportion to the hours; no gratuity for temporary work).
 *
 * The formula is the spec's, line for line:
 *   serviceDays = daysInclusive(startDate, lastWorkingDay) − unpaidAbsenceDays
 *   years       = serviceDays / 365
 *   eligible    = contractType !== 'temporary' && years >= 1
 *   daily       = basicMonthly / 30
 *   g21         = min(years, 5) × 21 × daily
 *   g30         = max(years − 5, 0) × 30 × daily
 *   raw         = (g21 + g30) × (part-time ? hours / fullTimeHours : 1)
 *   capBase     = totalMonthlyWage || basicMonthly            (open item: statute "wage" vs basic)
 *   gratuity    = eligible ? min(raw, 24 × capBase) : 0
 *   leave       = unusedLeaveDays × daily
 *   notice      = max(noticeDaysInContract − noticeDaysServed, 0) × (totalMonthlyWage || basicMonthly) / 30
 *   total       = gratuity + leave + notice + unpaidWages
 *
 * Rounding: every amount is rounded to fils (2 decimals, half-up) and the total is the sum of the rounded
 * amounts, so the rows always add up to the total the visitor sees. The 21-day and 30-day parts carry the
 * part-time ratio, so they add up to the gratuity whenever the cap does not apply.
 *
 * Inputs (estimate()): scope, startDate, lastWorkingDay (ISO dates), unpaidAbsenceDays, basicMonthly,
 * totalMonthlyWage, contractType ('full' | 'part' | 'temporary'), hoursPerWeek, fullTimeHoursPerWeek,
 * unusedLeaveDays, noticeDaysInContract, noticeDaysServed, unpaidWages. Numbers are numbers (already
 * parsed: format.js parseNumber), numeric strings, or null / '' when the field is empty; NaN when the
 * visitor typed something that is not a number.
 */
import { daysInclusive, isDate } from './dates.js';
import { MAX_AMOUNT, readNumber, round2, sum } from './money.js';

export const SCOPES = ['mainland', 'freezone', 'difc', 'adgm', 'government', 'domestic'];
/** The Labour Law estimate applies here; DIFC, ADGM, government and domestic work follow other rules. */
export const IN_SCOPE = ['mainland', 'freezone'];
export const CONTRACTS = ['full', 'part', 'temporary'];

export const YEAR_DAYS = 365; // Art. 67
export const MONTH_DAYS = 30; // Art. 67
export const FIRST_YEARS = 5;
export const DAYS_FIRST_YEARS = 21;
export const DAYS_LATER_YEARS = 30;
export const CAP_MONTHS = 24; // Art. 51(6): two years' wage
export const NOTICE_MIN = 30; // Art. 43
export const NOTICE_MAX = 90;
export const MAX_DAYS = 36500;
export const MAX_HOURS = 168;

/** Error codes the UI maps to messages (lang/{locale}/proposed-d2/tools.php → errors.*). */
export const ERRORS = [
    'date',
    'end_before_start',
    'not_number',
    'negative',
    'positive',
    'too_large',
    'whole_days',
    'absence_exceeds_service',
    'total_below_basic',
    'hours',
    'hours_exceed',
    'notice_range',
];

/** Fields that must be filled before an estimate is shown (part-time adds the two hour fields). */
export const REQUIRED = ['startDate', 'lastWorkingDay', 'basicMonthly'];

export const inScope = (scope) => IN_SCOPE.includes(scope);

const text = (value) => (typeof value === 'string' && value.trim() !== '' ? value.trim() : null);

/** Normalised inputs: unknown scope → mainland, unknown contract → full-time, numbers parsed. */
export function normalise(input = {}) {
    return {
        scope: SCOPES.includes(input.scope) ? input.scope : 'mainland',
        startDate: text(input.startDate),
        lastWorkingDay: text(input.lastWorkingDay),
        unpaidAbsenceDays: readNumber(input.unpaidAbsenceDays),
        basicMonthly: readNumber(input.basicMonthly),
        totalMonthlyWage: readNumber(input.totalMonthlyWage),
        contractType: CONTRACTS.includes(input.contractType) ? input.contractType : 'full',
        hoursPerWeek: readNumber(input.hoursPerWeek),
        fullTimeHoursPerWeek: readNumber(input.fullTimeHoursPerWeek),
        unusedLeaveDays: readNumber(input.unusedLeaveDays),
        noticeDaysInContract: readNumber(input.noticeDaysInContract),
        noticeDaysServed: readNumber(input.noticeDaysServed),
        unpaidWages: readNumber(input.unpaidWages),
    };
}

function amountError(value, { positive = false } = {}) {
    if (value === null) return null;
    if (Number.isNaN(value)) return 'not_number';
    if (value < 0) return 'negative';
    if (positive && value === 0) return 'positive';
    if (value > MAX_AMOUNT) return 'too_large';
    return null;
}

function daysError(value) {
    if (value === null) return null;
    if (Number.isNaN(value)) return 'not_number';
    if (value < 0) return 'negative';
    if (value > MAX_DAYS) return 'too_large';
    return null;
}

function hoursError(value) {
    if (value === null) return null;
    if (Number.isNaN(value) || value <= 0 || value > MAX_HOURS) return 'hours';
    return null;
}

/**
 * Field errors ({ field: code }) and the required fields still empty. Empty optional fields are fine:
 * unpaid absence, leave, notice served and unpaid wages count as 0; no total wage → the basic wage is
 * used for the cap and the notice; no contractual notice → no notice compensation.
 */
export function validate(values) {
    const v = values;
    const errors = {};
    const missing = [];

    for (const field of ['startDate', 'lastWorkingDay']) {
        if (v[field] === null) missing.push(field);
        else if (!isDate(v[field])) errors[field] = 'date';
    }
    const calendarDays = errors.startDate || errors.lastWorkingDay || !v.startDate || !v.lastWorkingDay
        ? null
        : daysInclusive(v.startDate, v.lastWorkingDay);
    if (calendarDays !== null && calendarDays < 1) errors.lastWorkingDay = 'end_before_start';

    const absence = daysError(v.unpaidAbsenceDays);
    if (absence) errors.unpaidAbsenceDays = absence;
    else if (v.unpaidAbsenceDays !== null && !Number.isInteger(v.unpaidAbsenceDays)) {
        // Service is counted in whole days (it is shown as "N days").
        errors.unpaidAbsenceDays = 'whole_days';
    } else if (calendarDays !== null && calendarDays >= 1 && (v.unpaidAbsenceDays ?? 0) >= calendarDays) {
        errors.unpaidAbsenceDays = 'absence_exceeds_service';
    }

    if (v.basicMonthly === null) missing.push('basicMonthly');
    else {
        const basic = amountError(v.basicMonthly, { positive: true });
        if (basic) errors.basicMonthly = basic;
    }
    const total = amountError(v.totalMonthlyWage);
    if (total) errors.totalMonthlyWage = total;
    else if (v.totalMonthlyWage !== null && v.basicMonthly !== null && !errors.basicMonthly && v.totalMonthlyWage < v.basicMonthly) {
        // The total wage includes the basic wage, so it can never be lower.
        errors.totalMonthlyWage = 'total_below_basic';
    }

    if (v.contractType === 'part') {
        for (const field of ['hoursPerWeek', 'fullTimeHoursPerWeek']) {
            if (v[field] === null) missing.push(field);
            else {
                const hours = hoursError(v[field]);
                if (hours) errors[field] = hours;
            }
        }
        if (!errors.hoursPerWeek && !errors.fullTimeHoursPerWeek && v.hoursPerWeek !== null && v.fullTimeHoursPerWeek !== null
            && v.hoursPerWeek > v.fullTimeHoursPerWeek) {
            errors.hoursPerWeek = 'hours_exceed';
        }
    }

    const leave = daysError(v.unusedLeaveDays);
    if (leave) errors.unusedLeaveDays = leave;

    if (v.noticeDaysInContract !== null) {
        if (Number.isNaN(v.noticeDaysInContract)) errors.noticeDaysInContract = 'not_number';
        else if (v.noticeDaysInContract < NOTICE_MIN || v.noticeDaysInContract > NOTICE_MAX) errors.noticeDaysInContract = 'notice_range';
    }
    const served = daysError(v.noticeDaysServed);
    if (served) errors.noticeDaysServed = served;

    const unpaid = amountError(v.unpaidWages);
    if (unpaid) errors.unpaidWages = unpaid;

    return { errors, missing };
}

/** The estimate for valid, complete values (see validate()). */
export function calculate(values) {
    const v = values;
    const calendarDays = daysInclusive(v.startDate, v.lastWorkingDay);
    const unpaidAbsenceDays = v.unpaidAbsenceDays ?? 0;
    const serviceDays = calendarDays - unpaidAbsenceDays;
    const years = serviceDays / YEAR_DAYS;
    const contract = v.contractType;
    const eligible = contract !== 'temporary' && years >= 1;
    const daily = v.basicMonthly / MONTH_DAYS;
    const ratio = contract === 'part' ? v.hoursPerWeek / v.fullTimeHoursPerWeek : 1;

    const g21 = Math.min(years, FIRST_YEARS) * DAYS_FIRST_YEARS * daily;
    const g30 = Math.max(years - FIRST_YEARS, 0) * DAYS_LATER_YEARS * daily;
    const part21 = eligible ? round2(g21 * ratio) : 0;
    const part30 = eligible ? round2(g30 * ratio) : 0;
    const raw = sum(part21, part30);

    const capBasis = v.totalMonthlyWage ? 'total' : 'basic';
    const wage = v.totalMonthlyWage || v.basicMonthly;
    const cap = round2(CAP_MONTHS * wage);
    const capApplied = eligible && raw > cap;
    const gratuity = eligible ? Math.min(raw, cap) : 0;

    const leave = round2((v.unusedLeaveDays ?? 0) * daily);
    const noticeDaysOwed = v.noticeDaysInContract === null ? 0 : Math.max(v.noticeDaysInContract - (v.noticeDaysServed ?? 0), 0);
    const notice = round2((noticeDaysOwed * wage) / MONTH_DAYS);
    const unpaidWages = round2(v.unpaidWages ?? 0);
    const total = sum(gratuity, leave, notice, unpaidWages);

    return {
        calendarDays,
        calendarYears: calendarDays / YEAR_DAYS,
        unpaidAbsenceDays,
        serviceDays,
        years,
        contract,
        ratio,
        eligible,
        reason: eligible ? null : contract === 'temporary' ? 'temporary' : 'under_one_year',
        daily,
        part21,
        part30,
        raw,
        capBasis,
        cap,
        capApplied,
        gratuity,
        leave,
        noticeDaysOwed,
        notice,
        unpaidWages,
        total,
    };
}

/**
 * One call for the UI: { status, values, errors, missing, result }.
 * status: 'out_of_scope' (DIFC, ADGM, government, domestic work), 'invalid' (field errors),
 * 'incomplete' (required fields empty) or 'ok' (result is the estimate).
 */
export function estimate(input = {}) {
    const values = normalise(input);
    if (!inScope(values.scope)) return { status: 'out_of_scope', values, errors: {}, missing: [], result: null };
    const { errors, missing } = validate(values);
    if (Object.keys(errors).length > 0) return { status: 'invalid', values, errors, missing, result: null };
    if (missing.length > 0) return { status: 'incomplete', values, errors, missing, result: null };
    return { status: 'ok', values, errors, missing, result: calculate(values) };
}
