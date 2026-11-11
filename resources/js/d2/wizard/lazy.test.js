import { describe, expect, it } from 'vitest';
import { prefixOf, targetsWizard } from './lazy.js';

describe('lazy wizard start-up', () => {
    it('loads at once when the address targets the form section or one of the wizard’s own ids', () => {
        expect(targetsWizard('#consultation-form', 'wz')).toBe(true);
        expect(targetsWizard('consultation-form', 'wz')).toBe(true);
        expect(targetsWizard('#wz-name', 'wz')).toBe(true);
        expect(targetsWizard('#wz-attachment', 'wz')).toBe(true);
        expect(targetsWizard('#wz%2Dphone', 'wz')).toBe(true);
    });

    it('ignores every other fragment (and never throws on a malformed one)', () => {
        for (const hash of ['', '#', '#main-content', '#navigator=S05-2', '#tool-rent', '#faq-07', '#wzname', '#consultation-form-x', '#%E0%A4%A']) {
            expect(targetsWizard(hash, 'wz')).toBe(false);
        }
        expect(targetsWizard('#sg-wz-s1-name', 'wz')).toBe(false);
        expect(targetsWizard('#wz-name', '')).toBe(false);
        expect(targetsWizard(undefined, 'wz')).toBe(false);
    });

    it('reads the id prefix from the root id', () => {
        expect(prefixOf('wz-wizard')).toBe('wz');
        expect(prefixOf('sg-wz-s1-wizard')).toBe('sg-wz-s1');
        expect(prefixOf('')).toBe('');
        expect(prefixOf(undefined)).toBe('');
    });
});
