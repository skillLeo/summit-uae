/**
 * Search text normalisation shared by the command palette, the FAQ filter and the Navigator
 * (docs/d2/engineering.md, shared contract F). Apply it to both sides of a comparison: the indexed
 * text and the visitor's query.
 *
 *   normalise('Ёлка Café') === 'елка cafe'
 *   normalise('أَحْكَامُ الإيجارِ') === 'احكام الايجار'
 *
 * - lower case (locale independent);
 * - Latin and Cyrillic diacritics are stripped (é → e, ü → u, stress marks, ё → е) while й stays a
 *   letter of its own, as on a Russian keyboard;
 * - Arabic: أ إ آ ٱ → ا, ة → ه, ى → ي (and the Persian forms ی → ي, ک → ك), tashkeel, Quranic marks
 *   and tatweel are removed;
 * - Arabic-Indic and Persian digits become 0–9, so "٢٠٢٦" finds "2026".
 *
 * The result can be shorter than the input (marks are removed): never use its indices on the
 * original string.
 */

// Harakat, tanween, shadda, sukun, the hamza and madda combining marks, superscript alef and the
// small Quranic annotation signs.
const ARABIC_MARKS = /[\u0610-\u061A\u064B-\u065F\u0670\u06D6-\u06DC\u06DF-\u06E8\u06EA-\u06ED]/g;
const TATWEEL = /\u0640/g;
const ALEF_FORMS = /[\u0622\u0623\u0625\u0671]/g;
const COMBINING = /[\u0300-\u036F]/g;
// Letters that are not decomposed by NFD.
// ß æ œ ø đ ł ı þ
const LATIN_EXTRA = { '\u00DF': 'ss', '\u00E6': 'ae', '\u0153': 'oe', '\u00F8': 'o', '\u0111': 'd', '\u0142': 'l', '\u0131': 'i', '\u00FE': 'th' };
const LATIN_EXTRA_RE = /[\u00DF\u00E6\u0153\u00F8\u0111\u0142\u0131\u00FE]/g;
const ARABIC_DIGITS = /[\u0660-\u0669\u06F0-\u06F9]/g;

export function normalise(text) {
    if (text === null || text === undefined) return '';
    let s = String(text).normalize('NFC').toLowerCase();

    s = s
        .replace(ALEF_FORMS, '\u0627')
        .replace(/\u0629/g, '\u0647')
        .replace(/[\u0649\u06CC]/g, '\u064A')
        .replace(/\u06A9/g, '\u0643')
        .replace(ARABIC_MARKS, '')
        .replace(TATWEEL, '')
        // U+0660–0669 and U+06F0–06F9: the low four bits are the digit.
        .replace(ARABIC_DIGITS, (d) => String(d.charCodeAt(0) & 0xf));

    // Decompose, keep й (и + breve) as one letter, drop the remaining combining marks, recompose.
    s = s
        .normalize('NFD')
        .replace(/\u0438\u0306/g, '\u0439')
        .replace(COMBINING, '')
        .normalize('NFC');

    return s.replace(LATIN_EXTRA_RE, (c) => LATIN_EXTRA[c]);
}

export default normalise;
