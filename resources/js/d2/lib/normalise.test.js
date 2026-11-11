import { describe, expect, it } from 'vitest';
import normaliseDefault, { normalise } from './normalise.js';

describe('normalise: basics', () => {
    it('lower-cases', () => {
        expect(normalise('Tenancy CONTRACT')).toBe('tenancy contract');
    });

    it('returns an empty string for null, undefined and empty input', () => {
        expect(normalise(null)).toBe('');
        expect(normalise(undefined)).toBe('');
        expect(normalise('')).toBe('');
    });

    it('stringifies numbers', () => {
        expect(normalise(2026)).toBe('2026');
    });

    it('keeps spaces, digits and punctuation', () => {
        expect(normalise('Law No. 33 of 2021, Art. 51')).toBe('law no. 33 of 2021, art. 51');
    });

    it('is idempotent', () => {
        const once = normalise('Ёлка · Café · أ\u064Eح\u0652ك\u064Eام\u064F الإيجار\u0650');
        expect(normalise(once)).toBe(once);
    });

    it('exports the same function as default', () => {
        expect(normaliseDefault).toBe(normalise);
    });
});

describe('normalise: Latin diacritics', () => {
    it('strips accents, umlauts, cedillas and tildes', () => {
        expect(normalise('Café résumé naïve façade São Paulo')).toBe('cafe resume naive facade sao paulo');
        expect(normalise('Müller Ångström Šimek')).toBe('muller angstrom simek');
    });

    it('handles decomposed input the same as precomposed input', () => {
        expect(normalise('Cafe\u0301')).toBe(normalise('Caf\u00e9'));
    });

    it('maps letters that do not decompose', () => {
        expect(normalise('Straße Æon Œuvre Øre Łódź Đorđe')).toBe('strasse aeon oeuvre ore lodz dorde');
    });

    it('treats the Turkish dotted and dotless i as i', () => {
        expect(normalise('İstanbul')).toBe('istanbul');
        expect(normalise('ılık')).toBe('ilik');
    });
});

describe('normalise: Russian', () => {
    it('turns ё into е in both cases', () => {
        expect(normalise('Ёлка')).toBe('елка');
        expect(normalise('всё учтём')).toBe('все учтем');
        expect(normalise('ЁЖ')).toBe('еж');
    });

    it('keeps й as its own letter', () => {
        expect(normalise('Трудовой договор')).toBe('трудовой договор');
        expect(normalise('ЙОД')).toBe('йод');
        expect(normalise('мой')).not.toBe(normalise('мои'));
    });

    it('strips stress marks', () => {
        expect(normalise('сло\u0301во')).toBe('слово');
        expect(normalise('за\u0301мок')).toBe('замок');
    });

    it('matches a query typed without ё against text with ё', () => {
        expect(normalise('Расчёт выплат').includes(normalise('расчет'))).toBe(true);
    });
});

describe('normalise: Arabic', () => {
    it('folds the hamza and madda alef forms into a bare alef', () => {
        expect(normalise('أحمد')).toBe('احمد');
        expect(normalise('إيجار')).toBe('ايجار');
        expect(normalise('آخر')).toBe('اخر');
        expect(normalise('ٱلعقد')).toBe('العقد');
    });

    it('handles decomposed hamza forms like precomposed ones', () => {
        // alef + hamza above (U+0654) and alef + hamza below (U+0655)
        expect(normalise('\u0627\u0654\u062D\u0645\u062F')).toBe('احمد');
        expect(normalise('\u0627\u0655\u064A\u062C\u0627\u0631')).toBe('ايجار');
    });

    it('keeps hamza on waw and yeh (not part of the contract)', () => {
        expect(normalise('مسؤولية')).toBe('مسؤوليه');
        expect(normalise('رئيس')).toBe('رئيس');
    });

    it('folds taa marbuta into haa', () => {
        expect(normalise('شركة')).toBe('شركه');
        expect(normalise('الاستشارة القانونية')).toBe('الاستشاره القانونيه');
    });

    it('folds alef maqsura (and the Persian yeh) into yeh', () => {
        expect(normalise('دعوى')).toBe('دعوي');
        expect(normalise('على')).toBe('علي');
        expect(normalise('\u0639\u0644\u06CC')).toBe('علي');
    });

    it('folds the Persian keheh into kaf', () => {
        expect(normalise('\u0634\u0631\u06A9\u0647')).toBe('شركه');
    });

    it('strips tashkeel: harakat, tanween, shadda, sukun and superscript alef', () => {
        expect(normalise('أ\u064Eح\u0652ك\u064Eام\u064F الإ\u0650يج\u064Eار\u0650')).toBe('احكام الايجار');
        expect(normalise('قانون\u064D')).toBe('قانون');
        expect(normalise('شيك\u064Bا')).toBe('شيكا');
        expect(normalise('م\u064Fد\u064E\u0651ة')).toBe('مده');
        expect(normalise('ه\u0670ذا')).toBe('هذا');
    });

    it('strips tatweel', () => {
        expect(normalise('عقــــد')).toBe('عقد');
        expect(normalise('الإيجـار')).toBe('الايجار');
    });

    it('maps Arabic-Indic and Persian digits to Western digits', () => {
        expect(normalise('٠١٢٣٤٥٦٧٨٩')).toBe('0123456789');
        expect(normalise('۰۱۲۳۴۵۶۷۸۹')).toBe('0123456789');
        expect(normalise('القانون رقم ٣٣ لسنة ٢٠٢١')).toBe('القانون رقم 33 لسنه 2021');
    });

    it('lets a plain query find vocalised text with hamza and taa marbuta', () => {
        const indexed = normalise('م\u064Fطال\u064Eب\u064Eة\u064C ب\u0650م\u064Fكاف\u064Eأ\u064Eة\u0650 ن\u0650هاي\u064Eة\u0650 الخ\u0650د\u0652م\u064Eة\u0650');
        expect(indexed.includes(normalise('مكافاه نهايه الخدمه'))).toBe(true);
        expect(indexed.includes(normalise('مكافأة نهاية الخدمة'))).toBe(true);
    });

    it('leaves Latin runs inside Arabic text intact apart from case', () => {
        expect(normalise('تحويل USDT عبر TxID')).toBe('تحويل usdt عبر txid');
    });
});
