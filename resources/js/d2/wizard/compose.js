/**
 * Consultation wizard: the matter summary that is actually sent. The enquiry backend has no fields for
 * the audience, the Navigator situation, the requested consultant, the calculator that started the
 * request or the ticked documents (docs/enquiries.md), so they travel as plain, visible lines inside the
 * summary. The review step shows the composed text in an editable field: nothing is ever hidden.
 *
 *   own summary
 *   (blank line)
 *   Contacting us as: Individual
 *   Closest situation: …
 *   Consultation requested with: …
 *   Started from: …
 *   (blank line)
 *   Documents I have:
 *   - …
 *
 * Patterns are proposed copy from the server (wizard.context.*) with a :value placeholder.
 * Pure functions: unit-tested in compose.test.js.
 */

/** Replace :value without treating "$1" or "$&" in the visitor's text as replacement patterns. */
export const fill = (pattern, value) => String(pattern ?? '').replace(':value', () => String(value ?? ''));

/**
 * Context lines in a fixed order. `context` holds display values (already translated):
 * { audience, situation, consultant, tool }; `patterns` the wizard.context.* strings.
 */
export function contextLines(context = {}, patterns = {}) {
    const lines = [];
    for (const key of ['audience', 'situation', 'consultant', 'tool']) {
        const value = typeof context[key] === 'string' ? context[key].trim() : '';
        if (value && patterns[key]) lines.push(fill(patterns[key], value));
    }
    return lines;
}

/** "Documents I have:" followed by one "- document" line each; '' without documents. */
export function documentsBlock(documents = [], heading = '') {
    const items = documents.map((doc) => String(doc ?? '').trim()).filter(Boolean);
    if (!items.length) return '';
    return [String(heading ?? '').trim(), ...items.map((doc) => `- ${doc}`)].filter(Boolean).join('\n');
}

/** The full message: the visitor's summary, the context lines and the documents, blank-line separated. */
export function composeMessage({ summary = '', context = [], documents = '' } = {}) {
    const parts = [
        String(summary ?? '').replace(/\r\n?/g, '\n').trim(),
        context.filter(Boolean).join('\n'),
        String(documents ?? '').trim(),
    ];
    return parts.filter(Boolean).join('\n\n');
}

/**
 * Add text handed over by another section (a calculator summary) to what the visitor already wrote,
 * never twice and never replacing it.
 */
export function appendText(current, addition) {
    const base = String(current ?? '').replace(/\r\n?/g, '\n').trimEnd();
    const extra = String(addition ?? '').replace(/\r\n?/g, '\n').trim();
    if (!extra) return base;
    if (base.includes(extra)) return base;
    return base ? `${base}\n\n${extra}` : extra;
}
