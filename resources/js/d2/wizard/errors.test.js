import { describe, expect, it } from 'vitest';
import { ERROR_ORDER, firstErrorStep, mapErrors } from './errors.js';

describe('mapErrors', () => {
    it('maps a 422 back to its step and field, in the wizard order (step, then field order)', () => {
        const list = mapErrors(
            {
                enquiry_consent: ['Consent'],
                summary: ['Summary'],
                contact: ['Contact'],
                name: ['Name'],
                deadline_date: ['Date'],
            },
            { server: true },
        );
        // The homepage DOCX order of the states: missing name, invalid contact, missing summary, missing consent.
        expect(list.map((e) => [e.field, e.target, e.step, e.message])).toEqual([
            ['name', 'name', 1, 'Name'],
            ['contact', 'phone', 1, 'Contact'],
            ['deadline_date', 'deadline_date', 3, 'Date'],
            ['message', 'message', 5, 'Summary'],
            ['enquiry_consent', 'enquiry_consent', 5, 'Consent'],
        ]);
        expect(firstErrorStep(list)).toBe(1);
    });

    it('after a no-JS round trip "summary" is the summary field of step 2', () => {
        const list = mapErrors({ summary: ['Summary'], email: ['Email'] });
        expect(list.map((e) => [e.field, e.step])).toEqual([
            ['email', 1],
            ['summary', 2],
        ]);
        expect(firstErrorStep(list)).toBe(1);
    });

    it('resolves browser-check keys to the page text', () => {
        const list = mapErrors({ message: 'message_long', phone: 'phone' }, { resolve: (field, key) => `${field}:${key}` });
        expect(list.map((e) => e.message)).toEqual(['phone:phone', 'message:message_long']);
        expect(list.map((e) => e.step)).toEqual([1, 5]);
    });

    it('sends an attachment error to the document input on the Documents step and ignores unknown keys', () => {
        const list = mapErrors({ attachment: ['Type'], hacker: ['x'], name: 'plain string' }, { server: true });
        expect(list.map((e) => [e.field, e.target, e.step, e.message])).toEqual([
            ['name', 'name', 1, 'plain string'],
            ['attachment', 'attachment', 4, 'Type'],
        ]);
        expect(mapErrors(null)).toEqual([]);
        expect(firstErrorStep([])).toBeNull();
    });

    it('a 422 with only an attachment error opens the Documents step; with consent too, still step 4 first', () => {
        const only = mapErrors({ attachment: ['Size'] }, { server: true });
        expect(firstErrorStep(only)).toBe(4);
        const both = mapErrors({ enquiry_consent: ['Consent'], attachment: ['Size'] }, { server: true });
        expect(both.map((e) => e.field)).toEqual(['attachment', 'enquiry_consent']);
        expect(firstErrorStep(both)).toBe(4);
    });

    it('knows every key the endpoint can send', () => {
        for (const key of ['name', 'phone', 'email', 'contact', 'summary', 'deadline_date', 'deadline_details', 'attachment', 'enquiry_consent']) {
            expect(ERROR_ORDER).toContain(key);
        }
    });
});
