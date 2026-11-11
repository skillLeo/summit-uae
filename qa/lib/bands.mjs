/**
 * Empty-band detection (checklist §9.1: no empty vertical band taller than 160 px at any width).
 *
 * The page script (runtime.mjs measurePage) collects the vertical extent of everything a visitor can see
 * (text lines, images, icons, controls, cards, and the top / bottom edges of full-width surfaces) as
 * [top, bottom, label] intervals in page coordinates. Merged, the holes between them are the bands where
 * nothing at all is drawn across the full width. Both functions are pure (no DOM): mergeIntervals is also
 * injected into the page by runtime.mjs, so it must stay self-contained.
 */

/**
 * Merges [top, bottom, label?] intervals (any order) into sorted, disjoint [top, bottom, firstLabel, lastLabel]
 * intervals: firstLabel is the item that starts the run, lastLabel the item that reaches its bottom.
 * Intervals that overlap or touch (gap <= join) merge.
 */
export function mergeIntervals(items, join = 0) {
    const sorted = items
        .filter((i) => Array.isArray(i) && Number.isFinite(i[0]) && Number.isFinite(i[1]) && i[1] > i[0])
        .sort((a, b) => a[0] - b[0] || a[1] - b[1]);
    const out = [];
    for (const item of sorted) {
        const top = item[0];
        const bottom = item[1];
        const label = item.length > 2 ? item[2] : null;
        const last = out[out.length - 1];
        if (last && top <= last[1] + join) {
            if (bottom > last[1]) {
                last[1] = bottom;
                last[3] = label;
            }
        } else {
            out.push([top, bottom, label, label]);
        }
    }
    return out;
}

/**
 * Bands taller than `min` between merged intervals, plus the page edges when `height` is given.
 * `labels` maps the label indexes used in the intervals to readable element descriptions.
 */
export function findBands(merged, { min = 160, height = null, labels = null } = {}) {
    const name = (i) => (i === null || i === undefined ? null : labels ? (labels[i] ?? String(i)) : i);
    const bands = [];
    let prevBottom = 0;
    let prevLabel = null;
    merged.forEach(([top, bottom, first, last], index) => {
        const gap = top - prevBottom;
        if (gap > min) {
            bands.push({
                top: Math.round(prevBottom),
                bottom: Math.round(top),
                height: Math.round(gap),
                above: name(prevLabel),
                below: name(first),
                edge: index === 0 ? 'top' : null,
            });
        }
        if (bottom > prevBottom) {
            prevBottom = bottom;
            prevLabel = last;
        }
    });
    if (height !== null && height - prevBottom > min) {
        bands.push({
            top: Math.round(prevBottom),
            bottom: Math.round(height),
            height: Math.round(height - prevBottom),
            above: name(prevLabel),
            below: null,
            edge: 'bottom',
        });
    }
    return bands;
}
