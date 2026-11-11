/**
 * Consultation wizard: browser-side checks that mirror App\Http\Requests\StoreEnquiryRequest
 * (docs/enquiries.md §1). The server stays the authority; these checks only stop a round trip that is
 * certain to fail, and they never reject a value the server accepts, except an e-mail address without
 * a dot in its domain (a typo far more often than a real address).
 *
 * Pure functions (no DOM): unit-tested in validate.test.js.
 */

/**
 * The step each field lives on, in the Contact DOCX form order (Design 2.1, D38): 1 You (name, phone, e-mail,
 * language), 2 Your matter (type, summary), 3 Deadline, 4 Documents (the checklist; the one optional document
 * only while attachments are enabled), 5 Review (the message that is actually sent, notices, consent).
 */
export const FIELD_STEP = {
    name: 1,
    phone: 1,
    email: 1,
    contact: 1,
    summary: 2,
    deadline_date: 3,
    deadline_details: 3,
    attachment: 4,
    message: 5,
    enquiry_consent: 5,
};

export const LIMITS = { name: 120, summary: 5000, deadline_details: 500, email: 254 };

const ARABIC_DIGITS = /[٠-٩۰-۹]/g;
const FORMAT_CHARS = /\p{Cf}/gu;
const CONTROL_CHARS = /[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F]/g;
const PHONE_SEPARATORS = /[().\s\p{Pd}]/gu;

/** Arabic-Indic digits to ASCII and invisible direction marks removed (as the server normalises). */
export function normaliseDigits(value) {
    return String(value ?? '')
        .replace(FORMAT_CHARS, '')
        .replace(ARABIC_DIGITS, (digit) => {
            const code = digit.charCodeAt(0);
            return String(code >= 0x06f0 ? code - 0x06f0 : code - 0x0660);
        });
}

/** Free text as the server stores it: one \n per line break, control characters dropped, trimmed. */
export function cleanText(value) {
    return String(value ?? '')
        .replace(/\r\n?/g, '\n')
        .replace(CONTROL_CHARS, '')
        .trim();
}

/** Length in characters (code points), the way PHP's mb_strlen counts for the max: rules. */
export const charLength = (value) => Array.from(String(value ?? '')).length;

/** Only a country-code prefix such as "+971 " (the form pre-fills it): counts as no phone. */
export function isPrefixOnly(value) {
    const compact = normaliseDigits(value).trim().replace(PHONE_SEPARATORS, '');
    return /^(?:\+|00)[0-9]{0,3}$/.test(compact);
}

/** The phone as the server reads it: '' when empty or only a prefix, otherwise the trimmed text. */
export function phoneValue(value) {
    const v = normaliseDigits(value).trim();
    return v === '' || isPrefixOnly(v) ? '' : v;
}

/** "+" or "00", then 7–15 digits; spaces, hyphens, dots and parentheses allowed. "+digits" or null. */
export function canonicalPhone(value) {
    const v = normaliseDigits(value).trim();
    if (!/^[0-9+().\s\p{Pd}]+$/u.test(v)) return null;
    const match = /^(?:\+|00)([0-9]{7,15})$/.exec(v.replace(PHONE_SEPARATORS, ''));
    return match ? `+${match[1]}` : null;
}

export function validEmail(value) {
    const v = String(value ?? '').replace(FORMAT_CHARS, '').trim();
    if (v === '' || charLength(v) > LIMITS.email || v.includes('..')) return false;
    return /^[^\s@]+@[^\s@]+\.[^\s@.]{2,}$/u.test(v);
}

/** ISO YYYY-MM-DD, a real calendar date between 1900 and 2100 (past dates are allowed). Empty is fine. */
export function validDate(value, { badInput = false } = {}) {
    if (badInput) return false;
    const v = String(value ?? '').trim();
    if (v === '') return true;
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v);
    if (!match) return false;
    const [year, month, day] = [Number(match[1]), Number(match[2]), Number(match[3])];
    if (year < 1900 || year > 2100) return false;
    const date = new Date(Date.UTC(year, month - 1, day));
    return date.getUTCFullYear() === year && date.getUTCMonth() === month - 1 && date.getUTCDate() === day;
}

/**
 * Errors for one step: { field: messageKey }. messageKey names the approved message ('name', 'summary',
 * 'contact' …) or the proposed 'message_long'. `values` holds raw field values:
 * { name, summary, deadline_date, deadline_date_bad, phone, email, message, enquiry_consent }.
 */
export function checkStep(step, values = {}) {
    const errors = {};
    if (step === 1) {
        if (cleanText(values.name) === '') errors.name = 'name';
        const phone = phoneValue(values.phone);
        const email = String(values.email ?? '').trim();
        if (phone === '' && email === '') errors.contact = 'contact';
        if (phone !== '' && canonicalPhone(phone) === null) errors.phone = 'phone';
        if (email !== '' && !validEmail(email)) errors.email = 'email';
    }
    if (step === 2) {
        if (cleanText(values.summary) === '') errors.summary = 'summary';
    }
    if (step === 3) {
        if (!validDate(values.deadline_date, { badInput: !!values.deadline_date_bad })) errors.deadline_date = 'deadline_date';
    }
    if (step === 5) {
        const message = cleanText(values.message);
        if (message === '') errors.message = 'summary';
        else if (charLength(message) > LIMITS.summary) errors.message = 'message_long';
        if (!values.enquiry_consent) errors.enquiry_consent = 'enquiry_consent';
    }
    return errors;
}

/** Every step at once (before sending): { step, errors } of the first step with errors, or null. */
export function firstInvalidStep(values, steps = [1, 2, 3, 4, 5]) {
    for (const step of steps) {
        const errors = checkStep(step, values);
        if (Object.keys(errors).length) return { step, errors };
    }
    return null;
}
