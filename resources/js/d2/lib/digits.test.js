import { readFileSync } from 'node:fs';
import { afterEach, describe, expect, it } from 'vitest';
import { digitsMode, localiseDigits, numberingSystem, numberLocale } from './digits.js';

// The same cases as tests/Feature/Design/D2/DigitsTest.php (App\Design\D2\Digits::text()): both sides convert alike.
const EASTERN_CASES = [
    ['1. نفهم ما حدث', '١. نفهم ما حدث'],
    ['من 9 صباحًا إلى 6 مساءً', 'من ٩ صباحًا إلى ٦ مساءً'],
    ['1.5 ساعة', '١٫٥ ساعة'],
    ['10,000 درهم', '١٠٬٠٠٠ درهم'],
    ['الساعة 18:00', 'الساعة ١٨:٠٠'],
    ['00/00/2026', '٠٠/٠٠/٢٠٢٦'],
    ['© 2026 سَمِت للاستشارات القانونية', '© ٢٠٢٦ سَمِت للاستشارات القانونية'],
    ['المادة 53 من القانون', 'المادة ٥٣ من القانون'],
    ['۱۲ شهرًا', '١٢ شهرًا'],
    ['AED 10,000.00', 'AED 10,000.00'],
    ['+971 52 466 6191', '+971 52 466 6191'],
    ['اتصل على 00971 52 466 6191 الآن', 'اتصل على 00971 52 466 6191 الآن'],
    ['12 00971 52 466 6191', '١٢ 00971 52 466 6191'],
    ['الحساب 12345678 فقط 3', 'الحساب 12345678 فقط ٣'],
    ['info@summitlegaluae.com 3', 'info@summitlegaluae.com ٣'],
    ['https://laborlawuae.com/a1 2', 'https://laborlawuae.com/a1 ٢'],
    ['www.summitlegaluae.com/ar/ 4', 'www.summitlegaluae.com/ar/ ٤'],
    ['S01 و 5', 'S01 و ٥'],
    ['SLC-261110-ABC123', 'SLC-261110-ABC123'],
    ['المرسوم Federal Decree-Law No. 33 of 2021 رقم 33', 'المرسوم Federal Decree-Law No. 33 of 2021 رقم ٣٣'],
    ['Время 3', 'Время 3'],
    ['10MB و 4', '10MB و ٤'],
    ['&#039;3&amp;4', '&#039;٣&amp;٤'],
    ['بلا أرقام', 'بلا أرقام'],
];

const WESTERN_CASES = [
    ['ساعات العمل: من الاثنين إلى الجمعة، من ٩ صباحًا إلى ٦ مساءً بتوقيت الإمارات.', 'ساعات العمل: من الاثنين إلى الجمعة، من 9 صباحًا إلى 6 مساءً بتوقيت الإمارات.'],
    ['١. نفهم ما حدث', '1. نفهم ما حدث'],
    ['آخر تحديث: ٢٣ أكتوبر ٢٠٢٦', 'آخر تحديث: 23 أكتوبر 2026'],
    ['١٫٥ ساعة', '1.5 ساعة'],
    ['١٠٬٠٠٠ درهم', '10,000 درهم'],
    ['١٬٠٠٠٬٠٠٠٫٥٠ درهم', '1,000,000.50 درهم'],
    ['۱۲ شهرًا', '12 شهرًا'],
    ['1٫5', '1.5'],
    ['٫ وحدها', '٫ وحدها'],
    ['Open now, closes at 6 pm', 'Open now, closes at 6 pm'],
];

describe('localiseDigits: eastern (٠-٩ outside left-to-right runs)', () => {
    it.each(EASTERN_CASES)('%s', (input, expected) => {
        expect(localiseDigits(input, 'eastern')).toBe(expected);
    });

    it('is undone by the western mode', () => {
        for (const [input] of EASTERN_CASES) {
            if (/[۰-۹]/u.test(input)) continue; // Persian digits come back as Western ones
            expect(localiseDigits(localiseDigits(input, 'eastern'), 'western')).toBe(input);
        }
    });
});

describe('localiseDigits: western (0-9 everywhere)', () => {
    it.each(WESTERN_CASES)('%s', (input, expected) => {
        expect(localiseDigits(input, 'western')).toBe(expected);
    });

    it('accepts null and numbers', () => {
        expect(localiseDigits(null, 'western')).toBe('');
        expect(localiseDigits(2026, 'eastern')).toBe('٢٠٢٦');
    });
});

describe('the page setting', () => {
    afterEach(() => {
        delete globalThis.document;
    });

    const page = (digits) => {
        globalThis.document = { documentElement: { dataset: digits ? { digits } : {} } };
    };

    it('reads <html data-digits>; western without it (English and Russian pages)', () => {
        page('eastern');
        expect(digitsMode()).toBe('eastern');
        page('western');
        expect(digitsMode()).toBe('western');
        page(null);
        expect(digitsMode()).toBe('western');
        page('arabic');
        expect(digitsMode()).toBe('western');
    });

    it('defaults to the page in localiseDigits', () => {
        page('eastern');
        expect(localiseDigits('9 صباحًا')).toBe('٩ صباحًا');
        page(null);
        expect(localiseDigits('٩ صباحًا')).toBe('9 صباحًا');
    });

    it('works without a document (server-side rendering, tests)', () => {
        expect(digitsMode()).toBe('western');
    });
});

describe('browser support', () => {
    it('uses no regular-expression lookbehind (Safari before 16.4 cannot parse it; the hours module loads eagerly)', () => {
        for (const file of ['./digits.js', '../modules/hours.js']) {
            const source = readFileSync(new URL(file, import.meta.url), 'utf8');
            expect(source, file).not.toMatch(/\(\?<[=!]/);
        }
    });
});

describe('Intl locales with the page digits', () => {
    it('uses the Arabic-Indic numbering system only for Arabic in eastern mode', () => {
        expect(numberingSystem('ar', 'eastern')).toBe('arab');
        expect(numberingSystem('ar', 'western')).toBe('latn');
        expect(numberingSystem('ar-AE', 'eastern')).toBe('arab');
        expect(numberingSystem('en', 'eastern')).toBe('latn');
        expect(numberingSystem('ru', 'eastern')).toBe('latn');
    });

    it('builds a locale tag for Intl', () => {
        expect(numberLocale('ar', 'eastern')).toBe('ar-u-nu-arab');
        expect(numberLocale('ar', 'western')).toBe('ar-u-nu-latn');
        expect(numberLocale('ru', 'eastern')).toBe('ru-u-nu-latn');
        expect(numberLocale('ar-u-nu-arab', 'western')).toBe('ar-u-nu-latn');
    });

    it('formats like the server converts', () => {
        const eastern = new Intl.NumberFormat(numberLocale('ar', 'eastern'), { maximumFractionDigits: 2 });
        expect(eastern.format(1.5)).toBe(localiseDigits('1.5', 'eastern'));
        expect(eastern.format(10000)).toBe(localiseDigits('10,000', 'eastern'));
        const western = new Intl.NumberFormat(numberLocale('ar', 'western'), { maximumFractionDigits: 2 });
        expect(western.format(1.5)).toBe('1.5');
        expect(western.format(10000)).toBe('10,000');
    });
});
