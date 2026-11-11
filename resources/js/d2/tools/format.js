/**
 * Locale formatting and parsing for the home tools. Pure: Intl only, no DOM.
 *
 * Digits: Latin (0–9) in every language, the same digits the visitor types into the fields (spec §11
 * item 9 leaves Western vs Arabic-Indic digits for Arabic to the client: change NUMBERING here).
 * Amounts: Intl currency format with the AED code ("AED 35,000.00", "35 000,00 AED",
 * "‏35,000.00 AED"); the view prints them inside <bdi>. Dates: Gregorian, UTC (ISO values).
 */
const LOCALES = { en: 'en-AE', ar: 'ar-AE', ru: 'ru-RU' };
const NUMBERING = 'latn';
const cache = new Map();

/** Intl locale for a page language ('en', 'ar', 'ru', 'ar-AE' …); English for anything else. */
export function intlLocale(lang) {
    const base = String(lang || 'en').toLowerCase().split(/[-_]/)[0];
    return LOCALES[base] ?? LOCALES.en;
}

function numberFormat(lang, options) {
    const locale = intlLocale(lang);
    const key = `n|${locale}|${JSON.stringify(options)}`;
    let format = cache.get(key);
    if (!format) {
        format = new Intl.NumberFormat(locale, { numberingSystem: NUMBERING, ...options });
        cache.set(key, format);
    }
    return format;
}

/** "AED 35,000.00" (always two decimals: fils). */
export const money = (value, lang) =>
    numberFormat(lang, { style: 'currency', currency: 'AED', currencyDisplay: 'code', minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);

/** A fraction as a percentage with up to `digits` decimals: 0.1235 → "12.35%". */
export const percent = (fraction, lang, digits = 2) =>
    numberFormat(lang, { style: 'percent', minimumFractionDigits: 0, maximumFractionDigits: digits }).format(fraction);

/** A whole number with grouping: 1825 → "1,825". */
export const integer = (value, lang) => numberFormat(lang, { maximumFractionDigits: 0 }).format(value);

/** A number with exactly `digits` decimals: 5 → "5.00". */
export const decimal = (value, lang, digits = 2) =>
    numberFormat(lang, { minimumFractionDigits: digits, maximumFractionDigits: digits }).format(value);

/** An ISO date (YYYY-MM-DD) as a short Gregorian date: "5 Jan 2026". The input is returned if invalid. */
export function date(iso, lang) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(iso ?? ''));
    if (!match) return String(iso ?? '');
    const locale = intlLocale(lang);
    const key = `d|${locale}`;
    let format = cache.get(key);
    if (!format) {
        format = new Intl.DateTimeFormat(locale, {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            timeZone: 'UTC',
            calendar: 'gregory',
            numberingSystem: NUMBERING,
        });
        cache.set(key, format);
    }
    return format.format(new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3]))));
}

/**
 * The plural form for a count: `forms` maps CLDR categories (zero, one, two, few, many, other) to
 * templates, built on the server from trans_choice() (ToolStrings in the Blade components), e.g.
 * { one: ':count day', other: ':count days' }. Falls back to `other`, then to the last form.
 */
export function plural(forms, count, lang) {
    if (!forms || typeof forms !== 'object') return '';
    const key = `p|${intlLocale(lang)}`;
    let rules = cache.get(key);
    if (!rules) {
        rules = new Intl.PluralRules(intlLocale(lang));
        cache.set(key, rules);
    }
    const values = Object.values(forms);
    return forms[rules.select(count)] ?? forms.other ?? values[values.length - 1] ?? '';
}

/** Laravel-style placeholders: fill('Total: :amount', { amount: 'AED 5.00' }). Unknown keys stay. */
export function fill(template, values = {}) {
    return String(template ?? '').replace(/:([A-Za-z_][A-Za-z0-9_]*)/g, (match, key) =>
        Object.prototype.hasOwnProperty.call(values, key) ? String(values[key]) : match,
    );
}

/**
 * The same template as a list of parts for the DOM: plain strings, and { bdi: text } for values that must
 * keep their own direction inside Arabic sentences (AED amounts). The view turns { bdi } into <bdi>.
 *   parts('Limited to :amount.', { amount: { bdi: 'AED 240,000.00' } }) → ['Limited to ', { bdi: '…' }, '.']
 */
export function parts(template, values = {}) {
    const source = String(template ?? '');
    const out = [];
    const pattern = /:([A-Za-z_][A-Za-z0-9_]*)/g;
    let last = 0;
    let match;
    while ((match = pattern.exec(source)) !== null) {
        if (!Object.prototype.hasOwnProperty.call(values, match[1])) continue;
        if (match.index > last) out.push(source.slice(last, match.index));
        const value = values[match[1]];
        out.push(value !== null && typeof value === 'object' ? { bdi: String(value.bdi ?? '') } : String(value));
        last = pattern.lastIndex;
    }
    if (last < source.length) out.push(source.slice(last));
    return out;
}

/** The plain text of parts() output (for the copied summary and the live announcement). */
export const plainText = (list) => list.map((part) => (typeof part === 'string' ? part : part.bdi)).join('');

const BIDI_MARKS = /[؜‎‏‪-‮⁦-⁩]/g;
const SPACES = /[\s  '’_]/g;

/**
 * A number typed by the visitor: null when empty, NaN when it is not a number. Accepts Arabic-Indic
 * digits (٠–٩, ۰–۹), the Arabic decimal and group separators (٫ ٬), spaces / apostrophes as thousands
 * separators, an AED code or د.إ, and either "10,000.50" or "10 000,50": when both "," and "." appear
 * the last one is the decimal point; a lone "," is the decimal point in Russian, a thousands separator
 * elsewhere when exactly three digits follow it ("10,000"), otherwise a decimal comma ("12,5").
 */
export function parseNumber(value, lang) {
    if (value === null || value === undefined) return null;
    if (typeof value === 'number') return Number.isFinite(value) ? value : NaN;
    let s = String(value)
        .replace(BIDI_MARKS, '')
        .replace(/[٠-٩]/g, (d) => String(d.charCodeAt(0) - 0x0660))
        .replace(/[۰-۹]/g, (d) => String(d.charCodeAt(0) - 0x06f0))
        .replace(/٫/g, '.')
        .replace(/٬/g, ',')
        .replace(/[−‒–]/g, '-')
        .trim();
    if (s === '') return null;
    s = s.replace(/^(aed|د\.?\s?إ\.?)/i, '').replace(/(aed|د\.?\s?إ\.?)$/i, '').replace(SPACES, '');
    if (s.startsWith('+')) s = s.slice(1);
    if (s === '') return NaN;

    const comma = s.lastIndexOf(',');
    const dot = s.lastIndexOf('.');
    if (comma !== -1 && dot !== -1) {
        // Both: the later one is the decimal separator, the other groups thousands.
        s = comma > dot ? s.replace(/\./g, '').replace(',', '.') : s.replace(/,/g, '');
    } else if (comma !== -1) {
        const groups = /^-?\d{1,3}(,\d{3})+$/.test(s);
        const russian = intlLocale(lang).startsWith('ru');
        if (groups && (!russian || s.split(',').length > 2)) s = s.replace(/,/g, '');
        else if (s.split(',').length === 2) s = s.replace(',', '.');
        else return NaN;
    } else if (dot !== -1 && s.split('.').length > 2) {
        // "1.000.000": dots group thousands.
        s = /^-?\d{1,3}(\.\d{3})+$/.test(s) ? s.replace(/\./g, '') : 'x';
    }
    return /^-?\d+(\.\d+)?$/.test(s) ? Number(s) : NaN;
}
