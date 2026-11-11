import { describe, expect, it } from 'vitest';
import {
    addDays,
    addMonths,
    baseLang,
    clampDate,
    formatDate,
    inRange,
    isoOf,
    latinDigits,
    monthCells,
    parseDate,
    todayIn,
    weekBounds,
    weekday,
} from './parse.js';

// The Design 2 date field (D36): typed dates in every language, the short display form, the calendar's month grid.
describe('date field: typed dates', () => {
    it('reads day, month and year with any separator, as every page language writes them', () => {
        expect(parseDate('01/03/2018')).toBe('2018-03-01');
        expect(parseDate('1/3/2018')).toBe('2018-03-01');
        expect(parseDate('01.03.2018')).toBe('2018-03-01');
        expect(parseDate('1-3-2018')).toBe('2018-03-01');
        expect(parseDate(' 1 3 2018 ')).toBe('2018-03-01');
        expect(parseDate('31.12.2025')).toBe('2025-12-31');
    });

    it('reads eight digits, an ISO date and two-digit years', () => {
        expect(parseDate('01032018')).toBe('2018-03-01');
        expect(parseDate('2018-03-01')).toBe('2018-03-01');
        expect(parseDate('2018/3/1')).toBe('2018-03-01');
        expect(parseDate('1/3/18')).toBe('2018-03-01');
        expect(parseDate('1/3/68')).toBe('2068-03-01');
        expect(parseDate('1/3/69')).toBe('1969-03-01');
        expect(parseDate('15/06/85')).toBe('1985-06-15');
    });

    it('reads Arabic-Indic and Persian digits and ignores bidi marks', () => {
        expect(parseDate('٠١/٠٣/٢٠١٨')).toBe('2018-03-01');
        expect(parseDate('۱۵/۰۶/۲۰۲۵')).toBe('2025-06-15');
        expect(parseDate('‏01/03/2018‏')).toBe('2018-03-01');
        expect(latinDigits('٢٠٢٦')).toBe('2026');
    });

    it('refuses half dates, impossible days, letters and years outside 1900-2100', () => {
        for (const text of ['', '   ', '01/03', '01/03/', '1/3/2', '1/3/201', '31/02/2026', '29/02/2025', '32/01/2026', '01/13/2026',
            '01/03/1899', '01/01/2101', 'abc', '01/03/2018x', '1/2/3/2018', '123/1/2018', '2018-003-01', '0103201']) {
            expect(parseDate(text), JSON.stringify(text)).toBeNull();
        }
        expect(parseDate('29/02/2024')).toBe('2024-02-29');
    });
});

describe('date field: display and arithmetic', () => {
    it('prints the short form of the page language with Western digits', () => {
        expect(formatDate('2018-03-01', 'en')).toBe('01/03/2018');
        expect(formatDate('2018-03-01', 'ar')).toBe('01/03/2018');
        expect(formatDate('2018-03-01', 'ru-RU')).toBe('01.03.2018');
        expect(formatDate('2018-03-01', 'de')).toBe('01/03/2018');
        expect(formatDate('2018-02-30', 'en')).toBe('');
        expect(formatDate('', 'en')).toBe('');
        expect(baseLang('AR_ae')).toBe('ar');
    });

    it('round-trips every display form through the parser', () => {
        for (const lang of ['en', 'ar', 'ru']) {
            for (const iso of ['1900-01-01', '2018-03-01', '2024-02-29', '2100-12-31']) {
                expect(parseDate(formatDate(iso, lang))).toBe(iso);
            }
        }
    });

    it('moves by days, months and weeks across month and year ends', () => {
        expect(addDays('2026-02-28', 1)).toBe('2026-03-01');
        expect(addDays('2026-01-01', -1)).toBe('2025-12-31');
        expect(addMonths('2026-01-31', 1)).toBe('2026-02-28');
        expect(addMonths('2024-01-31', 1)).toBe('2024-02-29');
        expect(addMonths('2026-03-15', -3)).toBe('2025-12-15');
        expect(addMonths('2026-03-15', 12)).toBe('2027-03-15');
        expect(weekday('2026-11-09')).toBe(1); // a Monday
        expect(weekday('2026-11-15')).toBe(7);
        expect(weekBounds('2026-11-11', 1)).toEqual({ start: '2026-11-09', end: '2026-11-15' });
        expect(weekBounds('2026-11-11', 6)).toEqual({ start: '2026-11-07', end: '2026-11-13' });
        expect(isoOf(2026, 2, 29)).toBeNull();
    });

    it('keeps a date inside the field limits', () => {
        expect(clampDate('1899-05-01', '1900-01-01', '2100-12-31')).toBe('1900-01-01');
        expect(clampDate('2026-05-01', '1900-01-01', null)).toBe('2026-05-01');
        expect(inRange('2026-05-01', '2026-05-02', null)).toBe(false);
        expect(inRange('2026-05-01', null, null)).toBe(true);
    });

    it('lays a month out in six weeks from the first day of the week', () => {
        const nov = monthCells(2026, 11, 1); // 1 November 2026 is a Sunday
        expect(nov).toHaveLength(42);
        expect(nov.slice(0, 7)).toEqual([null, null, null, null, null, null, '2026-11-01']);
        expect(nov[35]).toBe('2026-11-30');
        expect(nov[36]).toBeNull();
        const sundayFirst = monthCells(2026, 11, 7);
        expect(sundayFirst[0]).toBe('2026-11-01');
        expect(monthCells(2026, 13, 1)).toEqual([]);
    });

    it('reads today from a clock', () => {
        expect(todayIn(new Date('2026-11-10T21:30:00Z'), 'Asia/Dubai')).toBe('2026-11-11');
        expect(todayIn(new Date('2026-11-10T21:30:00Z'), 'UTC')).toBe('2026-11-10');
    });
});
