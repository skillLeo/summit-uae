import { describe, expect, it } from 'vitest';
import { filterItems, filterTerms, haystack, markRuns, matches } from '../faq-filter.js';

// Lives outside components/*.js on purpose: app.js eagerly imports every components/*.js file.

const items = [
    { id: 'faq-08', text: haystack('8. Is every bounced cheque a criminal matter?', 'Not necessarily. The reason for dishonour matters.') },
    { id: 'faq-41', text: haystack('41. What should I do if my employer has not paid my salary?', 'Gather your contract, payslips and bank statements.') },
    { id: 'faq-42', text: haystack('42. How is end-of-service gratuity calculated?', 'Gratuity is based on the last basic wage.') },
];

const ar = [
    { id: 'faq-08', text: haystack('8. هل كل شيك مرتجع مسألة جزائية؟', 'ليس بالضرورة.') },
    { id: 'faq-42', text: haystack('42. كيف تُحسب مكافأة نهاية الخدمة؟', 'تُحسب على أساس آخر أجر أساسي.') },
];

const ru = [
    { id: 'faq-41', text: haystack('41. Что делать, если работодатель не выплатил зарплату?', 'Соберите договор и расчётные листки.') },
    { id: 'faq-26', text: haystack('26. Разрешены ли операции с криптовалютой в ОАЭ?', 'Да, при соблюдении требований.') },
];

describe('filterItems', () => {
    it('keeps everything without a query', () => {
        expect(filterItems(items, '')).toEqual(['faq-08', 'faq-41', 'faq-42']);
        expect(filterItems(items, '  ')).toEqual(['faq-08', 'faq-41', 'faq-42']);
        // a single letter is not a query yet
        expect(filterItems(items, 'a')).toEqual(['faq-08', 'faq-41', 'faq-42']);
    });

    it('matches questions and answers, case-insensitively', () => {
        expect(filterItems(items, 'Cheque')).toEqual(['faq-08']);
        expect(filterItems(items, 'payslips')).toEqual(['faq-41']);
        expect(filterItems(items, 'GRATUITY')).toEqual(['faq-42']);
    });

    it('requires every word (AND)', () => {
        expect(filterItems(items, 'salary employer')).toEqual(['faq-41']);
        expect(filterItems(items, 'salary cheque')).toEqual([]);
    });

    it('meets plurals and word parts', () => {
        expect(filterItems(items, 'cheques')).toEqual(['faq-08']);
        expect(filterItems(items, 'calcul')).toEqual(['faq-42']);
    });

    it('returns nothing for an unknown word', () => {
        expect(filterItems(items, 'qzxqzx')).toEqual([]);
    });

    it('Arabic: hamza, taa marbuta, tashkeel and the article do not matter', () => {
        expect(filterItems(ar, 'الشيك')).toEqual(['faq-08']);
        expect(filterItems(ar, 'مكافاه')).toEqual(['faq-42']);
        expect(filterItems(ar, 'مُكافأة الخدمة')).toEqual(['faq-42']);
        expect(filterItems(ar, 'اجر')).toEqual(['faq-42']);
    });

    it('Arabic: the relative adjective finds its noun, a short stem never matches inside another word', () => {
        const property = [{ id: 'faq-35', text: haystack('35. ما أول خطوة عند تأخر تسليم العقار؟', '') }];
        expect(filterItems(property, 'العقارية')).toEqual(['faq-35']);
        // نهاية stays نهاي (not نها), so "عنها" does not match
        expect(filterItems([{ id: 'x', text: haystack('ماذا يترتب عنها؟', '') }], 'نهاية')).toEqual([]);
    });

    it('Russian: endings and ё do not matter', () => {
        expect(filterItems(ru, 'зарплата')).toEqual(['faq-41']);
        expect(filterItems(ru, 'расчет')).toEqual(['faq-41']);
        expect(filterItems(ru, 'криптовалюта')).toEqual(['faq-26']);
    });
});

describe('matches / filterTerms', () => {
    it('an empty term list matches', () => {
        expect(matches('anything', [])).toBe(true);
    });

    it('drops punctuation', () => {
        expect(filterTerms('salary, cheque!')).toEqual(['salary', 'cheque']);
    });
});

describe('markRuns', () => {
    it('marks matching words only', () => {
        expect(markRuns('Is every bounced cheque a criminal matter?', filterTerms('cheque'))).toEqual([
            { text: 'Is every bounced ', mark: false },
            { text: 'cheque', mark: true },
            { text: ' a criminal matter?', mark: false },
        ]);
    });

    it('keeps the text unchanged', () => {
        const text = '42. How is end-of-service gratuity calculated?';
        expect(markRuns(text, filterTerms('gratuity calc')).map((run) => run.text).join('')).toBe(text);
    });

    it('marks Arabic words with the article', () => {
        const runs = markRuns('ما هي الشيكات المرتجعة؟', filterTerms('شيك'));
        expect(runs.filter((run) => run.mark).map((run) => run.text)).toEqual(['الشيكات']);
    });

    it('without terms returns one plain run', () => {
        expect(markRuns('abc', [])).toEqual([{ text: 'abc', mark: false }]);
    });
});
