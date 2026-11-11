/**
 * Page script of <x-d2.tool.gratuity> (lazy chunk, mounted by Alpine d2ToolGratuity). Reads the fields,
 * runs the pure estimate (gratuity.js) and renders: field errors, the result rows, badges and notes, the
 * stacked bar, the service timeline, the live announcement, the copied / prefilled summary and the mobile
 * dock. Every string comes from the tool's JSON config (lang/{locale}/proposed-d2/tools.php).
 */
import { estimate } from './gratuity.js';
import { date, decimal, fill, integer, money, parts, percent, plainText, plural } from './format.js';
import { drawTimeline } from './timeline.js';
import {
    announcer,
    bindCopy,
    bindRequest,
    fillParts,
    frameScheduler,
    hasInput,
    keyOf,
    langOf,
    readConfig,
    readValues,
    setDisabled,
    setFieldError,
    swap,
} from './view.js';

/** An error on a field also shows once one of these fields was committed (the error depends on them). */
const RELATED = {
    lastWorkingDay: ['startDate'],
    unpaidAbsenceDays: ['startDate', 'lastWorkingDay'],
    totalMonthlyWage: ['basicMonthly'],
    hoursPerWeek: ['fullTimeHoursPerWeek'],
};

const SEGMENTS = { gratuity: 'gratuity', leave: 'leave', notice: 'notice', unpaid: 'unpaidWages' };

export function mount(root) {
    if (root.__toolMounted) return;
    root.__toolMounted = true;

    const config = readConfig(root);
    const text = config.text ?? {};
    const lang = langOf(root);
    const q = (selector) => root.querySelector(selector);
    const controls = new Map(Array.from(root.querySelectorAll('[data-input]'), (el) => [el.dataset.input, el]));
    const touched = new Set();
    const say = announcer(q('[data-live]'));
    const copyButton = q('[data-action="copy"]');
    const result = q('[data-result]');
    const dock = q('[data-dock]');
    const outText = q('.tool__out-text')?.textContent.trim() ?? '';

    const m = (value) => money(value, lang);
    const days = (n) => fill(plural(config.plurals?.days, n, lang), { count: integer(n, lang) });
    const years = (value) => fill(text.years, { count: decimal(value, lang) });

    let state = null;
    let summary = '';
    let lastResult = null;
    let lastStatus = null;
    let live = false;

    // Fields filled before the script ran (restored by the browser, or a styleguide state) count as committed.
    for (const [key, el] of controls) {
        if (el.tagName === 'INPUT' && hasInput(el)) touched.add(key);
    }

    function buildSummary(values, r) {
        const s = config.summary ?? {};
        const lines = [
            fill(s.title, { scope: config.scopes?.[values.scope] ?? '' }),
            fill(s.service, {
                start: date(values.startDate, lang),
                end: date(values.lastWorkingDay, lang),
                days: days(r.serviceDays),
                years: years(r.years),
                contract: config.contracts?.[values.contractType] ?? '',
            }),
        ];
        if (r.unpaidAbsenceDays > 0) lines.push(fill(s.absence, { days: days(r.unpaidAbsenceDays) }));
        lines.push(fill(s.wage, { basic: m(values.basicMonthly), total: values.totalMonthlyWage ? m(values.totalMonthlyWage) : text.notEntered }));
        lines.push(fill(s.gratuity, { amount: m(r.gratuity), part21: m(r.part21), part30: m(r.part30) }));
        if (r.capApplied) lines.push(plainText(parts(text.capNote, { amount: m(r.cap) })));
        if (!r.eligible) lines.push(r.reason === 'temporary' ? text.temporary : text.underYear);
        lines.push(fill(s.others, { leave: m(r.leave), notice: m(r.notice), unpaid: m(r.unpaidWages) }));
        lines.push(fill(s.total, { amount: m(r.total) }));
        lines.push(text.estimateOnly);
        return lines.filter(Boolean).join('\n');
    }

    function note(name, content) {
        const node = q(`[data-note="${name}"]`);
        if (!node) return;
        node.hidden = !content;
        if (content && typeof content !== 'boolean') fillParts(node, content);
    }

    function flag(name, on) {
        const node = q(`[data-flag="${name}"]`);
        if (node) node.hidden = !on;
    }

    function drawService(r) {
        const box = q('[data-timeline]');
        if (box && r) drawTimeline(box, r, { lang, id: root.id || 'gr', text });
    }

    // ------------------------------------------------------------------ mobile dock
    // Shown while the fields are on screen and the result card is not; never while the visitor is
    // editing (it would cover the focused field: WCAG 2.4.11), unless the focus is on the dock itself.
    const narrow = window.matchMedia('(width < 1280px)');
    let formSeen = false;
    let resultSeen = false;
    function updateDock() {
        if (!dock) return;
        const active = document.activeElement;
        const editing = root.contains(active) && active !== dock;
        dock.hidden = !(narrow.matches && state?.status === 'ok' && formSeen && !resultSeen && !editing);
    }
    root.addEventListener('focusin', () => updateDock());
    root.addEventListener('focusout', () => window.requestAnimationFrame(updateDock));
    if (dock && 'IntersectionObserver' in window) {
        const form = q('.tool__form');
        const io = new IntersectionObserver((entries) => {
            for (const entry of entries) {
                if (entry.target === form) formSeen = entry.isIntersecting;
                else resultSeen = entry.isIntersecting;
            }
            updateDock();
        });
        if (form) io.observe(form);
        if (result) io.observe(result);
        narrow.addEventListener('change', updateDock);
    }

    // ------------------------------------------------------------------ render
    function render() {
        const out = estimate(readValues(root, lang));
        state = out;
        root.dataset.scope = out.status === 'out_of_scope' ? 'out' : 'in';
        root.dataset.contract = out.values.contractType;

        const shown = [];
        for (const [key, el] of controls) {
            if (el.tagName === 'FIELDSET' || el.tagName === 'SELECT') continue;
            const code = out.errors[key];
            const visible = Boolean(code) && (touched.has(key) || (RELATED[key] ?? []).some((k) => touched.has(k)));
            const message = visible ? config.errors?.[code] ?? '' : null;
            setFieldError(el, message);
            if (message) shown.push(message);
        }

        const ok = out.status === 'ok';
        const r = out.result;
        const view = ok ? 'result' : shown.length > 0 ? 'invalid' : 'empty';
        root.dataset.state = out.status === 'out_of_scope' ? 'out' : view;
        const emptyNote = q('[data-state="empty"]');
        emptyNote.hidden = view !== 'empty';
        // Part-time with only the hours missing: say so, instead of asking for the dates and the wage again.
        const onlyHours = out.missing.length > 0 && out.missing.every((key) => key === 'hoursPerWeek' || key === 'fullTimeHoursPerWeek');
        const emptyText = onlyHours ? text.emptyHours : text.emptyFields;
        if (emptyText && emptyNote.textContent !== emptyText) emptyNote.textContent = emptyText;
        q('[data-state="invalid"]').hidden = view !== 'invalid';

        // D31: "—" until the required fields are valid (never a grey "AED 0.00"). D32: the breakdown appears with a
        // result only, so no column of dashes reads like a row of toggles.
        const dash = text.empty ?? '—';
        swap(q('[data-out="total"]'), ok ? m(r.total) : dash);
        swap(q('[data-out="dock"]'), ok ? m(r.total) : dash);
        const breakdown = q('[data-breakdown]');
        if (breakdown) breakdown.hidden = !ok;
        if (ok) {
            swap(q('[data-out="gratuity"]'), m(r.gratuity));
            swap(q('[data-out="part21"]'), m(r.part21));
            swap(q('[data-out="part30"]'), m(r.part30));
            swap(q('[data-out="leave"]'), m(r.leave));
            swap(q('[data-out="notice"]'), m(r.notice));
            swap(q('[data-out="unpaid"]'), m(r.unpaidWages));
        }

        // D34: the composition bar and its legend (the keys of the parts that have a value) only with values.
        const bar = q('[data-bar]');
        if (bar) bar.hidden = !(ok && r.total > 0);
        for (const seg of root.querySelectorAll('[data-seg]')) {
            const value = ok ? r[SEGMENTS[seg.dataset.seg]] ?? 0 : 0;
            const share = ok && r.total > 0 ? (value / r.total) * 100 : 0;
            seg.style.setProperty('--share', share.toFixed(3));
            seg.toggleAttribute('data-on', share > 0);
            root.querySelector(`[data-key="${seg.dataset.seg}"]`)?.toggleAttribute('data-on', share > 0);
        }

        flag('cap', ok && r.capApplied);
        flag('cap-basic', ok && r.eligible && r.capBasis === 'basic');
        note('cap', ok && r.capApplied ? parts(text.capNote, { amount: { bdi: m(r.cap) } }) : null);
        note('cap-basic', ok && r.eligible && r.capBasis === 'basic');
        note('eligibility', ok && !r.eligible ? (r.reason === 'temporary' ? text.temporary : text.underYear) : null);
        note('part-time', ok && r.eligible && r.contract === 'part' ? parts(text.partTime, { percent: percent(r.ratio, lang) }) : null);

        const service = q('[data-service]');
        if (service) service.hidden = !ok;
        if (ok) {
            swap(q('[data-out="service"]'), fill(text.service, { days: days(r.serviceDays), years: years(r.years) }));
            const absence = q('[data-tl-absence]');
            if (absence) {
                absence.hidden = r.unpaidAbsenceDays <= 0;
                absence.textContent = r.unpaidAbsenceDays > 0 ? fill(text.absence, { days: days(r.unpaidAbsenceDays) }) : '';
            }
            lastResult = r;
            drawService(r);
        }

        setDisabled(copyButton, !ok);
        summary = ok ? buildSummary(out.values, r) : '';
        updateDock();

        const changed = out.status !== lastStatus;
        lastStatus = out.status;
        if (!live) return; // nothing is announced for the state the page opened with
        if (out.status === 'out_of_scope') {
            if (changed) say(outText, { force: true });
        } else if (ok) say(fill(text.live, { amount: m(r.total) }));
        else if (shown.length > 0) say(shown[0]);
    }

    const schedule = frameScheduler(render);
    root.addEventListener('input', schedule);
    root.addEventListener('change', (event) => {
        const key = keyOf(event.target);
        if (key) touched.add(key);
        schedule();
    });
    root.addEventListener('focusout', (event) => {
        const key = keyOf(event.target);
        const el = key ? controls.get(key) : null;
        if (el && el.tagName === 'INPUT' && hasInput(el) && !touched.has(key)) {
            touched.add(key);
            schedule();
        }
    });
    window.addEventListener('pageshow', schedule);

    const timeline = q('[data-timeline]');
    if (timeline && 'ResizeObserver' in window) {
        let width = 0;
        new ResizeObserver(([entry]) => {
            const next = Math.round(entry.contentRect.width);
            if (next === width) return;
            width = next;
            if (lastResult && state?.status === 'ok') drawService(lastResult);
        }).observe(timeline);
    }

    bindCopy(root, () => summary, text);
    bindRequest(root, () => {
        const detail = { service: config.service || 'S06', tool: 'gratuity' };
        if (state?.status === 'ok' && summary) detail.summary = summary;
        else if (state?.status === 'out_of_scope') detail.summary = fill(text.outSummary, { scope: config.scopes?.[state.values.scope] ?? '' });
        return detail;
    });

    render();
    live = true;
    root.classList.add('is-ready');
    // The first render may run before the result card has its width (fonts, hidden panel): redraw then.
    window.requestAnimationFrame(() => {
        if (lastResult && state?.status === 'ok') drawService(lastResult);
    });
}
