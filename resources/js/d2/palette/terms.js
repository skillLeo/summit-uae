/**
 * Search terms shared by the command palette (palette/search.js, with MiniSearch) and the FAQ filter
 * (components/faq-filter.js, plain substring matching): the shared normalise() (contract F) plus a light
 * stemmer, so inflected words meet. Arabic: the article and clitic prefixes and a few suffixes; Russian:
 * case and verb endings; English: plural, -ing, -ed. Every word is stemmed by its own script, so Latin
 * terms inside Arabic or Russian text work too.
 *
 * Pure and small (no MiniSearch here, so the FAQ filter can use it from the main bundle).
 */
import { normalise } from '../lib/normalise.js';

const ARABIC = /[\u0600-\u06FF]/;
const CYRILLIC = /[\u0400-\u04FF]/;
const TOKEN_SPLIT = /[\n\r\p{Z}\p{P}]+/u; // MiniSearch's default tokenizer

const AR_PREFIXES = ['وال', 'بال', 'كال', 'فال', 'لل', 'ال'];
const AR_SUFFIXES = ['ات', 'ون', 'ين', 'ها', 'هم', 'ه', 'ي'];
// The feminine relative adjective ـيّة (normalised يه) meets its noun like the masculine ـي does:
// العقارية → عقار, الجزائية → جزائ, القانونية → قانون. Only when four letters remain: a three-letter stem
// (نهاية → نها) would match function words (منها، عنها) in the FAQ filter's substring test, so those keep
// the plain rule (نهاية → نهاي). The palette has no typo tolerance for Arabic (search.js fuzzyFor), so the
// stemmer is what brings these forms together.
const AR_NISBA = 'يه';
const AR_NISBA_MIN = 4;
const RU_ENDINGS = [
    'иями', 'ями', 'ами', 'иях', 'ого', 'его', 'ому', 'ему', 'ыми', 'ими', 'ией', 'ием', 'иям', 'ться', 'тся',
    'ешь', 'ете', 'ует', 'ают', 'яют', 'уют', 'ах', 'ях', 'ов', 'ев', 'ей', 'ой', 'ий', 'ый', 'ая', 'яя', 'ое',
    'ее', 'ие', 'ые', 'ом', 'ем', 'ам', 'ям', 'ых', 'их', 'ую', 'юю', 'ию', 'ия', 'ии', 'ть', 'ет', 'ит',
    'ут', 'ют', 'ат', 'ят', 'а', 'я', 'о', 'е', 'ы', 'и', 'у', 'ю', 'ь', 'й',
].sort((a, b) => b.length - a.length);

function stemArabic(term) {
    let t = term;
    for (const prefix of AR_PREFIXES) {
        if (t.startsWith(prefix) && t.length - prefix.length >= 2) {
            t = t.slice(prefix.length);
            break;
        }
    }
    if (t.endsWith(AR_NISBA) && t.length - AR_NISBA.length >= AR_NISBA_MIN) return t.slice(0, -AR_NISBA.length);
    for (const suffix of AR_SUFFIXES) {
        if (t.endsWith(suffix) && t.length - suffix.length >= 3) {
            t = t.slice(0, -suffix.length);
            break;
        }
    }
    return t;
}

function stemRussian(term) {
    for (const ending of RU_ENDINGS) {
        if (term.endsWith(ending) && term.length - ending.length >= 3) return term.slice(0, -ending.length);
    }
    return term;
}

function stemEnglish(term) {
    let t = term.replace(/['’]s$/, '');
    if (t.length > 4 && t.endsWith('ies')) return `${t.slice(0, -3)}y`;
    if (t.endsWith('sses')) return t.slice(0, -2);
    if (t.length > 4 && /(x|ch|sh|z)es$/.test(t)) return t.slice(0, -2);
    if (t.length > 3 && t.endsWith('s') && !/(ss|us|is)$/.test(t)) t = t.slice(0, -1);
    else if (t.length > 5 && t.endsWith('ing')) t = t.slice(0, -3);
    else if (t.length > 4 && t.endsWith('ed')) t = t.slice(0, -2);
    return t;
}

/** Light stem of an already normalised word, by script (Latin runs inside Arabic or Russian text included). */
export function stem(term) {
    if (!term) return term;
    if (ARABIC.test(term)) return stemArabic(term);
    if (CYRILLIC.test(term)) return stemRussian(term);
    if (/^[a-z]/.test(term)) return stemEnglish(term);
    return term;
}

// و "and" is written onto the next word (وقضايا، ومسائل، وتنظيم). Only when four letters remain, so words
// whose own first letter is و (وجود، وعود، وكيل) are not split.
const AR_AND = 'و';
const AR_AND_MIN = 4;

/**
 * Index side: the normalised word and its stem; an Arabic word that may carry the conjunction و is also
 * indexed by the stem of the rest, so "قضايا" finds "تحصيل الديون وقضايا الشيكات". Index side only: a
 * query word keeps its و, and the FAQ filter's substring test finds the joined form by itself.
 */
export function indexTerms(term) {
    const t = normalise(term);
    if (!t) return null;
    const s = stem(t);
    const rest = t.startsWith(AR_AND) && t.length - AR_AND.length >= AR_AND_MIN && ARABIC.test(t) ? stem(t.slice(AR_AND.length)) : '';
    const terms = [...new Set([t, s, rest].filter(Boolean))];
    return terms.length > 1 ? terms : t;
}

/** Query side: the stem (matched as a prefix); single letters are ignored, digits are kept. */
export function queryTerm(term) {
    const t = normalise(term);
    if (!t || (t.length < 2 && !/\d/.test(t))) return null;
    return stem(t) || t;
}

/** The processed terms of a query, as the index sees them (for highlighting and the OR fallback). */
export function queryTerms(query) {
    return String(query ?? '')
        .split(TOKEN_SPLIT)
        .map(queryTerm)
        .filter(Boolean);
}

/** Does a word of the original text match one of the processed query terms? */
export function wordMatches(word, terms) {
    if (!terms.length) return false;
    const t = normalise(word);
    if (!t) return false;
    const s = stem(t);
    return terms.some((term) => t.startsWith(term) || s.startsWith(term) || (term.length >= 3 && t.includes(term)));
}
