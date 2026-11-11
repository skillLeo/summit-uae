/**
 * Consultation wizard: the context another section hands over (docs/d2/engineering.md §8):
 *   prefill({ service, situation, audience, consultant, tool, summary })
 * through the d2:prefill event, the ?service=…&situation=… query (App\Design\D2\Prefill::fromRequest())
 * or sessionStorage (takeSummary()). Untrusted input: every code is a plain string checked against the
 * codes the page knows (Design 1 review SEC-02); arrays, objects and unknown values are dropped.
 *
 * Pure functions: unit-tested in context.test.js.
 */
export const PREFILL_KEYS = ['service', 'situation', 'audience', 'consultant', 'tool'];
const SUMMARY_MAX = 5000;

const str = (value) => (typeof value === 'string' ? value.trim() : '');

/**
 * `known` = { services: { S06: { situations: [...] } }, audiences: {code: label}, consultants: {…}, tools: {…} }
 * Returns only the valid parts: { service, situation, audience, consultant, tool, summary }.
 */
export function sanitisePrefill(detail, known = {}) {
    const out = {};
    if (!detail || typeof detail !== 'object' || Array.isArray(detail)) return out;
    const services = known.services || {};

    const service = str(detail.service);
    if (service && Object.hasOwn(services, service)) out.service = service;

    const situation = str(detail.situation);
    const match = /^(S(?:0[1-9]|1[01]))-([1-9])$/.exec(situation);
    if (match && Object.hasOwn(services, match[1])) {
        const list = services[match[1]].situations || [];
        if (Number(match[2]) <= list.length && (!out.service || out.service === match[1])) {
            out.situation = situation;
            out.service ??= match[1];
        }
    }

    for (const [key, table] of [['audience', known.audiences], ['consultant', known.consultants], ['tool', known.tools]]) {
        const value = str(detail[key]);
        if (value && table && Object.hasOwn(table, value)) out[key] = value;
    }

    const summary = typeof detail.summary === 'string' ? detail.summary.replace(/\r\n?/g, '\n').trim() : '';
    if (summary) out.summary = Array.from(summary).slice(0, SUMMARY_MAX).join('');
    return out;
}

/** The situation's approved text for "S06-2", or ''. */
export function situationText(ref, services = {}) {
    const match = /^(S(?:0[1-9]|1[01]))-([1-9])$/.exec(str(ref));
    if (!match || !Object.hasOwn(services, match[1])) return '';
    return (services[match[1]].situations || [])[Number(match[2]) - 1] || '';
}

/** The URL without the prefill parameters (kept: other parameters and the hash). */
export function withoutPrefillParams(href) {
    const url = new URL(href);
    let changed = false;
    for (const key of PREFILL_KEYS) {
        if (url.searchParams.has(key)) {
            url.searchParams.delete(key);
            changed = true;
        }
    }
    return changed ? url.toString() : null;
}
