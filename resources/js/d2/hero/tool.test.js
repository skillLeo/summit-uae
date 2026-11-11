import { describe, expect, it } from 'vitest';
import { detailFor, isAllowed, resultLabel, servicesFor } from './tool.js';

describe('hero tool card', () => {
    const map = { individual: ['S01', 'S06'], business: [], investor: ['S03'] };

    it('filters by the audience mapping', () => {
        expect([...servicesFor(map, 'individual')]).toEqual(['S01', 'S06']);
        expect([...servicesFor(map, 'investor')]).toEqual(['S03']);
    });

    it('shows every service when the audience has no mapping (missing or empty)', () => {
        expect(servicesFor(map, 'business')).toBeNull();
        expect(servicesFor(map, 'resident')).toBeNull();
        expect(servicesFor({}, 'individual')).toBeNull();
        expect(servicesFor(null, 'individual')).toBeNull();
    });

    it('builds the prefill detail from a situation code (service derived) and the audience', () => {
        expect(detailFor('S06-2', 'individual')).toEqual({ service: 'S06', situation: 'S06-2', audience: 'individual' });
        expect(detailFor('S11-4', 'business')).toEqual({ service: 'S11', situation: 'S11-4', audience: 'business' });
    });

    it('drops unknown or malformed values', () => {
        expect(detailFor('', 'investor')).toEqual({ audience: 'investor' });
        expect(detailFor('S12-1', 'pirate')).toEqual({});
        expect(detailFor('S06-2; drop', 'individual')).toEqual({ audience: 'individual' });
        expect(detailFor(['S06-2'], 'individual')).toEqual({ audience: 'individual' });
    });

    it('shows a service (a select group or a quick pick) only when the audience lists it', () => {
        expect(isAllowed(servicesFor(map, 'individual'), 'S06')).toBe(true);
        expect(isAllowed(servicesFor(map, 'investor'), 'S06')).toBe(false);
        expect(isAllowed(servicesFor(map, 'business'), 'S06')).toBe(true);
        expect(isAllowed(null, 'S11')).toBe(true);
    });

    it('labels the result half: the count, else the quick picks, else the plain documents label (D19)', () => {
        const labels = { picksLabel: 'Common situations', emptyLabel: 'Documents usually needed' };
        expect(resultLabel({ ...labels, countLabel: 'Documents usually needed: 4', picksShown: 3 })).toBe('Documents usually needed: 4');
        expect(resultLabel({ ...labels, picksShown: 1 })).toBe('Common situations');
        expect(resultLabel({ ...labels, picksShown: 0 })).toBe('Documents usually needed');
        expect(resultLabel({ emptyLabel: 'Documents usually needed', picksShown: 2 })).toBe('Documents usually needed');
    });
});
