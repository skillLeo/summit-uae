/**
 * The d2:palette-open protocol, pure parts (docs/d2/engineering.md §4a; used by components/palette.js, so
 * this file is in the main bundle: keep it tiny).
 *
 *   paletteQuery(value, max)   the optional detail.query: text a caller wants in the palette's field, with
 *                              its results shown. Untrusted (any script on the page may dispatch the event):
 *                              strings only; control and bidirectional formatting characters become spaces,
 *                              whitespace is collapsed, the ends are trimmed and the text is cut to the
 *                              field's maxlength (120 UTF-16 units, never inside a character). Anything else
 *                              gives '' and the palette opens as usual.
 *   openAction(state)          what an event does: 'open' or 'open-query' (closed palette), 'query' (open:
 *                              replace the text and show its results), 'close' (Ctrl/Cmd+K toggles an open
 *                              palette closed), 'focus' (anything else while open). A query never closes it.
 *
 * Vitest: palette/query.test.js and components/__tests__/palette.test.js.
 */
export const QUERY_MAX = 120;

// C0 and C1 controls (tab and line breaks included) and the bidi embeddings, overrides and isolates
// (U+202A–U+202E, U+2066–U+2069), which could make the field show something else than it searches.
const UNSAFE = /[\u0000-\u001F\u007F-\u009F‪-‮⁦-⁩]/g;

export function paletteQuery(value, max = QUERY_MAX) {
    if (typeof value !== 'string') return '';
    const clean = value.replace(UNSAFE, ' ').replace(/\s+/g, ' ').trim();
    const limit = Number.isInteger(max) && max > 0 ? max : QUERY_MAX;
    if (clean.length <= limit) return clean;
    let cut = '';
    for (const char of clean) {
        if (cut.length + char.length > limit) break;
        cut += char;
    }
    return cut.trimEnd();
}

export function openAction({ isOpen = false, source = '', query = '' } = {}) {
    if (!isOpen) return query ? 'open-query' : 'open';
    if (query) return 'query';
    return source === 'shortcut-k' ? 'close' : 'focus';
}
