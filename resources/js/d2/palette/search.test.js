import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import { createIndex, fuzzyFor, groupHits, indexTerms, queryTerm, queryTerms, search, stem, wordMatches } from './search.js';

// The committed index files (php artisan summit:search-index): real approved content.
const load = (locale) => JSON.parse(readFileSync(fileURLToPath(new URL(`../../../../public/search/${locale}.json`, import.meta.url)), 'utf8'));
const indexes = Object.fromEntries(['en', 'ar', 'ru'].map((locale) => [locale, createIndex(load(locale).items)]));
const ids = (result) => result.groups.flatMap((group) => group.items.map((item) => item.id));
const types = (result) => result.groups.map((group) => group.type);

describe('stem', () => {
    it('English plurals, -ing and -ed', () => {
        expect(stem('cheques')).toBe('cheque');
        expect(stem('taxes')).toBe('tax');
        expect(stem('parties')).toBe('party');
        expect(stem('business')).toBe('business');
        expect(stem('status')).toBe('status');
        expect(stem('recovering')).toBe('recover');
        expect(stem('dishonoured')).toBe('dishonour');
        expect(stem('employer’s')).toBe('employer');
    });

    it('Russian case endings, keeping at least three letters', () => {
        expect(stem('зарплата')).toBe('зарплат');
        expect(stem('зарплаты')).toBe('зарплат');
        expect(stem('договоров')).toBe('договор');
        expect(stem('трудовой')).toBe('трудов');
        expect(stem('чеки')).toBe('чек');
        expect(stem('чек')).toBe('чек');
    });

    it('Arabic article, clitics and a few suffixes', () => {
        expect(stem('الشيك')).toBe('شيك');
        expect(stem('بالراتب')).toBe('راتب');
        expect(stem('والعقود')).toBe('عقود');
        expect(stem('الشركات')).toBe('شرك');
        expect(stem('الموظفين')).toBe('موظف');
        // never below two letters after a prefix or three after a suffix
        expect(stem('الى')).toBe('الى');
        expect(stem('مده')).toBe('مده');
    });

    it('Arabic relative adjective ـيّة like the masculine ـي, when four letters remain', () => {
        expect(queryTerm('العقارية')).toBe('عقار');
        expect(queryTerm('العقاري')).toBe('عقار');
        expect(queryTerm('الجزائية')).toBe('جزائ');
        expect(queryTerm('القانونية')).toBe('قانون');
        // shorter words keep the plain rule: نها would match منها and عنها in the FAQ filter
        expect(queryTerm('نهاية')).toBe('نهاي');
        expect(queryTerm('المالية')).toBe('مالي');
        expect(queryTerm('قضية')).toBe('قضي');
    });

    it('leaves digits and other scripts alone', () => {
        expect(stem('2021')).toBe('2021');
    });
});

describe('term processing', () => {
    it('indexes the normalised word and its stem', () => {
        expect(indexTerms('Cheques')).toEqual(['cheques', 'cheque']);
        expect(indexTerms('Ёлка')).toEqual(['елка', 'елк']);
        expect(indexTerms('الإيجار')).toEqual(['الايجار', 'ايجار']);
        expect(indexTerms('')).toBeNull();
    });

    it('also indexes an Arabic word after the conjunction و by the rest, when four letters remain', () => {
        expect(indexTerms('وقضايا')).toEqual(['وقضايا', 'قضايا']);
        expect(indexTerms('وجزائية')).toEqual(['وجزائيه', 'وجزائ', 'جزائ']);
        // a word whose own first letter is و is not split
        expect(indexTerms('وجود')).toBe('وجود');
        expect(indexTerms('وعود')).toBe('وعود');
    });

    it('reduces a query word to its stem and ignores single letters', () => {
        expect(queryTerm('Cheques')).toBe('cheque');
        expect(queryTerm('a')).toBeNull();
        expect(queryTerm('و')).toBeNull();
        expect(queryTerm('5')).toBe('5');
        expect(queryTerms('Bounced, cheques!')).toEqual(['bounc', 'cheque']);
    });

    it('matches words of the original text for highlighting', () => {
        const terms = queryTerms('cheque');
        expect(wordMatches('Cheques', terms)).toBe(true);
        expect(wordMatches('Debt', terms)).toBe(false);
        expect(wordMatches('الشيكات', queryTerms('شيك'))).toBe(true);
        expect(wordMatches('Зарплаты', queryTerms('зарплата'))).toBe(true);
    });
});

describe('search: English', () => {
    const en = indexes.en;

    it('finds the cheque service, guide and questions', () => {
        const result = search(en, 'cheque');
        expect(ids(result)).toContain('S01');
        expect(ids(result)).toContain('G03');
        expect(types(result)).toContain('faq');
    });

    it('matches plurals and word starts', () => {
        expect(ids(search(en, 'bounced cheques'))).toContain('faq-08');
        expect(ids(search(en, 'dishonoured cheques'))).toContain('G03');
        expect(ids(search(en, 'gratu'))).toContain('faq-42');
        expect(ids(search(en, 'tenancy'))).toContain('S05');
    });

    it('tolerates a typo in a long word', () => {
        expect(ids(search(en, 'gratuiity'))).toContain('faq-42');
    });

    it('falls back to partial matches for a sentence', () => {
        const result = search(en, 'my employer did not pay my salary');
        expect(result.count).toBeGreaterThan(0);
        expect(result.partial).toBe(true);
        expect(ids(result)).toContain('faq-41');
    });

    it('returns nothing for gibberish or an empty query', () => {
        expect(search(en, 'qzxqzx').count).toBe(0);
        expect(search(en, '   ').count).toBe(0);
        expect(search(en, 'a').terms).toEqual([]);
    });

    it('groups by type, best group first, with caps', () => {
        const result = search(en, 'salary');
        expect(result.groups.length).toBeGreaterThan(1);
        for (const group of result.groups) expect(group.items.length).toBeLessThanOrEqual({ service: 5, guide: 4, faq: 6, case: 3 }[group.type]);
        expect(result.count).toBeLessThanOrEqual(16);
        const bests = result.groups.map((group) => group.best);
        expect([...bests].sort((a, b) => b - a)).toEqual(bests);
    });
});

describe('search: Arabic', () => {
    const ar = indexes.ar;

    it('finds cheques with or without the article', () => {
        expect(ids(search(ar, 'شيك'))).toContain('S01');
        expect(ids(search(ar, 'الشيكات'))).toContain('S01');
    });

    it('ignores hamza, taa marbuta and tashkeel differences', () => {
        const plain = ids(search(ar, 'مكافاه نهايه الخدمه'));
        expect(plain).toContain('faq-42');
        expect(ids(search(ar, 'مُكافأة نهاية الخِدمة'))).toEqual(plain);
    });

    it('finds tenancy', () => {
        expect(ids(search(ar, 'الإيجار'))).toContain('S05');
    });

    it('finds Latin terms inside Arabic content', () => {
        expect(ids(search(ar, 'USDT')).length).toBeGreaterThan(0);
    });

    // QA round 1: "زرافة فضائية" (space giraffe) returned faq-01 because فضائية is one dot away from
    // قضائية (judicial) and the sentence fallback accepted that near-miss as half of the query.
    it('finds nothing for nonsense, also when one word is a dot away from a real one', () => {
        expect(search(ar, 'زرافة فضائية').count).toBe(0);
        expect(search(ar, 'فضائية').count).toBe(0);
        expect(search(ar, 'زرافة').count).toBe(0);
    });

    it('never matches a different word one letter away (no typo tolerance in Arabic)', () => {
        // تحصيل (collection) must not bring the transfer (تحويل) and analysis (تحليل) documents
        const collection = ids(search(ar, 'تحصيل'));
        expect(collection).toContain('S01');
        expect(collection).toContain('G02');
        expect(collection).not.toContain('S03');
        expect(collection).not.toContain('G06');
        expect(collection).not.toContain('S02');
    });

    it('meets the relative adjective and its noun, and words joined to و', () => {
        // العقارية (real-estate, adj.) finds the property documents that only say عقار
        expect(ids(search(ar, 'العقارية'))).toContain('G04');
        expect(ids(search(ar, 'الضريبية'))).toContain('G09');
        // S01's title says "وقضايا الشيكات"
        expect(ids(search(ar, 'قضايا الشيكات'))).toContain('S01');
    });
});

describe('search: Russian', () => {
    const ru = indexes.ru;

    it('meets inflected forms', () => {
        expect(ids(search(ru, 'зарплата'))).toContain('G01');
        expect(ids(search(ru, 'чеки'))).toContain('S01');
        expect(ids(search(ru, 'аренда'))).toContain('S05');
    });

    it('treats ё as е', () => {
        expect(ids(search(ru, 'расчет'))).toEqual(ids(search(ru, 'расчёт')));
    });

    it('tolerates a typo in a long word, and finds nothing for nonsense', () => {
        expect(ids(search(ru, 'зарплатта'))).toContain('G01');
        expect(search(ru, 'жираф космический').count).toBe(0);
    });
});

describe('typo tolerance and the sentence fallback', () => {
    it('one edit from five letters in Latin and Cyrillic words, never in Arabic ones', () => {
        expect(fuzzyFor('gratuiity')).toBe(0.2);
        expect(fuzzyFor('зарплатт')).toBe(0.2);
        expect(fuzzyFor('cheq')).toBe(false);
        expect(fuzzyFor('فضائ')).toBe(false);
        expect(fuzzyFor('تحصيل')).toBe(false);
        expect(fuzzyFor(undefined)).toBe(false);
    });

    it('a near-miss never counts as one of the matched words of a sentence', () => {
        const en = indexes.en;
        expect(search(en, 'zebra spaceship').count).toBe(0);
        // "salery" is one letter from "salary": typo tolerance finds it alone, not as half of a sentence
        expect(ids(search(en, 'salery'))).toContain('faq-41');
        expect(search(en, 'zebra salery').count).toBe(0);
        const exact = search(en, 'zebra salary');
        expect(exact.partial).toBe(true);
        expect(ids(exact)).toContain('faq-41');
        // a real sentence with a typo still finds its question through the words spelt right
        expect(ids(search(en, 'my employer did not pay my salery'))).toContain('faq-41');
    });
});

describe('groupHits', () => {
    it('drops unknown types and keeps the order of scores', () => {
        const groups = groupHits([
            { id: 'a', type: 'faq', score: 9 },
            { id: 'b', type: 'service', score: 5 },
            { id: 'c', type: 'faq', score: 4 },
            { id: 'x', type: 'page', score: 99 },
        ]);
        expect(groups.map((g) => g.type)).toEqual(['faq', 'service']);
        expect(groups[0].items.map((i) => i.id)).toEqual(['a', 'c']);
    });
});
