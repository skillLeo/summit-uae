// Legal Knowledge Centre search and topic filter (components/resources-kit.js; P03 designer note: "Search all
// nine guide titles and their full content. Filter both cards and associated sections consistently").
// Lives outside components/*.js on purpose: app.js eagerly imports every components/*.js file.
import { describe, expect, it } from 'vitest';
import { guideText, isFiltering, mostVisible, resultParts, textMatches, topicLabel, visibleGuides } from '../resources-kit.js';

const en = [
    { code: 'G01', topic: 'gc-01', text: guideText('Unpaid Salary and How to Document Your Claim', 'Keep payslips, bank statements and correspondence about unpaid salaries.') },
    { code: 'G03', topic: 'gc-01', text: guideText('Dishonoured Cheques and Enforcement Options', 'A cheque returned by the bank, the return memo and enforcement.') },
    { code: 'G05', topic: 'gc-02', text: guideText('Wills for Non-Muslim Residents', 'Choosing a registration route for your will.') },
    { code: 'G08', topic: 'gc-03', text: guideText('How to Check the Accuracy of a Legal Translation', 'Certified translation and attestation; DIFC and ADGM.') },
];

const ar = [
    { code: 'G01', topic: 'gc-01', text: guideText('الرواتب المتأخرة وكيفية توثيق المطالبة', 'احتفظ بكشوف الحساب والمراسلات بشأن الراتب.') },
    { code: 'G03', topic: 'gc-01', text: guideText('الشيكات المرتجعة وخيارات التنفيذ', 'الشيك المرتجع وإفادة البنك.') },
    { code: 'G05', topic: 'gc-02', text: guideText('وصايا غير المسلمين', 'اختيار جهة تسجيل الوصيّة.') },
];

const ru = [
    { code: 'G01', topic: 'gc-01', text: guideText('Невыплаченная зарплата', 'Сохраните расчётные листки и выписки о заработной плате.') },
    { code: 'G05', topic: 'gc-02', text: guideText('Завещания для немусульман', 'Выбор способа регистрации завещания.') },
];

describe('guideText', () => {
    it('keeps every normalised word and its light stem, padded for whole-text matching', () => {
        const text = guideText('Salaries, CHEQUES!', 'Café');
        expect(text.startsWith(' ') && text.endsWith(' ')).toBe(true);
        expect(text).toContain(' salaries ');
        expect(text).toContain(' salary ');
        expect(text).toContain(' cheque ');
        expect(text).toContain(' cafe ');
    });

    it('normalises Arabic letter forms and strips tashkeel', () => {
        const text = guideText('الوصيّة المُسجَّلة');
        expect(text).toContain('الوصيه');
        expect(text).not.toMatch(/[ً-ٟ]/);
    });

    it('treats empty and missing parts as no text', () => {
        expect(guideText()).toBe('  ');
        expect(guideText(null, undefined, '')).toBe('  ');
    });
});

describe('visibleGuides', () => {
    it('returns every guide in order when nothing is filtered', () => {
        expect(visibleGuides(en)).toEqual(['G01', 'G03', 'G05', 'G08']);
        expect(visibleGuides(en, { query: '   ', topic: '' })).toEqual(['G01', 'G03', 'G05', 'G08']);
    });

    it('finds inflected forms in titles and in the full text', () => {
        expect(visibleGuides(en, { query: 'salary' })).toEqual(['G01']);
        expect(visibleGuides(en, { query: 'Cheques' })).toEqual(['G03']);
        expect(visibleGuides(en, { query: 'attestation' })).toEqual(['G08']);
    });

    it('requires every query word', () => {
        expect(visibleGuides(en, { query: 'cheque bank' })).toEqual(['G03']);
        expect(visibleGuides(en, { query: 'cheque will' })).toEqual([]);
    });

    it('filters by topic, alone or with a query', () => {
        expect(visibleGuides(en, { topic: 'gc-01' })).toEqual(['G01', 'G03']);
        expect(visibleGuides(en, { topic: 'gc-01', query: 'cheque' })).toEqual(['G03']);
        expect(visibleGuides(en, { topic: 'gc-02', query: 'cheque' })).toEqual([]);
    });

    it('matches Latin codes inside the text', () => {
        expect(visibleGuides(en, { query: 'DIFC' })).toEqual(['G08']);
    });

    it('works in Arabic with the article and alef forms', () => {
        expect(visibleGuides(ar, { query: 'راتب' })).toEqual(['G01']);
        expect(visibleGuides(ar, { query: 'الشيكات' })).toEqual(['G03']);
        expect(visibleGuides(ar, { query: 'وصية' })).toEqual(['G05']);
    });

    it('works in Russian with case endings', () => {
        expect(visibleGuides(ru, { query: 'зарплату' })).toEqual(['G01']);
        expect(visibleGuides(ru, { query: 'завещание' })).toEqual(['G05']);
    });

    it('ignores non-string filter values (never trusts its input)', () => {
        expect(visibleGuides(en, { query: ['cheque'], topic: { x: 1 } })).toEqual(['G01', 'G03', 'G05', 'G08']);
    });
});

describe('isFiltering / textMatches', () => {
    it('is filtering with a query word or a topic only', () => {
        expect(isFiltering({})).toBe(false);
        expect(isFiltering({ query: ' ' })).toBe(false);
        expect(isFiltering({ query: 'a' })).toBe(false);
        expect(isFiltering({ query: 'will' })).toBe(true);
        expect(isFiltering({ topic: 'gc-03' })).toBe(true);
    });

    it('matches when every term is in the text', () => {
        expect(textMatches(' a b c ', ['a', 'c'])).toBe(true);
        expect(textMatches(' a b c ', ['a', 'd'])).toBe(false);
        expect(textMatches(' a ', [])).toBe(true);
    });
});

describe('resultParts', () => {
    const strings = { results: { 0: '__N__ guides found', 1: '__N__ guide found', 3: 'عُثر على __N__ أدلة' }, none: 'We could not find a guide matching your search.' };

    it('isolates the count as its own part', () => {
        expect(resultParts(strings, 1)).toEqual({ empty: false, parts: [{ text: '1', number: true }, { text: ' guide found', number: false }] });
        expect(resultParts(strings, 3)).toEqual({ empty: false, parts: [{ text: 'عُثر على ', number: false }, { text: '3', number: true }, { text: ' أدلة', number: false }] });
    });

    it('shows the approved message for no result', () => {
        expect(resultParts(strings, 0)).toEqual({ empty: true, parts: [{ text: strings.none, number: false }] });
    });

    it('falls back to the bare number when a plural form is missing', () => {
        expect(resultParts(strings, 7).parts).toEqual([{ text: '7', number: false }]);
        expect(resultParts({}, -2).empty).toBe(true);
    });
});

describe('mostVisible', () => {
    it('picks the largest ratio, ties keep the first', () => {
        expect(mostVisible([0, 0.4, 0.9, 0.2])).toBe(2);
        expect(mostVisible([0.5, 0.5])).toBe(0);
        expect(mostVisible([])).toBe(0);
    });
});

// The phones' topic picker (D30 / D41 rule: no half-cut label): its box shows the chosen approved name whole.
describe('topicLabel', () => {
    const options = [
        { value: '', textContent: 'All Topics' },
        { value: 'gc-01', textContent: '  Employment and Financial\n   Claims ' },
        { value: 'gc-03', textContent: 'Цифровые доказательства и организация бизнеса' },
    ];

    it('prints the chosen option in full, whitespace collapsed', () => {
        expect(topicLabel(options, 'gc-01')).toBe('Employment and Financial Claims');
        expect(topicLabel(options, 'gc-03')).toBe('Цифровые доказательства и организация бизнеса');
    });

    it('falls back to the first option (all topics) for an empty or unknown value', () => {
        expect(topicLabel(options, '')).toBe('All Topics');
        expect(topicLabel(options, 'gc-99')).toBe('All Topics');
        expect(topicLabel(options, null)).toBe('All Topics');
    });

    it('reads an options collection and copes with none', () => {
        const collection = { 0: options[0], 1: options[1], length: 2 };
        expect(topicLabel(collection, 'gc-01')).toBe('Employment and Financial Claims');
        expect(topicLabel([], 'gc-01')).toBe('');
        expect(topicLabel(undefined)).toBe('');
    });
});
