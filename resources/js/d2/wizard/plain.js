/**
 * Consultation wizard: back to the plain form (the markup exactly as it works without JavaScript) when the
 * stepped wizard cannot start: its chunk failed to load, the markup was incomplete, or mounting threw half
 * way. `.is-plain` wins over the html.js stepped layout (components/wizard.css), every step that exists
 * without JavaScript shows again, nothing stays inert, and the one submit button posts the form natively.
 * Eager (tiny): the start-up code in lazy.js needs it when the controller chunk is the thing that failed.
 */
export function plainFallback(root) {
    if (!root) return;
    root.classList.remove('is-stepped');
    root.classList.add('is-plain');
    for (const panel of root.querySelectorAll('[data-step-panel]')) {
        panel.hidden = panel.hasAttribute('data-js-only');
        panel.inert = false;
        panel.classList.remove('is-entering', 'is-forward', 'is-backward');
    }
    const submit = root.querySelector('[data-wizard-submit]');
    if (submit) {
        submit.hidden = false;
        submit.classList.remove('is-loading');
        submit.removeAttribute('aria-disabled');
        submit.removeAttribute('aria-busy');
    }
    root.querySelector('[data-wizard-form]')?.setAttribute('aria-busy', 'false');
}
