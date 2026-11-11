/**
 * The Arabic digits setting for scripts (Design 2.1 brief §3.7, fix D11).
 *
 * App\Design\D2\Digits converts every Design 2 Arabic page at render time (config design.d2.arabic_digits,
 * SUMMIT_ARABIC_DIGITS) and marks it <html data-digits="western|eastern">; "western" (0-9) is the default. English
 * and Russian pages carry no attribute: their own text always uses 0-9, and their Arabic blocks (lang="ar", such as
 * the styleguide's specimens in the other direction) are converted at render time only, so no script writes into
 * them (modules/hours.js leaves a slot inside a block in another language empty). So that a page never mixes 0-9
 * and ٠-٩, whatever a script writes follows the same setting:
 *
 *   digitsMode()               'western' | 'eastern', read from <html data-digits>
 *   numberLocale(locale)       a locale tag for Intl.NumberFormat / DateTimeFormat with the page's digits:
 *                              'ar-u-nu-latn' (western) or 'ar-u-nu-arab' (eastern); other languages always latn
 *   localiseDigits(text)       the digits of a ready-made string (a search result, a JSON copy line) converted the
 *                              way the server converts the page: western ٠-٩ → 0-9 (٫ ٬ between digits → . ,);
 *                              eastern 0-9 → ٠-٩ (. , between digits → ٫ ٬) except phone numbers (+971 …, 00 …),
 *                              runs of seven or more digits, e-mail addresses, URLs, Latin and Cyrillic runs
 *                              ("AED 1,000", "S01", "Art. 53"), digits fused to Latin letters ("10MB") and
 *                              character references. Text inside <bdi dir="ltr"> stays as it is: never pass it here.
 *
 * The rules mirror App\Design\D2\Digits::text(); lib/digits.test.js and tests/Feature/Design/D2/DigitsTest.php
 * share the same cases.
 */

export const WESTERN = 'western';
export const EASTERN = 'eastern';

const TO_WESTERN = {
    '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9',
    '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4', '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
};
const TO_EASTERN = {
    0: '٠', 1: '١', 2: '٢', 3: '٣', 4: '٤', 5: '٥', 6: '٦', 7: '٧', 8: '٨', 9: '٩',
    '۰': '٠', '۱': '١', '۲': '٢', '۳': '٣', '۴': '٤', '۵': '٥', '۶': '٦', '۷': '٧', '۸': '٨', '۹': '٩',
    '.': '٫', ',': '٬',
};

// Group 1: what keeps its Western digits; otherwise a number to convert (Digits::EASTERN_SPLIT in PHP).
// No lookbehind anywhere in this file: Safari before 16.4 (inside Vite's default build target) cannot parse it,
// and this module loads with the eager hours module. None is needed: the last alternative takes any run of digits,
// so a match never starts in the middle of a number (a "00" here always begins a number).
const EASTERN_SPLIT = new RegExp(
    '(' +
        [
            '&(?:#[0-9]+|#[xX][0-9a-fA-F]+|[a-zA-Z][a-zA-Z0-9]*);', // character references
            '(?:https?:\\/\\/|www\\.)[^\\t\\n\\f\\r <>"\']+', // URLs
            '[A-Za-z0-9._%+\\-]+@[A-Za-z0-9.\\-]+\\.[A-Za-z]{2,}', // e-mail addresses
            '(?:\\+|00)[0-9][0-9 ().\\-]{5,}[0-9]', // international phone numbers
            '[0-9]{7,}', // account, reference and phone numbers
            '[0-9]+(?:[.,][0-9]+)*(?=[A-Za-z])[A-Za-z0-9.,:\\/+#%&_\\-]*', // digits fused to Latin: 10MB, 2nd
            '[A-Za-z\\u0400-\\u04FF][A-Za-z\\u0400-\\u04FF0-9.,:\\/+#%&_\\-]*(?:[ \\t]+[A-Za-z\\u0400-\\u04FF0-9][A-Za-z\\u0400-\\u04FF0-9.,:\\/+#%&_\\-]*)*', // Latin and Cyrillic runs
        ].join('|') +
        ')|[0-9\\u06F0-\\u06F9]+(?:[.,][0-9\\u06F0-\\u06F9]+)*',
    'gu',
);

export function digitsMode(root = typeof document !== 'undefined' ? document.documentElement : null) {
    return root?.dataset?.digits === EASTERN ? EASTERN : WESTERN;
}

/** The Intl numbering system for a language under the setting: 'arab' only for Arabic in eastern mode. */
export function numberingSystem(locale = 'en', mode = digitsMode()) {
    return mode === EASTERN && /^ar(?:-|$)/i.test(String(locale)) ? 'arab' : 'latn';
}

/** 'ar-u-nu-arab', 'ar-u-nu-latn', 'en-u-nu-latn' …: pass it to Intl.NumberFormat or Intl.DateTimeFormat. */
export function numberLocale(locale = 'en', mode = digitsMode()) {
    const base = String(locale || 'en').split('-u-')[0];
    return `${base}-u-nu-${numberingSystem(base, mode)}`;
}

export function localiseDigits(text, mode = digitsMode()) {
    const source = String(text ?? '');
    if (mode === EASTERN) {
        if (!/[0-9۰-۹]/u.test(source)) return source;
        return source.replace(EASTERN_SPLIT, (match, keep) => keep ?? [...match].map((c) => TO_EASTERN[c] ?? c).join(''));
    }
    if (!/[٠-٩۰-۹٫٬]/u.test(source)) return source;
    return source
        .replace(/[٠-٩۰-۹]/gu, (c) => TO_WESTERN[c])
        .replace(/([0-9])٫(?=[0-9])/gu, '$1.')
        .replace(/([0-9])٬(?=[0-9])/gu, '$1,');
}
