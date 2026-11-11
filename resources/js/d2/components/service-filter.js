/**
 * Pure helpers of the P02 audience filter (d2ServiceHub, service-hub.js) and the service pages
 * (d2ServicePage, service-page.js). No DOM, no default export: app.js registers only modules that export a
 * default function, so this file is a plain library (unit tests: __tests__/service-filter.test.js).
 * Untrusted values (the query string, data attributes) are validated as plain strings against known codes.
 */

/** The audience keys a filter may use (config/audience_map.php → hero). */
export const AUDIENCES = ['individual', 'business', 'investor'];

const SERVICE = /^S(?:0[1-9]|1[01])$/;

/** A valid service code (S01 … S11) or null. */
export function serviceCode(value) {
    return typeof value === 'string' && SERVICE.test(value) ? value : null;
}

/**
 * Does a card belong to the chosen audience? `audiences` is the card's space-separated data-audiences;
 * 'all' (or anything unknown) shows every card.
 */
export function matchesAudience(audiences, audience) {
    if (audience === 'all' || !AUDIENCES.includes(audience)) return true;
    if (typeof audiences !== 'string') return false;
    return audiences.split(/\s+/).includes(audience);
}

/**
 * The validated ?audience= value of a query string (the server's rule: one plain value naming a known
 * audience, else 'all'). Repeated parameters, array syntax (?audience[]=x) and unknown values give 'all'.
 */
export function audienceFromSearch(search) {
    if (typeof search !== 'string') return 'all';
    let params;
    try {
        params = new URLSearchParams(search);
    } catch {
        return 'all';
    }
    const values = params.getAll('audience');
    if (values.length !== 1) return 'all';
    return AUDIENCES.includes(values[0]) ? values[0] : 'all';
}

/** The query string for an audience, keeping every other parameter ('' when nothing is left). */
export function searchForAudience(search, audience) {
    const params = new URLSearchParams(typeof search === 'string' ? search : '');
    params.delete('audience');
    if (AUDIENCES.includes(audience)) params.set('audience', audience);
    const out = params.toString();
    return out ? `?${out}` : '';
}
