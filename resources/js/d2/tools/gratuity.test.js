import { describe, expect, it } from 'vitest';
import { calculate, estimate, IN_SCOPE, normalise, SCOPES, validate } from './gratuity.js';
import { daysInclusive } from './dates.js';
import { round2, sum } from './money.js';

/** ISO date `n` days after `iso` (UTC). */
function addDays(iso, n) {
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(Date.UTC(y, m - 1, d + n)).toISOString().slice(0, 10);
}

/** Inputs for a service of exactly `days` calendar days (both ends counted) from 2015-01-01. */
function service(days, extra = {}) {
    const startDate = '2015-01-01';
    return { scope: 'mainland', startDate, lastWorkingDay: addDays(startDate, days - 1), basicMonthly: 10000, ...extra };
}

const ok = (input) => {
    const out = estimate(input);
    expect(out.status, JSON.stringify(out.errors)).toBe('ok');
    return out.result;
};

describe('gratuity: the spec cases (§12 C6)', () => {
    it('0.9 years of service gives no gratuity', () => {
        const r = ok(service(Math.round(0.9 * 365)));
        expect(r.years).toBeCloseTo(0.9, 2);
        expect(r.eligible).toBe(false);
        expect(r.reason).toBe('under_one_year');
        expect(r.gratuity).toBe(0);
        expect(r.part21).toBe(0);
        expect(r.part30).toBe(0);
    });

    it('exactly 5 years on a basic wage of 10,000 gives 35,000', () => {
        const input = { scope: 'mainland', startDate: '2020-01-01', lastWorkingDay: '2024-12-29', basicMonthly: 10000 };
        expect(daysInclusive(input.startDate, input.lastWorkingDay)).toBe(1825);
        const r = ok(input);
        expect(r.years).toBe(5);
        expect(r.part21).toBe(35000);
        expect(r.part30).toBe(0);
        expect(r.gratuity).toBe(35000);
        expect(r.total).toBe(35000);
    });

    it('7 years on 10,000 gives 35,000 + 2 × 10,000 = 55,000', () => {
        const r = ok(service(7 * 365));
        expect(r.years).toBe(7);
        expect(r.part21).toBe(35000);
        expect(r.part30).toBe(20000);
        expect(r.gratuity).toBe(55000);
    });

    it('unpaid absence reduces the service counted', () => {
        const full = ok(service(7 * 365));
        const absent = ok(service(7 * 365, { unpaidAbsenceDays: 365 }));
        expect(absent.serviceDays).toBe(6 * 365);
        expect(absent.years).toBe(6);
        expect(absent.gratuity).toBe(45000);
        expect(absent.gratuity).toBeLessThan(full.gratuity);
    });

    it('unpaid absence can take the service below one year', () => {
        const r = ok(service(380, { unpaidAbsenceDays: 20 }));
        expect(r.serviceDays).toBe(360);
        expect(r.eligible).toBe(false);
        expect(r.gratuity).toBe(0);
    });

    it('a part-time ratio of 0.5 halves the gratuity', () => {
        const fullTime = ok(service(7 * 365));
        const partTime = ok(service(7 * 365, { contractType: 'part', hoursPerWeek: 24, fullTimeHoursPerWeek: 48 }));
        expect(partTime.ratio).toBe(0.5);
        expect(partTime.gratuity).toBe(fullTime.gratuity / 2);
        expect(partTime.part21).toBe(17500);
        expect(partTime.part30).toBe(10000);
    });

    it('the cap (24 months of wage) triggers at very long service', () => {
        const basicOnly = ok(service(40 * 365));
        expect(basicOnly.raw).toBe(35000 + 35 * 10000);
        expect(basicOnly.cap).toBe(240000);
        expect(basicOnly.capApplied).toBe(true);
        expect(basicOnly.capBasis).toBe('basic');
        expect(basicOnly.gratuity).toBe(240000);

        const withTotal = ok(service(40 * 365, { totalMonthlyWage: 15000 }));
        expect(withTotal.capBasis).toBe('total');
        expect(withTotal.cap).toBe(360000);
        expect(withTotal.capApplied).toBe(true);
        expect(withTotal.gratuity).toBe(360000);

        const higherTotal = ok(service(40 * 365, { totalMonthlyWage: 20000 }));
        expect(higherTotal.capApplied).toBe(false);
        expect(higherTotal.gratuity).toBe(385000);
    });
});

describe('gratuity: eligibility', () => {
    it('one full year (365 days) is the first eligible day', () => {
        expect(ok(service(364)).gratuity).toBe(0);
        const r = ok(service(365));
        expect(r.eligible).toBe(true);
        expect(r.gratuity).toBe(7000);
    });

    it('temporary work has no gratuity, whatever the service', () => {
        const r = ok(service(10 * 365, { contractType: 'temporary', unusedLeaveDays: 3 }));
        expect(r.eligible).toBe(false);
        expect(r.reason).toBe('temporary');
        expect(r.gratuity).toBe(0);
        expect(r.leave).toBe(1000);
        expect(r.total).toBe(1000);
    });

    it('part years after the first year count in proportion', () => {
        const r = ok(service(365 + 73)); // 1.2 years
        expect(r.years).toBeCloseTo(1.2, 10);
        expect(r.gratuity).toBe(8400);
    });
});

describe('gratuity: leave, notice and unpaid wages', () => {
    it('leave encashment uses the basic daily wage (basic ÷ 30)', () => {
        const r = ok(service(2 * 365, { unusedLeaveDays: 15, totalMonthlyWage: 15000 }));
        expect(r.leave).toBe(5000);
    });

    it('notice compensation is the unserved notice at the total wage, or the basic wage without it', () => {
        const withTotal = ok(service(2 * 365, { noticeDaysInContract: 30, noticeDaysServed: 10, totalMonthlyWage: 12000 }));
        expect(withTotal.noticeDaysOwed).toBe(20);
        expect(withTotal.notice).toBe(8000);
        const basicOnly = ok(service(2 * 365, { noticeDaysInContract: 30, noticeDaysServed: 10 }));
        expect(basicOnly.notice).toBe(6666.67);
    });

    it('notice served beyond the contractual notice gives no compensation (never negative)', () => {
        const r = ok(service(2 * 365, { noticeDaysInContract: 30, noticeDaysServed: 45 }));
        expect(r.noticeDaysOwed).toBe(0);
        expect(r.notice).toBe(0);
    });

    it('no contractual notice entered means no notice compensation', () => {
        expect(ok(service(2 * 365, { noticeDaysServed: 5 })).notice).toBe(0);
    });

    it('the total is the gratuity plus leave, notice and unpaid wages', () => {
        const r = ok(service(7 * 365, { unusedLeaveDays: 10, noticeDaysInContract: 60, noticeDaysServed: 0, totalMonthlyWage: 15000, unpaidWages: 2500.5 }));
        expect(r.leave).toBe(3333.33);
        expect(r.notice).toBe(30000);
        expect(r.unpaidWages).toBe(2500.5);
        expect(r.total).toBe(round2(55000 + 3333.33 + 30000 + 2500.5));
    });
});

describe('gratuity: inclusive days, leap years and rounding', () => {
    it('counts both the first and the last working day', () => {
        expect(daysInclusive('2026-03-10', '2026-03-10')).toBe(1);
        expect(daysInclusive('2025-12-31', '2026-01-01')).toBe(2);
    });

    it('counts 29 February in a leap year', () => {
        expect(daysInclusive('2024-02-28', '2024-03-01')).toBe(3);
        expect(daysInclusive('2023-02-28', '2023-03-01')).toBe(2);
    });

    it('five calendar years with two leap days are slightly more than five law years (365 days each)', () => {
        const r = ok({ scope: 'mainland', startDate: '2020-01-01', lastWorkingDay: '2024-12-31', basicMonthly: 10000 });
        expect(r.calendarDays).toBe(1827);
        expect(r.years).toBeCloseTo(1827 / 365, 12);
        expect(r.part21).toBe(35000);
        expect(r.part30).toBe(54.79);
        expect(r.gratuity).toBe(35054.79);
    });

    it('rounds every amount to fils, half up on the decimal value', () => {
        expect(round2(1.005)).toBe(1.01);
        expect(round2(2.675)).toBe(2.68);
        expect(round2(333.3333333)).toBe(333.33);
        expect(round2(-0)).toBe(0);
        expect(ok(service(400, { unusedLeaveDays: 1 })).leave).toBe(333.33);
        expect(ok(service(400, { unusedLeaveDays: 2 })).leave).toBe(666.67);
        expect(ok(service(400, { unusedLeaveDays: 3 })).leave).toBe(1000);
    });

    it('the rows always add up to the total shown', () => {
        let seed = 7;
        const rand = () => {
            seed = (seed * 16807) % 2147483647;
            return seed / 2147483647;
        };
        for (let i = 0; i < 300; i += 1) {
            const r = ok(service(1 + Math.floor(rand() * 15000), {
                basicMonthly: round2(1000 + rand() * 60000),
                totalMonthlyWage: rand() > 0.5 ? round2(61000 + rand() * 20000) : null,
                unusedLeaveDays: Math.floor(rand() * 60),
                noticeDaysInContract: 30 + Math.floor(rand() * 61),
                noticeDaysServed: Math.floor(rand() * 40),
                unpaidWages: round2(rand() * 9999.99),
            }));
            expect(r.total).toBe(sum(r.gratuity, r.leave, r.notice, r.unpaidWages));
            if (!r.capApplied) expect(r.gratuity).toBe(sum(r.part21, r.part30));
            expect(r.gratuity).toBeLessThanOrEqual(r.cap);
        }
    });
});

describe('gratuity: validation codes', () => {
    const codes = (input) => estimate(input).errors;

    it('rejects a last working day before the start date', () => {
        const out = estimate({ startDate: '2026-05-02', lastWorkingDay: '2026-05-01', basicMonthly: 9000 });
        expect(out.status).toBe('invalid');
        expect(out.errors).toEqual({ lastWorkingDay: 'end_before_start' });
    });

    it('rejects impossible and implausible dates', () => {
        expect(codes({ startDate: '2026-02-30' }).startDate).toBe('date');
        expect(codes({ startDate: '0002-01-01' }).startDate).toBe('date');
        expect(codes({ lastWorkingDay: '26-01-01' }).lastWorkingDay).toBe('date');
    });

    it('rejects negative values and text that is not a number', () => {
        const e = codes({ ...service(800), unpaidAbsenceDays: -1, unusedLeaveDays: -2, unpaidWages: -5, noticeDaysServed: -1, basicMonthly: NaN });
        expect(e).toEqual({ unpaidAbsenceDays: 'negative', unusedLeaveDays: 'negative', unpaidWages: 'negative', noticeDaysServed: 'negative', basicMonthly: 'not_number' });
        expect(codes({ ...service(800), basicMonthly: 'ten' }).basicMonthly).toBe('not_number');
        expect(codes({ ...service(800), basicMonthly: 0 }).basicMonthly).toBe('positive');
        expect(codes({ ...service(800), basicMonthly: 2e9 }).basicMonthly).toBe('too_large');
    });

    it('accepts a contractual notice of 30 to 90 days only', () => {
        expect(codes({ ...service(800), noticeDaysInContract: 29 }).noticeDaysInContract).toBe('notice_range');
        expect(codes({ ...service(800), noticeDaysInContract: 91 }).noticeDaysInContract).toBe('notice_range');
        expect(codes({ ...service(800), noticeDaysInContract: 30 })).toEqual({});
        expect(codes({ ...service(800), noticeDaysInContract: 90 })).toEqual({});
    });

    it('needs valid part-time hours, no more than the full-time hours', () => {
        const part = (hoursPerWeek, fullTimeHoursPerWeek) => estimate({ ...service(800), contractType: 'part', hoursPerWeek, fullTimeHoursPerWeek });
        expect(part(null, 48).status).toBe('incomplete');
        expect(part(null, 48).missing).toEqual(['hoursPerWeek']);
        expect(part(0, 48).errors).toEqual({ hoursPerWeek: 'hours' });
        expect(part(20, 200).errors).toEqual({ fullTimeHoursPerWeek: 'hours' });
        expect(part(50, 48).errors).toEqual({ hoursPerWeek: 'hours_exceed' });
        expect(part(48, 48).status).toBe('ok');
    });

    it('counts unpaid absence in whole days (leave days may be fractional)', () => {
        expect(codes(service(800, { unpaidAbsenceDays: 2.5 })).unpaidAbsenceDays).toBe('whole_days');
        expect(ok(service(800, { unusedLeaveDays: 1.5 })).leave).toBe(500);
    });

    it('rejects unpaid absence as long as the whole period', () => {
        expect(codes(service(100, { unpaidAbsenceDays: 100 })).unpaidAbsenceDays).toBe('absence_exceeds_service');
        expect(estimate(service(100, { unpaidAbsenceDays: 99 })).status).toBe('ok');
    });

    it('rejects a total wage below the basic wage (the total includes the basic wage)', () => {
        expect(codes(service(800, { totalMonthlyWage: 9000 })).totalMonthlyWage).toBe('total_below_basic');
        expect(codes(service(800, { totalMonthlyWage: 0 })).totalMonthlyWage).toBe('total_below_basic');
        expect(codes(service(800, { totalMonthlyWage: 10000 }))).toEqual({});
    });

    it('lists the required fields while they are empty', () => {
        const out = estimate({});
        expect(out.status).toBe('incomplete');
        expect(out.missing).toEqual(['startDate', 'lastWorkingDay', 'basicMonthly']);
        expect(estimate({ startDate: '', lastWorkingDay: '  ', basicMonthly: '' }).missing).toHaveLength(3);
    });
});

describe('gratuity: scope', () => {
    it('covers the mainland private sector and non-financial free zones only', () => {
        expect(IN_SCOPE).toEqual(['mainland', 'freezone']);
        for (const scope of SCOPES) {
            const out = estimate(service(800, { scope }));
            expect(out.status).toBe(IN_SCOPE.includes(scope) ? 'ok' : 'out_of_scope');
        }
    });

    it('treats an unknown scope or contract type as the default', () => {
        const v = normalise({ scope: 'mars', contractType: ['part'] });
        expect(v.scope).toBe('mainland');
        expect(v.contractType).toBe('full');
    });

    it('validate() and calculate() can be used on their own', () => {
        const values = normalise(service(7 * 365));
        expect(validate(values)).toEqual({ errors: {}, missing: [] });
        expect(calculate(values).gratuity).toBe(55000);
    });
});
