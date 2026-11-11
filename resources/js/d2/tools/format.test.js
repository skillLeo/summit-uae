import { describe, expect, it } from 'vitest';
import { date, decimal, fill, integer, intlLocale, money, parseNumber, parts, percent, plainText, plural } from './format.js';
import { dayNumber, daysBetween, daysInclusive, isDate } from './dates.js';
import { readNumber, round2, sum, toFils } from './money.js';

const plain = (s) => s.replace(/[  ]/g, ' ').replace(/[‎‏؜]/g, '');

describe('format: amounts, numbers and dates', () => {
    it('maps page languages to Intl locales', () => {
        expect(intlLocale('en')).toBe('en-AE');
        expect(intlLocale('ar')).toBe('ar-AE');
        expect(intlLocale('ru')).toBe('ru-RU');
        expect(intlLocale('AR_ae')).toBe('ar-AE');
        expect(intlLocale('de')).toBe('en-AE');
    });

    it('formats AED with two decimals and Latin digits in every language', () => {
        expect(plain(money(35000, 'en'))).toBe('AED 35,000.00');
        expect(plain(money(35000, 'ru'))).toBe('35 000,00 AED');
        expect(plain(money(1234.5, 'ar'))).toBe('1,234.50 AED');
        expect(money(7, 'ar')).not.toMatch(/[٠-٩]/);
    });

    it('formats percentages, integers and decimals', () => {
        expect(plain(percent(0.1, 'en'))).toBe('10%');
        expect(plain(percent(0.2234, 'en'))).toBe('22.34%');
        expect(plain(percent(0.05, 'ru'))).toBe('5 %');
        expect(plain(integer(1825, 'en'))).toBe('1,825');
        expect(plain(decimal(5, 'ru'))).toBe('5,00');
    });

    it('formats ISO dates as Gregorian dates without a time-zone shift', () => {
        expect(date('2026-01-05', 'en')).toBe('5 Jan 2026');
        expect(date('2026-01-05', 'ar')).not.toMatch(/[٠-٩]/);
        expect(date('not a date', 'en')).toBe('not a date');
    });
});

describe('format: plurals and templates', () => {
    const ru = { one: ':count день', few: ':count дня', many: ':count дней' };
    const ar = { zero: ':count يوم', one: 'يوم واحد', two: 'يومان', few: ':count أيام', many: ':count يومًا', other: ':count يوم' };

    it('chooses the CLDR plural form', () => {
        expect(plural({ one: ':count day', other: ':count days' }, 1, 'en')).toBe(':count day');
        expect(plural({ one: ':count day', other: ':count days' }, 2, 'en')).toBe(':count days');
        expect([1, 2, 5, 11, 21, 22, 25].map((n) => plural(ru, n, 'ru'))).toEqual([ru.one, ru.few, ru.many, ru.many, ru.one, ru.few, ru.many]);
        expect([0, 1, 2, 3, 10, 11, 99, 100, 102].map((n) => plural(ar, n, 'ar'))).toEqual([ar.zero, ar.one, ar.two, ar.few, ar.few, ar.many, ar.many, ar.other, ar.other]);
    });

    it('falls back to other, then to the last form', () => {
        expect(plural({ one: 'a', other: 'b' }, 1.5, 'ru')).toBe('b');
        expect(plural({ one: 'a' }, 5, 'en')).toBe('a');
        expect(plural(null, 5, 'en')).toBe('');
    });

    it('fills Laravel-style placeholders and leaves unknown ones', () => {
        expect(fill('Total: :amount (:days)', { amount: 'AED 1.00', days: '2 days' })).toBe('Total: AED 1.00 (2 days)');
        expect(fill(':count дней, :unknown', { count: 5 })).toBe('5 дней, :unknown');
        expect(fill(null)).toBe('');
    });

    it('splits a template into text and isolated parts for the DOM', () => {
        const list = parts('Limited to :amount (:months).', { amount: { bdi: 'AED 240,000.00' }, months: 24 });
        expect(list).toEqual(['Limited to ', { bdi: 'AED 240,000.00' }, ' (', '24', ').']);
        expect(plainText(list)).toBe('Limited to AED 240,000.00 (24).');
        expect(parts(':amount', { amount: { bdi: 'x' } })).toEqual([{ bdi: 'x' }]);
        expect(parts('no :token here', {})).toEqual(['no :token here']);
    });
});

describe('format: numbers typed by the visitor', () => {
    it('reads grouping and decimal separators by language', () => {
        expect(parseNumber('10,000', 'en')).toBe(10000);
        expect(parseNumber('10,000.50', 'en')).toBe(10000.5);
        expect(parseNumber('1,000,000.25', 'ar')).toBe(1000000.25);
        expect(parseNumber('12,5', 'en')).toBe(12.5);
        expect(parseNumber('10 000,50', 'ru')).toBe(10000.5);
        expect(parseNumber('10 000', 'ru')).toBe(10000);
        expect(parseNumber('10,5', 'ru')).toBe(10.5);
        expect(parseNumber('1.000.000,25', 'ru')).toBe(1000000.25);
        expect(parseNumber('1.000.000', 'en')).toBe(1000000);
    });

    it('reads Arabic-Indic digits and separators', () => {
        expect(parseNumber('١٠٬٠٠٠', 'ar')).toBe(10000);
        expect(parseNumber('١٢٫٥', 'ar')).toBe(12.5);
        expect(parseNumber('۱۲۳', 'ar')).toBe(123);
        expect(parseNumber('‏١٠٠٠', 'ar')).toBe(1000);
    });

    it('ignores the currency, keeps the sign and rejects text', () => {
        expect(parseNumber('AED 5,000', 'en')).toBe(5000);
        expect(parseNumber('5000 aed', 'en')).toBe(5000);
        expect(parseNumber('5000 د.إ', 'ar')).toBe(5000);
        expect(parseNumber('-5', 'en')).toBe(-5);
        expect(parseNumber('−5', 'en')).toBe(-5);
        expect(parseNumber('+5', 'en')).toBe(5);
        expect(parseNumber('ten', 'en')).toBeNaN();
        expect(parseNumber('1.2.3', 'en')).toBeNaN();
        expect(parseNumber('1,2,3', 'en')).toBeNaN();
        expect(parseNumber('AED', 'en')).toBeNaN();
    });

    it('returns null for an empty field', () => {
        expect(parseNumber('', 'en')).toBeNull();
        expect(parseNumber('   ', 'en')).toBeNull();
        expect(parseNumber(null, 'en')).toBeNull();
        expect(parseNumber(undefined, 'en')).toBeNull();
    });
});

describe('dates and money helpers', () => {
    it('accepts real calendar dates between 1900 and 2100 only', () => {
        expect(isDate('2024-02-29')).toBe(true);
        expect(isDate('2023-02-29')).toBe(false);
        expect(isDate('1899-12-31')).toBe(false);
        expect(isDate('2101-01-01')).toBe(false);
        expect(isDate(20240101)).toBe(false);
        expect(dayNumber('1970-01-02')).toBe(1);
        expect(daysInclusive('2026-01-01', 'x')).toBeNull();
        expect(daysBetween('2026-01-01', '2026-04-01')).toBe(90);
        expect(daysBetween('2026-04-01', '2026-01-01')).toBe(-90);
    });

    it('rounds, sums and converts to fils', () => {
        expect(round2(NaN)).toBe(0);
        expect(round2(-1.005)).toBe(-1.01);
        expect(round2(1e-9)).toBe(0);
        expect(toFils(12.34)).toBe(1234);
        expect(toFils(0.1 + 0.2)).toBe(30);
        expect(sum(0.1, 0.2)).toBe(0.3);
        expect(sum(333.33, 333.33, 333.34)).toBe(1000);
    });

    it('reads numbers that are already parsed', () => {
        expect(readNumber(null)).toBeNull();
        expect(readNumber('')).toBeNull();
        expect(readNumber(' 12.5 ')).toBe(12.5);
        expect(readNumber('1e3')).toBeNaN();
        expect(readNumber(Infinity)).toBeNaN();
        expect(readNumber(NaN)).toBeNaN();
    });
});
