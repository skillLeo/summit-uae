import { describe, expect, it } from 'vitest';
import { AUDIENCES, audienceFromSearch, matchesAudience, searchForAudience, serviceCode } from '../service-filter.js';

describe('service-filter: P02 audience filter and service codes', () => {
    it('knows the three hero audiences of config/audience_map.php', () => {
        expect(AUDIENCES).toEqual(['individual', 'business', 'investor']);
    });

    it('matches a card by its space-separated audiences', () => {
        expect(matchesAudience('individual business', 'business')).toBe(true);
        expect(matchesAudience('individual business', 'investor')).toBe(false);
        expect(matchesAudience('investor', 'investor')).toBe(true);
        // a service no audience maps to only shows under "All"
        expect(matchesAudience('', 'business')).toBe(false);
        expect(matchesAudience(undefined, 'business')).toBe(false);
    });

    it('shows every card for "all" and for anything unknown', () => {
        expect(matchesAudience('', 'all')).toBe(true);
        expect(matchesAudience('individual', 'nobody')).toBe(true);
        expect(matchesAudience('individual', '')).toBe(true);
    });

    it('never matches a partial word', () => {
        expect(matchesAudience('individuals', 'individual')).toBe(false);
        expect(matchesAudience('businessman', 'business')).toBe(false);
    });

    it('reads only one plain, known ?audience= value (same rule as the server)', () => {
        expect(audienceFromSearch('?audience=business')).toBe('business');
        expect(audienceFromSearch('?x=1&audience=investor')).toBe('investor');
        expect(audienceFromSearch('')).toBe('all');
        expect(audienceFromSearch('?audience=')).toBe('all');
        expect(audienceFromSearch('?audience=nobody')).toBe('all');
        expect(audienceFromSearch('?audience[]=business')).toBe('all');
        expect(audienceFromSearch('?audience=business&audience=investor')).toBe('all');
        expect(audienceFromSearch('?audience=Business')).toBe('all');
        expect(audienceFromSearch(null)).toBe('all');
    });

    it('writes the query for an audience and keeps other parameters', () => {
        expect(searchForAudience('', 'business')).toBe('?audience=business');
        expect(searchForAudience('?audience=business', 'all')).toBe('');
        expect(searchForAudience('?x=1&audience=individual', 'investor')).toBe('?x=1&audience=investor');
        expect(searchForAudience('?x=1&audience=individual', 'all')).toBe('?x=1');
        expect(searchForAudience('?x=1', 'nobody')).toBe('?x=1');
    });

    it('accepts only S01 … S11 as a service code', () => {
        expect(serviceCode('S01')).toBe('S01');
        expect(serviceCode('S11')).toBe('S11');
        for (const bad of ['S00', 'S12', 's01', 'S1', 'S011', '', null, undefined, ['S01'], 'S01 ']) {
            expect(serviceCode(bad)).toBe(null);
        }
    });
});
