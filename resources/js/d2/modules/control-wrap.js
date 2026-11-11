/**
 * Wrapping placeholders (<x-d2.ui.input wrap-placeholder>; components/field.css .control--wrap; D09). While the
 * field is empty its placeholder is drawn inside it as text that wraps, so the field can be taller than the input's
 * own line. A press anywhere in the field puts the caret in the input, as a press on a one-line field does:
 * mousedown keeps the focus where it is (nothing blurs to the page), the click moves it to the input (a click, so a
 * phone opens its keyboard). Presses on the input itself, on a disabled field and with another button are left alone.
 * Without JavaScript the field's label and the input's own line focus it. One delegated pair of listeners serves
 * every wrapping field on the page.
 */

/** The input a press on `target` should focus: inside a wrapping field, but not on the input itself. */
export function wrapTarget(target) {
    const box = target && typeof target.closest === 'function' ? target.closest('[data-control-wrap]') : null;
    if (!box) return null;
    const input = box.querySelector('input');
    if (!input || input === target || input.disabled) return null;
    return input;
}

export default function controlWrap() {
    document.addEventListener('mousedown', (event) => {
        if (event.button === 0 && wrapTarget(event.target)) event.preventDefault();
    });
    document.addEventListener('click', (event) => {
        if (event.button !== 0) return;
        const input = wrapTarget(event.target);
        if (input && document.activeElement !== input) input.focus();
    });
}
