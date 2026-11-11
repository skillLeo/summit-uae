/**
 * Command-palette search (upgrade spec §12 C12, §5.5): MiniSearch over public/search/{locale}.json with
 * the shared normalise() (contract F) as its term processor, plus a light stemmer so inflected words
 * meet: Arabic article and clitic prefixes (ال، وال، بال …) and a few suffixes, Russian case endings,
 * English plural / -ing / -ed. Every indexed word is stored as itself and as its stem; a query word is
 * reduced to its stem and matched as a prefix (Latin and Cyrillic words also with a typo from five
 * letters, see fuzzyFor), so "зарплата" finds "зарплаты", "الشيك" finds "شيك" and "cheques" finds "cheque".
 *
 * Pure (no DOM): Vitest in palette/search.test.js. Loaded only with the palette engine (lazy chunk).
 */
import MiniSearch from 'minisearch';
import { indexTerms, queryTerm, queryTerms } from './terms.js';

export { indexTerms, queryTerm, queryTerms, stem, wordMatches } from './terms.js';

export const TYPES = ['service', 'guide', 'faq', 'case'];
export const LIMITS = { service: 5, guide: 4, faq: 6, case: 3 };
export const MAX_RESULTS = 16;

const ARABIC = /[\u0600-\u06FF]/;

/**
 * Typo tolerance of a processed query word: one edit from five letters (two from eight) for Latin and
 * Cyrillic words; never for Arabic words, where a single dot turns a word into another one (فضائية
 * "space" / قضائية "judicial", تحصيل "collection" / تحويل "transfer", الشيكات / الشركات): two thirds of the
 * five-letter Arabic words of the index have such a neighbour. normalise() and the stemmer already absorb
 * the usual Arabic spelling variants (hamza forms, taa marbuta, alef maqsura, tashkeel, the article).
 */
export const fuzzyFor = (term) => (typeof term === 'string' && term.length >= 5 && !ARABIC.test(term) ? 0.2 : false);

export function createIndex(items) {
    const index = new MiniSearch({
        idField: 'id',
        fields: ['title', 'summary', 'text'],
        storeFields: ['type', 'title', 'summary', 'url'],
        processTerm: indexTerms,
        searchOptions: {
            processTerm: queryTerm,
            boost: { title: 4, summary: 2, text: 1 },
            prefix: true,
            fuzzy: fuzzyFor,
            combineWith: 'AND',
        },
    });
    index.addAll(Array.isArray(items) ? items.filter((item) => item && typeof item.id === 'string') : []);
    return index;
}

/** Results grouped by type: groups ordered by their best hit, items by score, capped per group and in total. */
export function groupHits(hits) {
    const byType = new Map();
    for (const hit of hits) {
        if (!TYPES.includes(hit.type)) continue;
        if (!byType.has(hit.type)) byType.set(hit.type, []);
        byType.get(hit.type).push(hit);
    }
    const groups = [...byType.entries()].map(([type, list]) => ({
        type,
        best: list[0].score,
        items: list.slice(0, LIMITS[type] ?? 4),
        total: list.length,
    }));
    groups.sort((a, b) => b.best - a.best || TYPES.indexOf(a.type) - TYPES.indexOf(b.type));
    let left = MAX_RESULTS;
    for (const group of groups) {
        group.items = group.items.slice(0, Math.max(0, left));
        left -= group.items.length;
    }
    return groups.filter((group) => group.items.length);
}

/**
 * Every query word must match (AND). When nothing matches a query of several words (a sentence such as
 * "my employer did not pay my salary"), the documents that match at least half of the words are shown;
 * in that fallback a word counts only when it matches exactly or as a word start, never by a near-miss
 * (so "زرافة فضائية", "space giraffe", finds nothing rather than a question about a judicial matter).
 * Returns { terms, groups, count, partial }.
 */
export function search(index, query) {
    const terms = queryTerms(query);
    if (!terms.length || !index) return { terms, groups: [], count: 0, partial: false };
    let hits = index.search(query, { combineWith: 'AND' });
    let partial = false;
    if (!hits.length && terms.length > 1) {
        const need = Math.ceil(new Set(terms).size / 2);
        hits = index.search(query, { combineWith: 'OR', fuzzy: false }).filter((hit) => new Set(hit.queryTerms).size >= need);
        partial = hits.length > 0;
    }
    const groups = groupHits(hits);
    return { terms, groups, count: groups.reduce((sum, group) => sum + group.items.length, 0), partial };
}
