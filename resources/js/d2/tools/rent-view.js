/**
 * Page script of <x-d2.tool.rent> (lazy chunk, mounted by Alpine d2ToolRent). Reads the four fields, runs
 * the pure check (rent.js) and renders the maximum increase (% and AED), the gap sentence, the active step
 * of the server-rendered band scale, the 90-day notice check, the live announcement and the summary.
 * Every string comes from the tool's JSON config (lang/{locale}/proposed-d2/tools.php).
 */
import { estimate } from './rent.js';
import { date, fill, integer, money, parts, percent, plural } from './format.js';
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
    const steps = Array.from(root.querySelectorAll('[data-band]'));
    const noticeBox = q('[data-notice]');

    const m = (value) => money(value, lang);
    const days = (n) => fill(plural(config.plurals?.days, n, lang), { count: integer(Math.abs(n), lang) });
    const rate = (increase) => percent(increase / 100, lang);

    let state = null;
    let summary = '';
    let live = false;

    for (const [key, el] of controls) {
        if (el.tagName === 'INPUT' && hasInput(el)) touched.add(key);
    }

    /** The notice sentence (and its tone) for a result's notice check. */
    function noticeText(notice) {
        if (!notice) return { tone: 'neutral', text: text.noticeMissing };
        if (notice.after) return { tone: 'warning', text: text.noticeAfter };
        return { tone: notice.ok ? 'success' : 'warning', text: fill(notice.ok ? text.noticeOk : text.noticeShort, { days: days(notice.days) }) };
    }

    function gapText(r) {
        return r.belowAverage ? fill(text.gapBelow, { percent: percent(r.gapBasisPoints / 10000, lang) }) : text.gapNotBelow;
    }

    function buildSummary(values, r) {
        const s = config.summary ?? {};
        const lines = [
            s.title,
            fill(s.rents, { current: m(values.currentAnnualRent), average: m(values.indexAverageRent) }),
            gapText(r),
            fill(s.result, { percent: rate(r.increase), amount: m(r.increaseAmount), rent: m(r.newRent) }),
        ];
        if (values.expiryDate || values.noticeReceivedDate) {
            lines.push(fill(s.dates, {
                expiry: values.expiryDate ? date(values.expiryDate, lang) : text.notEntered,
                notice: values.noticeReceivedDate ? date(values.noticeReceivedDate, lang) : text.notEntered,
            }));
        }
        if (r.notice) lines.push(noticeText(r.notice).text);
        lines.push(text.estimateOnly);
        return lines.filter(Boolean).join('\n');
    }

    function render() {
        const out = estimate(readValues(root, lang));
        state = out;

        const shown = [];
        for (const [key, el] of controls) {
            const code = out.errors[key];
            const message = code && touched.has(key) ? config.errors?.[code] ?? '' : null;
            setFieldError(el, message);
            if (message) shown.push(message);
        }

        const ok = out.status === 'ok';
        const r = out.result;
        const view = ok ? 'result' : shown.length > 0 ? 'invalid' : 'empty';
        root.dataset.state = view;
        q('[data-state="empty"]').hidden = view !== 'empty';
        q('[data-state="invalid"]').hidden = view !== 'invalid';

        // D31: "—" until both rents are valid (0% would read as "no increase allowed"); the amounts with the result.
        const dash = text.empty ?? '—';
        swap(q('[data-out="rate"]'), ok ? rate(r.increase) : dash);
        const increase = q('[data-out="increase"]');
        if (increase) {
            increase.hidden = !ok;
            if (ok) swap(increase, parts(text.perYear, { amount: { bdi: m(r.increaseAmount) } }));
        }
        const newRent = q('[data-out="new-rent"]');
        if (newRent) {
            newRent.hidden = !ok;
            if (ok) swap(newRent, parts(text.newRent, { amount: { bdi: m(r.newRent) } }));
        }
        const gap = q('[data-out="gap"]');
        if (gap) {
            gap.hidden = !ok;
            if (ok) swap(gap, gapText(r));
        }

        for (const step of steps) {
            const active = ok && Number(step.dataset.band) === r.band;
            step.classList.toggle('is-active', active);
            if (active) step.setAttribute('aria-current', 'true');
            else step.removeAttribute('aria-current');
            const you = step.querySelector('[data-you]');
            if (you) you.hidden = !active;
        }

        const notice = ok ? noticeText(r.notice) : null;
        if (noticeBox) {
            noticeBox.hidden = !ok;
            noticeBox.dataset.tone = notice?.tone ?? 'neutral';
            if (notice) fillParts(q('[data-out="notice"]'), notice.text);
        }

        setDisabled(copyButton, !ok);
        summary = ok ? buildSummary(out.values, r) : '';

        if (!live) return;
        if (ok) say(fill(text.live, { percent: rate(r.increase), amount: m(r.increaseAmount) }) + (r.notice && !r.notice.ok ? ` ${notice.text}` : ''));
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
        if (el && hasInput(el) && !touched.has(key)) {
            touched.add(key);
            schedule();
        }
    });
    window.addEventListener('pageshow', schedule);

    bindCopy(root, () => summary, text);
    bindRequest(root, () => {
        const detail = { service: config.service || 'S05', tool: 'rent' };
        if (state?.status === 'ok' && summary) detail.summary = summary;
        return detail;
    });

    render();
    live = true;
    root.classList.add('is-ready');
}
