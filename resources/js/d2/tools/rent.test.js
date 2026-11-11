import { describe, expect, it } from 'vitest';
import { BANDS, bandIndex, calculate, estimate, gapBasisPoints, noticeCheck, normalise } from './rent.js';

const increaseFor = (current, average) => BANDS[bandIndex(current, average)].increase;

describe('rent: Decree No. 43 of 2013 bands at every edge', () => {
    it('exactly 10% below the average allows no increase', () => {
        expect(increaseFor(90000, 100000)).toBe(0);
        expect(increaseFor(63000, 70000)).toBe(0);
    });

    it('just above 10% below allows 5%', () => {
        expect(increaseFor(89999.99, 100000)).toBe(5);
        expect(increaseFor(89999, 100000)).toBe(5);
    });

    it('exactly 20% below allows 5%, just above allows 10%', () => {
        expect(increaseFor(80000, 100000)).toBe(5);
        expect(increaseFor(68000, 85000)).toBe(5);
        expect(increaseFor(79999.99, 100000)).toBe(10);
    });

    it('exactly 30% below allows 10%, just above allows 15%', () => {
        expect(increaseFor(70000, 100000)).toBe(10);
        expect(increaseFor(69999.99, 100000)).toBe(15);
    });

    it('exactly 40% below allows 15%', () => {
        expect(increaseFor(60000, 100000)).toBe(15);
        expect(increaseFor(51000, 85000)).toBe(15);
    });

    it('just above 40% below allows 20%', () => {
        expect(increaseFor(59999.99, 100000)).toBe(20);
        expect(increaseFor(10000, 100000)).toBe(20);
    });

    it('a rent at or above the average allows no increase', () => {
        expect(increaseFor(100000, 100000)).toBe(0);
        expect(increaseFor(120000, 100000)).toBe(0);
        const r = estimate({ currentAnnualRent: 120000, indexAverageRent: 100000 }).result;
        expect(r.belowAverage).toBe(false);
        expect(r.gapBasisPoints).toBe(0);
        expect(r.increaseAmount).toBe(0);
        expect(r.newRent).toBe(120000);
    });

    it('bands with amounts in fils that floating point gets wrong', () => {
        // 0.3 and 0.1 are not exact in binary: the integer comparison still lands on the edge.
        expect(increaseFor(700.7, 1001)).toBe(10); // exactly 30%
        expect(increaseFor(900.9, 1001)).toBe(0); // exactly 10%
    });
});

describe('rent: the gap shown', () => {
    it('is rounded up to 0.01% so it never falls back onto a band edge', () => {
        expect(gapBasisPoints(90000, 100000)).toBe(1000);
        expect(gapBasisPoints(89999.99, 100000)).toBe(1001);
        expect(gapBasisPoints(77500, 100000)).toBe(2250);
        expect(gapBasisPoints(116500, 150000)).toBe(2234); // 22.333…% → 22.34%
    });

    it('is 0 when the rent is not below the average or the average is not positive', () => {
        expect(gapBasisPoints(100000, 100000)).toBe(0);
        expect(gapBasisPoints(1, 0)).toBe(0);
    });
});

describe('rent: maximum increase in AED', () => {
    it('applies the band to the current rent, rounded to fils', () => {
        const r = estimate({ currentAnnualRent: 80000, indexAverageRent: 103000 }).result;
        expect(r.band).toBe(2);
        expect(r.increase).toBe(10);
        expect(r.increaseAmount).toBe(8000);
        expect(r.newRent).toBe(88000);
        const odd = calculate(normalise({ currentAnnualRent: 75555.55, indexAverageRent: 90000 }));
        expect(odd.increase).toBe(5);
        expect(odd.increaseAmount).toBe(3777.78);
        expect(odd.newRent).toBe(79333.33);
    });
});

describe('rent: the 90-day notice', () => {
    const expiry = '2027-03-31';

    it('passes at 90 and 91 days, fails at 89', () => {
        expect(noticeCheck('2026-12-31', expiry)).toEqual({ days: 90, ok: true, after: false });
        expect(noticeCheck('2026-12-30', expiry)).toEqual({ days: 91, ok: true, after: false });
        expect(noticeCheck('2027-01-01', expiry)).toEqual({ days: 89, ok: false, after: false });
    });

    it('fails when the notice arrives on or after the expiry date', () => {
        expect(noticeCheck(expiry, expiry)).toEqual({ days: 0, ok: false, after: false });
        expect(noticeCheck('2027-04-02', expiry)).toEqual({ days: -2, ok: false, after: true });
    });

    it('is not checked until both dates are valid', () => {
        expect(noticeCheck('', expiry)).toBeNull();
        expect(noticeCheck('2027-02-30', expiry)).toBeNull();
        expect(estimate({ currentAnnualRent: 1, indexAverageRent: 2, expiryDate: expiry }).result.notice).toBeNull();
        expect(estimate({ currentAnnualRent: 1, indexAverageRent: 2, expiryDate: expiry, noticeReceivedDate: '2026-11-01' }).result.notice.ok).toBe(true);
    });
});

describe('rent: validation', () => {
    it('needs both rents, positive numbers', () => {
        expect(estimate({}).missing).toEqual(['currentAnnualRent', 'indexAverageRent']);
        expect(estimate({ currentAnnualRent: -1, indexAverageRent: 'x' }).errors).toEqual({ currentAnnualRent: 'negative', indexAverageRent: 'not_number' });
        expect(estimate({ currentAnnualRent: 1000, indexAverageRent: 0 }).errors).toEqual({ indexAverageRent: 'positive' });
        expect(estimate({ currentAnnualRent: 5e9, indexAverageRent: 1 }).errors).toEqual({ currentAnnualRent: 'too_large' });
    });

    it('rejects invalid dates but does not need them for the band', () => {
        const out = estimate({ currentAnnualRent: 90000, indexAverageRent: 100000, expiryDate: '2027-13-01' });
        expect(out.status).toBe('invalid');
        expect(out.errors).toEqual({ expiryDate: 'date' });
        expect(estimate({ currentAnnualRent: 90000, indexAverageRent: 100000 }).status).toBe('ok');
    });
});
