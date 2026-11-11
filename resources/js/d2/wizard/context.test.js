import { describe, expect, it } from 'vitest';
import { sanitisePrefill, situationText, withoutPrefillParams } from './context.js';

const known = {
    services: {
        S05: { situations: ['Off-plan delay.', 'Deposit.', 'Rent increase.', 'Eviction.'] },
        S06: { situations: ['Salary overdue.', 'Final entitlements.', 'Release signed.', 'Employer.'] },
    },
    audiences: { individual: 'Individual', business: 'Business', investor: 'Investor' },
    consultants: { 'team-ahmed': 'Ahmed Mohammadi' },
    tools: { gratuity: 'Gratuity estimator', rent: 'Rent increase check' },
};

describe('sanitisePrefill (untrusted input: Design 1 review SEC-02)', () => {
    it('keeps valid codes', () => {
        expect(
            sanitisePrefill(
                { service: 'S06', situation: 'S06-2', audience: 'business', consultant: 'team-ahmed', tool: 'gratuity', summary: ' Estimate \r\n' },
                known,
            ),
        ).toEqual({ service: 'S06', situation: 'S06-2', audience: 'business', consultant: 'team-ahmed', tool: 'gratuity', summary: 'Estimate' });
    });

    it('takes the service from the situation', () => {
        expect(sanitisePrefill({ situation: 'S05-3' }, known)).toEqual({ service: 'S05', situation: 'S05-3' });
    });

    it('drops unknown, malformed, array and object values', () => {
        expect(
            sanitisePrefill(
                { service: ['S06'], situation: 'S06-9', audience: 'alien', consultant: 'team-nobody', tool: { a: 1 }, summary: 42 },
                known,
            ),
        ).toEqual({});
        expect(sanitisePrefill({ service: 'S99', situation: 'S07-1' }, known)).toEqual({});
        expect(sanitisePrefill({ service: 'constructor' }, known)).toEqual({});
        expect(sanitisePrefill(null, known)).toEqual({});
        expect(sanitisePrefill('S06', known)).toEqual({});
    });

    it('a situation of another service than the one given is ignored', () => {
        expect(sanitisePrefill({ service: 'S06', situation: 'S05-1' }, known)).toEqual({ service: 'S06' });
    });

    it('caps a handed-over summary at 5,000 characters', () => {
        expect(sanitisePrefill({ summary: 'x'.repeat(6000) }, known).summary).toHaveLength(5000);
    });
});

describe('situationText', () => {
    it('reads the approved situation text', () => {
        expect(situationText('S06-2', known.services)).toBe('Final entitlements.');
        expect(situationText('S06-5', known.services)).toBe('');
        expect(situationText('nope', known.services)).toBe('');
    });
});

describe('withoutPrefillParams', () => {
    it('removes only the prefill parameters and keeps the hash', () => {
        expect(withoutPrefillParams('https://x.test/en/?service=S06&situation=S06-2&utm=a#consultation-form')).toBe(
            'https://x.test/en/?utm=a#consultation-form',
        );
        expect(withoutPrefillParams('https://x.test/en/?tool=rent#consultation-form')).toBe('https://x.test/en/#consultation-form');
        expect(withoutPrefillParams('https://x.test/en/#consultation-form')).toBeNull();
    });
});
