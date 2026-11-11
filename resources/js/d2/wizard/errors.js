/**
 * Consultation wizard: field errors (from the browser checks or a 422 "invalid" response) mapped to
 * the wizard's steps and fields (docs/enquiries.md §2). The error summary lists them in the wizard's
 * order (by step, then the endpoint's field order) and links each one to a real control:
 *
 *   contact          the phone / e-mail group on step 1: shown on the group, the summary links the phone input
 *   summary          in the wizard the summary that was sent is the review message (step 5)
 *   enquiry_consent  the consent checkbox (Design 1 review C6 / A11Y-13: link to the real id)
 *   attachment       the document input on the Documents step 4 (<x-d2.upload.dropzone>, {prefix}-attachment);
 *                    it only exists while attachments are enabled, so the summary lists the message
 *                    without a link when the page has no such input (the controller checks the id)
 *
 * Pure functions: unit-tested in errors.test.js.
 */
import { FIELD_STEP } from './validate.js';

/** Every key a 422 can carry, in form order (StoreEnquiryRequest::ERROR_KEYS), plus the review message. */
export const ERROR_ORDER = ['name', 'phone', 'email', 'contact', 'summary', 'message', 'deadline_date', 'deadline_details', 'attachment', 'enquiry_consent'];

/** Server key → wizard field (only where they differ). */
const SERVER_FIELD = { summary: 'message' };

/** Wizard field → the control the summary link and the focus move go to. */
const TARGET = { contact: 'phone' };

const firstMessage = (value) => {
    const message = Array.isArray(value) ? value[0] : value;
    return typeof message === 'string' ? message : '';
};

/**
 * Normalise errors into an ordered list: [{ field, target, step, message, key }].
 * `errors`  { key: message | [message] } (server) or { field: messageKey } (browser checks)
 * `server`  true when the keys are the endpoint's keys (then "summary" means the review message)
 * `resolve` (field, key) => message: the approved text for browser checks (read from the page)
 */
export function mapErrors(errors = {}, { server = false, resolve = null } = {}) {
    if (!errors || typeof errors !== 'object') return [];
    const list = [];
    for (const [key, value] of Object.entries(errors)) {
        const field = server ? SERVER_FIELD[key] ?? key : key;
        if (!ERROR_ORDER.includes(field) && !ERROR_ORDER.includes(key)) continue;
        const message = server ? firstMessage(value) : resolve ? resolve(field, value) : firstMessage(value);
        list.push({
            key,
            field,
            target: TARGET[field] ?? field,
            step: FIELD_STEP[field] ?? 5,
            message,
        });
    }
    return list.sort((a, b) => a.step - b.step || ERROR_ORDER.indexOf(a.field) - ERROR_ORDER.indexOf(b.field));
}

/** The earliest step that has an error (where the wizard goes after a 422). */
export const firstErrorStep = (list) => (list.length ? Math.min(...list.map((item) => item.step)) : null);
