/**
 * Matter Navigator service rows (final pass, home-mobile). Below 1280 px with JavaScript each service group
 * of the list (<x-d2.navigator.list>) is a disclosure row: [data-nav-group] carries .is-open and its
 * [data-nav-toggle] button aria-expanded (navigator.css hides the situations of a closed group). Shared by
 * the eager d2Navigator (the rows work before the lazy controller arrives) and navigator/controller.js (the
 * group of a chosen situation opens with it). From 1280 px the class changes nothing on screen.
 */

/** Opens or closes one group; returns true when its state changed. */
export function setGroupOpen(group, open) {
    if (!group) return false;
    const changed = group.classList.contains('is-open') !== open;
    group.classList.toggle('is-open', open);
    group.querySelector('[data-nav-toggle]')?.setAttribute('aria-expanded', open ? 'true' : 'false');
    return changed;
}

/** The next state of a row the visitor pressed (true = open). */
export function nextOpen(group) {
    return !group?.classList.contains('is-open');
}
