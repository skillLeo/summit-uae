import { describe, expect, it } from 'vitest';
import {
    FIELD_STEP,
    canonicalPhone,
    charLength,
    checkStep,
    cleanText,
    firstInvalidStep,
    isPrefixOnly,
    normaliseDigits,
    phoneValue,
    validDate,
    validEmail,
} from './validate.js';

describe('phone (mirrors StoreEnquiryRequest::canonicalPhone)', () => {
    it.each([
        ['+971 52 466 6191', '+971524666191'],
        ['00971 (52) 466-6191', '+971524666191'],
        ['+7 912 345.67.89', '+79123456789'],
        ['+٩٧١ ٥٢ ٤٦٦ ٦١٩١', '+971524666191'],
        ['‎+971 52 466 6191', '+971524666191'],
    ])('accepts %s', (input, canonical) => {
        expect(canonicalPhone(input)).toBe(canonical);
    });

    it.each(['052 466 6191', '+971 5', '+1234567890123456', '+971 52 466 6191 ext 2', 'phone'])('rejects %s', (input) => {
        expect(canonicalPhone(input)).toBeNull();
    });

    it('treats a country-code prefix as no phone (the form pre-fills "+971 ")', () => {
        for (const prefix of ['+971', '+971 ', '00', '+', '00971']) expect(isPrefixOnly(prefix)).toBe(true);
        expect(isPrefixOnly('+971 5')).toBe(false);
        expect(phoneValue('+971 ')).toBe('');
        expect(phoneValue('  ')).toBe('');
        expect(phoneValue(' +971 52 466 6191 ')).toBe('+971 52 466 6191');
    });

    it('normalises Arabic-Indic digits and drops direction marks', () => {
        expect(normaliseDigits('٠١٢٣٤٥٦٧٨٩ ۰۱۲۳۴۵۶۷۸۹')).toBe('0123456789 0123456789');
        expect(normaliseDigits('‏12‎')).toBe('12');
    });
});

describe('e-mail', () => {
    it.each(['info@summitlegaluae.com', 'a.example@example.co.uk', 'имя@пример.рф', ' a@b.ae '])('accepts %s', (value) => {
        expect(validEmail(value)).toBe(true);
    });

    it.each(['', 'wrong@', '@example.com', 'a@b', 'a b@example.com', 'a@@example.com', 'a@example..com', `${'a'.repeat(250)}@example.com`])(
        'rejects %s',
        (value) => {
            expect(validEmail(value)).toBe(false);
        },
    );
});

describe('deadline date (ISO, real date, 1900–2100, past allowed)', () => {
    it('accepts empty, past and future dates', () => {
        expect(validDate('')).toBe(true);
        expect(validDate('1999-12-31')).toBe(true);
        expect(validDate('2026-02-28')).toBe(true);
        expect(validDate('2028-02-29')).toBe(true);
        expect(validDate('1900-01-01')).toBe(true);
        expect(validDate('2100-12-31')).toBe(true);
    });

    it('rejects impossible, out-of-range and malformed dates and bad input', () => {
        expect(validDate('2026-02-29')).toBe(false);
        expect(validDate('2026-13-01')).toBe(false);
        expect(validDate('1899-12-31')).toBe(false);
        expect(validDate('2101-01-01')).toBe(false);
        expect(validDate('01/12/2026')).toBe(false);
        expect(validDate('', { badInput: true })).toBe(false);
    });
});

describe('text', () => {
    it('counts line breaks once and code points, like mb_strlen after the server normalises', () => {
        expect(cleanText('  a\r\nb\rc  ')).toBe('a\nb\nc');
        expect(cleanText('a\u0007b')).toBe('ab');
        expect(charLength('😀😀')).toBe(2);
    });
});

describe('checkStep (the Contact DOCX order: D38)', () => {
    it('step 1 needs a name and one valid contact method; both are checked when both are given', () => {
        const contact = { name: 'A. Example', phone: '+971 50 000 0000', email: '' };
        expect(checkStep(1, { ...contact, name: '   ' })).toEqual({ name: 'name' });
        expect(checkStep(1, contact)).toEqual({});
        expect(checkStep(1, { name: 'A', phone: '+971 ', email: '' })).toEqual({ contact: 'contact' });
        expect(checkStep(1, { name: '', phone: '', email: '' })).toEqual({ name: 'name', contact: 'contact' });
        expect(checkStep(1, { name: 'A', phone: '+971 ', email: 'a@example.com' })).toEqual({});
        expect(checkStep(1, { name: 'A', phone: '+971 5', email: 'wrong@' })).toEqual({ phone: 'phone', email: 'email' });
        expect(checkStep(1, { name: 'A', phone: '+971 50 000 0000', email: 'wrong@' })).toEqual({ email: 'email' });
    });

    it('step 2 needs a summary', () => {
        expect(checkStep(2, { summary: '' })).toEqual({ summary: 'summary' });
        expect(checkStep(2, { summary: 'x' })).toEqual({});
    });

    it('step 3 (deadline) accepts an empty, past or future date but not an impossible one', () => {
        expect(checkStep(3, {})).toEqual({});
        expect(checkStep(3, { deadline_date: '2026-02-30' })).toEqual({ deadline_date: 'deadline_date' });
        expect(checkStep(3, { deadline_date: '', deadline_date_bad: true })).toEqual({ deadline_date: 'deadline_date' });
        expect(checkStep(3, { deadline_date: '2020-01-01' })).toEqual({});
    });

    it('step 4 (documents) never blocks', () => {
        expect(checkStep(4, {})).toEqual({});
    });

    it('step 5 needs the message (at most 5,000 characters) and consent', () => {
        expect(checkStep(5, { message: '', enquiry_consent: false })).toEqual({ message: 'summary', enquiry_consent: 'enquiry_consent' });
        expect(checkStep(5, { message: 'x'.repeat(5001), enquiry_consent: true })).toEqual({ message: 'message_long' });
        expect(checkStep(5, { message: `${'x'.repeat(4999)}\r\n`, enquiry_consent: true })).toEqual({});
    });

    it('firstInvalidStep finds the earliest step with errors', () => {
        const values = { name: 'A', summary: '', phone: '', email: '', message: 'm', enquiry_consent: true };
        expect(firstInvalidStep(values)).toEqual({ step: 1, errors: { contact: 'contact' } });
        expect(firstInvalidStep({ ...values, phone: '+971 50 000 0000' })).toEqual({ step: 2, errors: { summary: 'summary' } });
        expect(firstInvalidStep({ ...values, summary: 's', phone: '+971 50 000 0000' })).toBeNull();
    });

    it('fields follow the DOCX order: name and contact details, matter summary, deadline, document, consent', () => {
        expect([FIELD_STEP.name, FIELD_STEP.phone, FIELD_STEP.email, FIELD_STEP.contact]).toEqual([1, 1, 1, 1]);
        expect(FIELD_STEP.summary).toBe(2);
        expect([FIELD_STEP.deadline_date, FIELD_STEP.deadline_details]).toEqual([3, 3]);
        expect(FIELD_STEP.attachment).toBe(4);
        expect([FIELD_STEP.message, FIELD_STEP.enquiry_consent]).toEqual([5, 5]);
    });

    it('every field has a step', () => {
        for (const field of ['name', 'summary', 'deadline_date', 'deadline_details', 'phone', 'email', 'contact', 'message', 'enquiry_consent']) {
            expect(FIELD_STEP[field]).toBeGreaterThan(0);
        }
    });
});
