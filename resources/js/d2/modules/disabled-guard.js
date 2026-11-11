/**
 * Disabled and loading controls (<x-d2.ui.button disabled|loading>, any element with
 * aria-disabled="true") stay focusable so screen readers still find them, but must never activate.
 * Keyboard activation (Enter / Space) and implicit form submission reach the page as click events too,
 * so one capture-phase click guard covers links, buttons and submit buttons before any other handler.
 */
export default function disabledGuard() {
    document.addEventListener(
        'click',
        (event) => {
            const target = event.target instanceof Element ? event.target.closest('[aria-disabled="true"]') : null;
            if (!target) return;
            event.preventDefault();
            event.stopImmediatePropagation();
        },
        true,
    );
}
