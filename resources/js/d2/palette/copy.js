/**
 * Server-provided copy for Design 2 scripts (docs/d2/engineering.md §0 rule 2: JavaScript never
 * hard-codes visible text). Blade renders the strings into data attributes or a JSON block; these helpers
 * fill their Laravel-style ":name" placeholders and pick plural forms.
 *
 *   plural(forms, n, locale)        forms keyed by CLDR plural category (zero one two few many other),
 *                                   rendered by Blade with trans_choice; returns the form for n
 *   fill(template, values)          'Open now · closes :time UAE time' + { time: '18:00' } → string
 *   fragment(template, values, isolate)
 *                                   the same as DOM nodes; values named in `isolate` ({ time: 'ltr',
 *                                   query: 'auto' }) are wrapped in <bdi>, so numbers, times and the
 *                                   visitor's own text keep their direction inside Arabic sentences
 *   formatNumber(n, locale)         Western digits in every language (spec §11 item 9), up to 2 decimals
 *   readJson(id)                    parses <script type="application/json" id="…">, or null
 */

const PLACEHOLDER = /:([a-z_]+)/gi;
const rules = new Map();

export function pluralCategory(n, locale = 'en') {
    try {
        let rule = rules.get(locale);
        if (!rule) {
            rule = new Intl.PluralRules(locale);
            rules.set(locale, rule);
        }
        return rule.select(n);
    } catch {
        return n === 1 ? 'one' : 'other';
    }
}

export function plural(forms, n, locale = 'en') {
    if (!forms) return '';
    if (typeof forms === 'string') return forms;
    const category = pluralCategory(n, locale);
    return forms[category] ?? forms.other ?? forms.many ?? Object.values(forms)[0] ?? '';
}

export function formatNumber(n, locale = 'en') {
    try {
        return new Intl.NumberFormat(`${locale}-u-nu-latn`, { maximumFractionDigits: 2 }).format(n);
    } catch {
        return String(n);
    }
}

/** [{ text }, { text, name }, …]: literal runs and filled placeholders, in order. */
export function segments(template, values = {}) {
    const source = String(template ?? '');
    const out = [];
    let last = 0;
    for (const match of source.matchAll(PLACEHOLDER)) {
        const name = match[1];
        if (!Object.prototype.hasOwnProperty.call(values, name)) continue;
        if (match.index > last) out.push({ text: source.slice(last, match.index) });
        out.push({ text: String(values[name] ?? ''), name });
        last = match.index + match[0].length;
    }
    if (last < source.length) out.push({ text: source.slice(last) });
    return out;
}

export function fill(template, values = {}) {
    return segments(template, values)
        .map((part) => part.text)
        .join('');
}

export function fragment(template, values = {}, isolate = {}) {
    const frag = document.createDocumentFragment();
    for (const part of segments(template, values)) {
        if (part.name && isolate[part.name]) {
            const bdi = document.createElement('bdi');
            if (isolate[part.name] !== 'auto') bdi.dir = isolate[part.name];
            bdi.textContent = part.text;
            frag.append(bdi);
        } else if (part.text) {
            frag.append(document.createTextNode(part.text));
        }
    }
    return frag;
}

export function readJson(id) {
    const el = typeof document !== 'undefined' ? document.getElementById(id) : null;
    if (!el) return null;
    try {
        return JSON.parse(el.textContent || 'null');
    } catch {
        return null;
    }
}
