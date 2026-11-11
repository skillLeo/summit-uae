/**
 * d2Date: the Design 2.1 date field (<x-d2.ui.date>, resources/views/components/d2/ui/date.blade.php; D36).
 *
 * The server renders the platform's <input type="date"> (the no-JS path). This turns it into a text field that
 * takes a date typed in the page language's order and shows it in the language's short form (date/parse.js),
 * and wires the calendar button to the calendar (date/calendar.js, a lazy chunk loaded on the first open).
 *
 * The native contract stays, so the calculators, the consultation wizard and the server need no change:
 * - the element with the field id reports `.value` as ISO (YYYY-MM-DD; '' while empty or not a date) and setting
 *   `.value` to an ISO date (or '') shows it; `.validity.badInput` is true while the text is not a date;
 * - its `name` moves to a hidden input beside it that always holds the ISO value (a form posts ISO, as before);
 * - typing fires the input's own `input` events; leaving the field (or picking a day) fires `change`, after the
 *   text is rewritten in the short form; listeners on ancestors see the ISO value at once.
 * Nothing here holds copy: the placeholder and every label of the calendar come from the server.
 */
import { formatDate, latinDigits, parseDate, separatorOf } from '../date/parse.js';

const NATIVE_VALUE = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
const NATIVE_VALIDITY = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'validity');

/** The language of a field (a styleguide specimen may differ from the page). */
const langOf = (el) => el.closest('[lang]')?.getAttribute('lang') || document.documentElement.lang || 'en';

/**
 * Enhance one date field (idempotent). Returns its small API ({ input, button, lang, iso, set, open }) or null.
 * Exported for the calendar chunk and the styleguide.
 */
export function enhance(root) {
    if (root.__date) return root.__date;
    const input = root.querySelector('[data-date-input]');
    const button = root.querySelector('[data-date-open]');
    if (!input) return null;

    const lang = langOf(root);
    const raw = () => NATIVE_VALUE.get.call(input);
    const setRaw = (text) => NATIVE_VALUE.set.call(input, text);

    // The value the server (or a restored draft) put in the native field, read before the type changes.
    const initial = parseDate(raw()) ?? '';

    // The name moves to a hidden input that always carries ISO (form posts, FormData).
    let hidden = null;
    const name = input.getAttribute('name');
    if (name) {
        hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = name;
        if (input.hasAttribute('form')) hidden.setAttribute('form', input.getAttribute('form'));
        hidden.disabled = input.disabled;
        input.removeAttribute('name');
        input.after(hidden);
    }

    input.type = 'text';
    input.setAttribute('inputmode', 'numeric');
    input.setAttribute('autocomplete', 'off');
    input.setAttribute('spellcheck', 'false');
    if (!input.getAttribute('placeholder') && root.dataset.datePlaceholder) input.setAttribute('placeholder', root.dataset.datePlaceholder);

    const iso = () => parseDate(raw()) ?? '';
    const bad = () => raw().trim() !== '' && parseDate(raw()) === null;
    const sync = () => {
        if (hidden) {
            hidden.value = iso();
            hidden.disabled = input.disabled;
        }
    };
    /** Show an ISO date (or clear the field); anything else is kept as typed text. */
    const set = (value) => {
        const text = value === null || value === undefined ? '' : String(value);
        const parsed = parseDate(text);
        setRaw(parsed ? formatDate(parsed, lang) : text);
        sync();
    };

    Object.defineProperty(input, 'value', {
        configurable: true,
        get: iso,
        set,
    });
    Object.defineProperty(input, 'validity', {
        configurable: true,
        get() {
            const native = NATIVE_VALIDITY.get.call(input);
            const badInput = bad();
            return {
                badInput,
                customError: native.customError,
                patternMismatch: false,
                rangeOverflow: false,
                rangeUnderflow: false,
                stepMismatch: false,
                tooLong: false,
                tooShort: false,
                typeMismatch: false,
                valueMissing: native.valueMissing,
                valid: !badInput && native.valid,
            };
        },
    });

    set(initial);

    // Typing: digits typed at the end get the language's separator after the day and the month ("01" → "01/",
    // "01/03" → "01/03/"), so the digits-only keypad of phones (inputmode numeric) types a whole date; a separator
    // the visitor types is kept. The hidden ISO follows every keystroke (this listener runs before any ancestor's).
    input.addEventListener('input', (event) => {
        const text = raw();
        const atEnd = input.selectionStart === text.length && input.selectionEnd === text.length;
        if (event.inputType === 'insertText' && atEnd && /^\d+$/.test(latinDigits(event.data ?? ''))) {
            const sep = separatorOf(lang);
            const digits = latinDigits(text);
            const dayMonth = digits.length === 5 && /^\d{2}$/.test(digits.slice(0, 2)) && digits[2] === sep && /^\d{2}$/.test(digits.slice(3));
            if (/^\d{2}$/.test(digits) || dayMonth) setRaw(`${text}${sep}`);
        } else if (event.inputType === 'insertText' && atEnd && /^[\s./\-]$/.test(event.data ?? '') && /[\s./\-]$/.test(text.slice(0, -1))) {
            // A separator typed right after one we added: keep one ("01/" then "/" stays "01/").
            setRaw(text.slice(0, -1));
        }
        sync();
    });
    // Leaving the field: a complete date is rewritten in the short form ("1/3/18" → "01/03/2018").
    input.addEventListener('change', () => {
        const parsed = parseDate(raw());
        if (parsed && raw() !== formatDate(parsed, lang)) setRaw(formatDate(parsed, lang));
        sync();
    });

    let calendar = null;
    const open = async () => {
        if (input.disabled || input.readOnly || !button) return;
        try {
            calendar ??= await import('../date/calendar.js');
            calendar.open(api);
        } catch (error) {
            // Typing still works; only the calendar is missing.
            console.error(error);
        }
    };

    if (button) {
        button.disabled = input.disabled;
        button.addEventListener('click', open);
    }
    input.addEventListener('keydown', (event) => {
        if (event.altKey && event.key === 'ArrowDown') {
            event.preventDefault();
            open();
        }
    });

    const api = {
        root,
        input,
        button,
        lang,
        iso,
        /** Pick a date: show it, then fire input + change like typing and leaving the field. */
        pick(value) {
            set(value);
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        },
        open,
    };
    root.__date = api;
    root.classList.add('is-ready');
    return api;
}

export default (Alpine) => {
    Alpine.data('d2Date', () => ({
        init() {
            enhance(this.$root);
        },
    }));
};
