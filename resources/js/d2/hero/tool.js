/**
 * Tool card "Start your matter" (<x-d2.hero.tool>, upgrade spec §12 B1; contracts D, H and I in
 * docs/d2/engineering.md §8; Design 2.1 D19). Lazy chunk, mounted by Alpine d2HeroTool (components/hero.js).
 *
 * - Audience (segmented radios) → the situation select keeps only the services that
 *   config('audience_map.hero') maps to it (all services, still grouped, when it has no mapping). A chosen
 *   situation that the new audience does not list is cleared. The quick picks follow the same rule.
 * - Before a choice the result half shows the quick picks ("Common situations": chips holding approved situations,
 *   config('design.d2.home.quick_picks')); when the audience lists none of them, the plain "Documents usually
 *   needed" line and its hint. A pick selects its situation (the select keeps the value, so Continue and the
 *   no-JS submit agree) and moves focus to the select, since the pick itself is no longer shown.
 * - Situation → "Documents usually needed: N" (the polite live line; server-rendered per service with
 *   trans_choice) and that service's approved documents (server-rendered lists; the others are .is-off).
 * - Continue → prefill({service, situation, audience}) (lib/prefill.js): on the home page the wizard
 *   receives d2:prefill and the page scrolls to it; elsewhere the browser goes to the consultation form
 *   with the same values. Without JavaScript the form's GET submit does the same (a pick submits its own value).
 * Everything is computed in the browser; nothing is sent until the visitor submits the consultation form.
 */
import { prefill } from '../lib/prefill.js';

export const SERVICE_RE = /^(S(?:0[1-9]|1[01]))-([1-9])$/;

/** The service codes an audience shows, or null for "every service" (unknown or empty mapping). */
export function servicesFor(map, audience) {
    const list = map && Object.prototype.hasOwnProperty.call(map, audience) ? map[audience] : null;
    return Array.isArray(list) && list.length ? new Set(list.map(String)) : null;
}

/** Whether a service is listed under the current audience (`allowed` from servicesFor: null = every service). */
export function isAllowed(allowed, service) {
    return !allowed || allowed.has(String(service));
}

/** The prefill detail for a situation code and an audience (unknown codes are dropped). */
export function detailFor(situation, audience) {
    const detail = {};
    const match = typeof situation === 'string' ? SERVICE_RE.exec(situation) : null;
    if (match) {
        detail.service = match[1];
        detail.situation = situation;
    }
    if (['individual', 'business', 'investor'].includes(audience)) detail.audience = audience;
    return detail;
}

/**
 * The live line above the result: the chosen service's count label, else the quick picks' label while at least one
 * pick is shown, else the plain documents label.
 */
export function resultLabel({ countLabel = '', picksShown = 0, picksLabel = '', emptyLabel = '' }) {
    if (countLabel) return countLabel;
    return picksShown > 0 && picksLabel ? picksLabel : emptyLabel;
}

export function mount(form) {
    const select = form.querySelector('select[data-tool-situation]');
    const count = form.querySelector('[data-tool-count]');
    const panels = Array.from(form.querySelectorAll('[data-docs-panel]'));
    const picks = Array.from(form.querySelectorAll('[data-pick-service]'));
    const none = form.querySelector('[data-picks-none]');
    if (!select) return () => {};
    let map = {};
    try {
        map = JSON.parse(form.querySelector('[data-tool-data]')?.textContent || '{}').audiences || {};
    } catch {
        map = {};
    }
    const placeholder = select.querySelector('option[value=""]');
    const groups = Array.from(select.querySelectorAll('optgroup')).map((el) => ({
        el,
        service: (el.querySelector('option')?.value || '').split('-')[0],
    }));

    const audience = () => form.querySelector('input[name="audience"]:checked')?.value || '';

    function show() {
        const match = SERVICE_RE.exec(select.value);
        const service = match ? match[1] : '';
        let countLabel = '';
        let shown = false;
        for (const panel of panels) {
            const on = panel.dataset.docsPanel === service;
            panel.classList.toggle('is-off', !on);
            shown ||= on;
            if (on && service) countLabel = panel.dataset.countLabel || '';
        }
        // A service without documents falls back to the quick picks.
        if (!shown) panels.find((p) => p.dataset.docsPanel === '')?.classList.remove('is-off');
        const picksShown = picks.filter((pick) => !pick.hidden).length;
        if (none) none.hidden = picksShown > 0;
        const label = resultLabel({
            countLabel,
            picksShown,
            picksLabel: count?.dataset.picksLabel || '',
            emptyLabel: count?.dataset.emptyLabel || '',
        });
        if (count && count.textContent !== label) count.textContent = label;
    }

    function filter() {
        const allowed = servicesFor(map, audience());
        const value = select.value;
        // Rebuild the groups in their original order (hidden <optgroup>s are not reliable in every picker).
        for (const g of groups) g.el.remove();
        for (const g of groups) if (isAllowed(allowed, g.service)) select.append(g.el);
        if (value && !select.querySelector(`option[value="${CSS.escape(value)}"]`)) {
            if (placeholder) placeholder.selected = true;
            else select.selectedIndex = 0;
        }
        for (const pick of picks) pick.hidden = !isAllowed(allowed, pick.dataset.pickService);
        show();
    }

    function onChange(event) {
        if (event.target?.name === 'audience') filter();
        else if (event.target === select) show();
    }

    // A quick pick is a submit button (the no-JS path); with JavaScript it only selects its situation.
    function onClick(event) {
        const pick = event.target?.closest?.('[data-quick-pick]');
        if (!pick || !form.contains(pick)) return;
        event.preventDefault();
        const code = pick.dataset.quickPick || '';
        if (!SERVICE_RE.test(code) || !select.querySelector(`option[value="${CSS.escape(code)}"]`)) return;
        select.value = code;
        show();
        select.focus({ preventScroll: true });
    }

    function onSubmit(event) {
        event.preventDefault();
        prefill(detailFor(select.value, audience()));
    }

    form.addEventListener('change', onChange);
    form.addEventListener('click', onClick);
    form.addEventListener('submit', onSubmit);
    filter();

    return () => {
        form.removeEventListener('change', onChange);
        form.removeEventListener('click', onClick);
        form.removeEventListener('submit', onSubmit);
    };
}
