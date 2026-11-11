import { describe, expect, it } from 'vitest';
import { appendText, composeMessage, contextLines, documentsBlock, fill } from './compose.js';

const patterns = {
    audience: 'Contacting us as: :value',
    situation: 'Closest situation: :value',
    consultant: 'Consultation requested with: :value',
    tool: 'Started from: :value',
};

describe('compose', () => {
    it('fills :value literally (no $-patterns from the visitor or the copy)', () => {
        expect(fill('Started from: :value', 'Rent $1 $& check')).toBe('Started from: Rent $1 $& check');
    });

    it('builds the context lines in a fixed order and skips empty parts', () => {
        expect(contextLines({ tool: 'Gratuity estimator', audience: 'Individual', situation: '' }, patterns)).toEqual([
            'Contacting us as: Individual',
            'Started from: Gratuity estimator',
        ]);
        expect(contextLines({}, patterns)).toEqual([]);
        expect(contextLines({ audience: 'Individual' }, {})).toEqual([]);
    });

    it('lists the ticked documents under their heading', () => {
        expect(documentsBlock(['Contract.', ' ', 'Payslips.'], 'Documents I have:')).toBe('Documents I have:\n- Contract.\n- Payslips.');
        expect(documentsBlock([], 'Documents I have:')).toBe('');
    });

    it('joins summary, context and documents with blank lines', () => {
        const message = composeMessage({
            summary: '  My salary is late.\r\n',
            context: ['Contacting us as: Individual', 'Closest situation: Your salary is overdue.'],
            documents: 'Documents I have:\n- Payslips.',
        });
        expect(message).toBe(
            'My salary is late.\n\nContacting us as: Individual\nClosest situation: Your salary is overdue.\n\nDocuments I have:\n- Payslips.',
        );
        expect(composeMessage({ summary: 'Only this.' })).toBe('Only this.');
        expect(composeMessage({ context: ['Started from: Rent increase check'] })).toBe('Started from: Rent increase check');
    });

    it('adds handed-over text once and never replaces what the visitor wrote', () => {
        expect(appendText('', 'Estimate: AED 0')).toBe('Estimate: AED 0');
        expect(appendText('My words', 'Estimate: AED 0')).toBe('My words\n\nEstimate: AED 0');
        expect(appendText('My words\n\nEstimate: AED 0', 'Estimate: AED 0')).toBe('My words\n\nEstimate: AED 0');
        expect(appendText('My words', '   ')).toBe('My words');
    });
});
