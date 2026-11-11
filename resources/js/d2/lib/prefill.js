/**
 * Hand context to the consultation wizard (docs/d2/engineering.md §8).
 *
 *   prefill({ service: 'S06', situation: 'S06-2', audience: 'individual', consultant: 'team-ahmed',
 *             tool: 'gratuity', summary: 'text the visitor can still edit' })
 *
 * On a page with the wizard (#consultation-form) it dispatches `d2:prefill` on window (the wizard
 * listens) and scrolls to the form; elsewhere it navigates to the consultation URL from
 * <meta name="d2-consultation" content="…"> with the same values as query parameters (the summary
 * text travels through sessionStorage, never through the URL).
 */
import { scrollBehavior } from './prefs.js';

const KEYS = ['service', 'situation', 'audience', 'consultant', 'tool'];
export const SUMMARY_KEY = 'summit:prefill-summary';

export function prefill(detail = {}) {
    const form = document.getElementById('consultation-form');
    if (form) {
        window.dispatchEvent(new CustomEvent('d2:prefill', { detail }));
        form.scrollIntoView({ behavior: scrollBehavior(), block: 'start' });
        return;
    }
    const base = document.querySelector('meta[name="d2-consultation"]')?.content;
    if (!base) return;
    const url = new URL(base, window.location.href);
    for (const key of KEYS) {
        if (typeof detail[key] === 'string' && detail[key] !== '') url.searchParams.set(key, detail[key]);
    }
    try {
        if (detail.summary) window.sessionStorage.setItem(SUMMARY_KEY, detail.summary);
    } catch {
        /* storage blocked: the summary is optional */
    }
    url.hash = 'consultation-form';
    window.location.assign(url.toString());
}

/** Read and clear the summary handed over from another page. */
export function takeSummary() {
    try {
        const value = window.sessionStorage.getItem(SUMMARY_KEY);
        window.sessionStorage.removeItem(SUMMARY_KEY);
        return value;
    } catch {
        return null;
    }
}
