/**
 * Gratuity service timeline (spec §12 C6): an SVG bar from 0 to N years with the counted service
 * (21-day zone up to 5 years, 30-day zone after it), a pin at 1 year (the gratuity starts), the 5-year
 * boundary labelled "21 days/yr | 30 days/yr", and a hatched notch for unpaid absence (not counted),
 * drawn after the counted service because the absence dates are not known.
 *
 * The SVG is drawn in real pixels (re-drawn when its box is resized), so the rounded track, the pin and
 * the hatching never stretch. x runs from the inline start: mirrored in RTL. The labels are HTML (page
 * fonts, 14 px minimum) placed with inset-inline-start, so they mirror by themselves. Purely visual: the
 * container is aria-hidden; the same facts are text next to it (service counted, the absence line).
 */
import { integer } from './format.js';

const NS = 'http://www.w3.org/2000/svg';
const HEIGHT = 28;
const BAR_Y = 11;
const BAR_H = 8;

function el(name, attrs = {}, parent = null) {
    const node = document.createElementNS(NS, name);
    for (const [key, value] of Object.entries(attrs)) node.setAttribute(key, String(value));
    if (parent) parent.append(node);
    return node;
}

/** Tick spacing for an axis of `max` whole years: about ten ticks at most. */
export function tickStep(max) {
    if (max <= 10) return 1;
    if (max <= 20) return 2;
    if (max <= 50) return 5;
    return 10;
}

/**
 * The axis: whole years covering the calendar span (service + unpaid absence), at least one year,
 * rounded up to a whole number of tick steps so the last tick is a regular one (41 years → 0…45).
 */
export function axisMax(calendarYears) {
    const years = Math.max(1, Math.ceil(calendarYears - 1e-9));
    const step = tickStep(years);
    return Math.ceil(years / step) * step;
}

/**
 * Draw (or re-draw) the timeline for a gratuity result into the [data-timeline] box.
 * @param {HTMLElement} box
 * @param {{ years: number, calendarYears: number, unpaidAbsenceDays: number }} result
 * @param {{ lang: string, id: string, text: { zone21: string, zone30: string } }} options
 */
export function drawTimeline(box, result, { lang, id, text }) {
    const svg = box.querySelector('[data-tl-svg]');
    const labels = box.querySelector('[data-tl-labels]');
    const ticks = box.querySelector('[data-tl-ticks]');
    if (!svg || !labels || !ticks) return;
    const width = Math.round(svg.getBoundingClientRect().width || box.clientWidth || 0);
    if (width < 40) return;

    const rtl = getComputedStyle(box).direction === 'rtl';
    const max = axisMax(result.calendarYears);
    const along = (years) => (Math.min(Math.max(years, 0), max) / max) * width; // from the inline start
    const x = (years) => (rtl ? width - along(years) : along(years)); // SVG (physical) x
    const span = (from, to) => {
        const a = x(from);
        const b = x(to);
        return { x: Math.min(a, b), width: Math.abs(b - a) };
    };

    svg.setAttribute('viewBox', `0 0 ${width} ${HEIGHT}`);
    svg.setAttribute('height', String(HEIGHT));
    svg.replaceChildren();
    const defs = el('defs', {}, svg);
    const clipId = `${id}-tl-clip`;
    const hatchId = `${id}-tl-hatch`;
    const clip = el('clipPath', { id: clipId }, defs);
    el('rect', { x: 0, y: BAR_Y, width, height: BAR_H, rx: BAR_H / 2 }, clip);
    const hatch = el('pattern', { id: hatchId, patternUnits: 'userSpaceOnUse', width: 6, height: 6, patternTransform: `rotate(${rtl ? -45 : 45})` }, defs);
    el('rect', { width: 6, height: 6, class: 'tl__hatch-bg' }, hatch);
    el('line', { x1: 0, y1: 0, x2: 0, y2: 6, class: 'tl__hatch' }, hatch);

    el('rect', { x: 0, y: BAR_Y, width, height: BAR_H, rx: BAR_H / 2, class: 'tl__track' }, svg);
    const fills = el('g', { 'clip-path': `url(#${clipId})` }, svg);
    const years = result.years;
    const first = span(0, Math.min(years, 5));
    el('rect', { ...first, y: BAR_Y, height: BAR_H, class: 'tl__fill tl__fill--21' }, fills);
    if (years > 5) el('rect', { ...span(5, years), y: BAR_Y, height: BAR_H, class: 'tl__fill tl__fill--30' }, fills);
    if (result.unpaidAbsenceDays > 0) {
        const notch = span(years, result.calendarYears);
        el('rect', { ...notch, y: BAR_Y, height: BAR_H, fill: `url(#${hatchId})`, class: 'tl__absence' }, fills);
    }

    // Ticks under the bar, and the 5-year boundary through it.
    const step = tickStep(max);
    const marks = [];
    for (let year = 0; year <= max; year += step) marks.push(year);
    for (const year of marks) {
        const tx = Math.min(Math.max(x(year), 0.75), width - 0.75);
        el('line', { x1: tx, x2: tx, y1: BAR_Y + BAR_H + 3, y2: BAR_Y + BAR_H + 7, class: 'tl__tick' }, svg);
    }
    if (max > 5) el('line', { x1: x(5), x2: x(5), y1: BAR_Y - 5, y2: BAR_Y + BAR_H + 4, class: 'tl__boundary' }, svg);

    // Pin at 1 year: a small downward triangle above the bar.
    const px = Math.min(Math.max(x(1), 5), width - 5);
    el('path', { d: `M ${px - 5} 1 H ${px + 5} L ${px} 8 Z`, class: 'tl__pin' }, svg);

    // Tick labels (HTML, from the inline start).
    ticks.replaceChildren(
        ...marks.map((year, i) => {
            const label = document.createElement('span');
            label.className = 'tl__tick-label';
            if (i === 0) label.classList.add('is-first');
            if (i === marks.length - 1) label.classList.add('is-last');
            if (year === 1 || (year === 5 && max > 5)) label.classList.add('is-key');
            label.textContent = integer(year, lang);
            label.style.setProperty('--at', `${along(year)}px`);
            return label;
        }),
    );

    // Zone labels: "21 days/yr | 30 days/yr" with the divider on the 5-year boundary (kept inside the box).
    const zone21 = document.createElement('span');
    zone21.className = 'tl__zone tl__zone--21';
    zone21.textContent = text.zone21;
    const group = document.createElement('span');
    group.className = 'tl__zones';
    group.append(zone21);
    let zone30 = null;
    if (max > 5 && years > 5) {
        zone30 = document.createElement('span');
        zone30.className = 'tl__zone tl__zone--30';
        zone30.textContent = text.zone30;
        group.append(zone30);
    }
    labels.replaceChildren(group);
    let start = 0;
    if (zone30) {
        const before = zone21.getBoundingClientRect().width;
        const total = group.getBoundingClientRect().width;
        start = Math.min(Math.max(along(5) - before, 0), Math.max(width - total, 0));
    }
    group.style.setProperty('--at', `${start}px`);
}
