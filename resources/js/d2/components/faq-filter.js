/**
 * Pure helpers of the FAQ browser's local search filter (components/faq.js; spec §12 C11). Every query
 * word must appear in the question or its answer, compared through the shared normalise() and the
 * palette's light stemmer (palette/terms.js), so "Cheques", "الشيكات" and "зарплата" match their other
 * forms. No MiniSearch here: 74 questions are filtered with plain substring tests.
 *
 * Named exports only: app.js imports every components/*.js file, and this one registers nothing.
 */
import { normalise } from '../lib/normalise.js';
import { queryTerms, wordMatches } from '../palette/terms.js';

/** The searchable text of a question: normalised question + answer, one string. */
export const haystack = (question, answer = '') => normalise(`${question ?? ''} ${answer ?? ''}`);

/** The processed query words (empty: no filtering). */
export const filterTerms = (query) => queryTerms(query);

/** True when every query word occurs in the text. */
export function matches(text, terms) {
    if (!terms.length) return true;
    return terms.every((term) => text.includes(term));
}

/** Ids of the matching items, in their order: items = [{ id, text }]. */
export function filterItems(items, query) {
    const terms = filterTerms(query);
    return items.filter((item) => matches(item.text, terms)).map((item) => item.id);
}

/**
 * Split a text into runs for highlighting: [{ text, mark }]; whitespace stays in its own runs, a word is
 * marked when it matches one of the terms (same rule as the palette).
 */
export function markRuns(text, terms) {
    if (!terms.length) return [{ text, mark: false }];
    const runs = [];
    for (const piece of String(text).split(/(\s+)/)) {
        if (!piece) continue;
        const mark = !/^\s+$/.test(piece) && wordMatches(piece, terms);
        const last = runs[runs.length - 1];
        if (last && last.mark === mark && !mark) last.text += piece;
        else runs.push({ text: piece, mark });
    }
    return runs;
}
