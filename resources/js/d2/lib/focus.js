/**
 * Focus helpers for sheets, dialogs, popovers and menus (Design 1 review A11Y-02, A11Y-03, NAV-01,
 * NAV-02: focus never moved in, was not trapped and dropped to <body> on close).
 *
 *   focusables(root)   the elements Tab can reach inside root, in DOM order: rendered, enabled, not
 *                      inert, tabindex ≥ 0; a radio group counts once (its checked radio, else its first)
 *   focusFirst(root)   focuses [autofocus] / [data-autofocus], else the first focusable, else root itself
 *                      (given tabindex="-1" when it has none); returns the element that has focus
 *   trapFocus(root)    Tab and Shift+Tab wrap around inside root, and focus that lands outside it is pulled
 *                      back in. Traps nest: only the most recent one is active. Returns release()
 *   restoreFocus(el)   focuses el again if it is still in the document, rendered and not inert; true when
 *                      el has focus afterwards
 *
 * Focus moves never scroll the page (preventScroll): a sheet or dialog is already in view.
 */

const CANDIDATES = [
    'a[href]',
    'area[href]',
    'button',
    'input:not([type="hidden"])',
    'select',
    'textarea',
    'iframe',
    'audio[controls]',
    'video[controls]',
    'summary',
    '[contenteditable]:not([contenteditable="false"])',
    '[tabindex]',
].join(',');

function rendered(el) {
    if (typeof el.checkVisibility === 'function') {
        // Covers display:none, closed <details>, content-visibility:hidden and visibility:hidden.
        return el.checkVisibility({ visibilityProperty: true, checkVisibilityCSS: true });
    }
    return el.getClientRects().length > 0;
}

/** Can receive focus at all (by script): rendered, enabled, not inert. */
function usable(el) {
    return !!el && !el.closest('[inert]') && !el.matches(':disabled') && rendered(el);
}

/** Radio buttons of one group share a single tab stop: the checked one, or the first one. */
function radioStop(radio, list) {
    if (!radio.name) return true;
    const group = list.filter(
        (el) => el instanceof HTMLInputElement && el.type === 'radio' && el.name === radio.name && el.form === radio.form,
    );
    const checked = group.find((r) => r.checked);
    return radio === (checked || group[0]);
}

export function focusables(root) {
    if (!root) return [];
    const list = Array.from(root.querySelectorAll(CANDIDATES)).filter((el) => el.tabIndex >= 0 && usable(el));
    return list.filter((el) => !(el instanceof HTMLInputElement && el.type === 'radio') || radioStop(el, list));
}

function focusEl(el) {
    try {
        el.focus({ preventScroll: true });
    } catch {
        el.focus();
    }
}

export function focusFirst(root) {
    if (!root) return null;
    const preferred = root.querySelector('[autofocus], [data-autofocus]');
    let target = usable(preferred) && (preferred.tabIndex >= 0 || preferred.hasAttribute('tabindex')) ? preferred : null;
    target ||= focusables(root)[0] || root;
    if (target === root && !root.hasAttribute('tabindex')) root.setAttribute('tabindex', '-1');
    focusEl(target);
    return target;
}

// --------------------------------------------------------------------------------- trap

const traps = [];
let listening = false;

const topTrap = () => traps[traps.length - 1] || null;

function onKeydown(e) {
    const root = topTrap();
    if (!root || e.key !== 'Tab' || e.altKey || e.ctrlKey || e.metaKey || e.defaultPrevented) return;
    const items = focusables(root);
    if (items.length === 0) {
        e.preventDefault();
        focusFirst(root);
        return;
    }
    const active = document.activeElement;
    const first = items[0];
    const last = items[items.length - 1];
    if (!root.contains(active)) {
        e.preventDefault();
        focusEl(e.shiftKey ? last : first);
        return;
    }
    // Wrap when nothing focusable lies beyond the active element in the Tab direction. This also covers a
    // focused heading or panel (tabindex -1) that is not in the list itself.
    const FOLLOWING = Node.DOCUMENT_POSITION_FOLLOWING;
    if (e.shiftKey) {
        const before = items.some((el) => el !== active && el.compareDocumentPosition(active) & FOLLOWING);
        if (!before) {
            e.preventDefault();
            focusEl(last);
        }
    } else {
        const after = items.some((el) => el !== active && active.compareDocumentPosition(el) & FOLLOWING);
        if (!after) {
            e.preventDefault();
            focusEl(first);
        }
    }
}

function onFocusin(e) {
    const root = topTrap();
    const target = e.target;
    if (!root || !(target instanceof Element) || root.contains(target)) return;
    // A modal <dialog> opened above the trapped element owns focus while it is open.
    if (target.closest('dialog[open]')) return;
    // Something outside took focus (script, assistive technology): bring it back.
    focusFirst(root);
}

export function trapFocus(root) {
    if (!root) return () => {};
    traps.push(root);
    if (!listening) {
        document.addEventListener('keydown', onKeydown, true);
        document.addEventListener('focusin', onFocusin, true);
        listening = true;
    }
    let released = false;
    return function release() {
        if (released) return;
        released = true;
        const index = traps.lastIndexOf(root);
        if (index !== -1) traps.splice(index, 1);
        if (traps.length === 0 && listening) {
            document.removeEventListener('keydown', onKeydown, true);
            document.removeEventListener('focusin', onFocusin, true);
            listening = false;
        }
    };
}

export function restoreFocus(el) {
    if (!el || !el.isConnected || typeof el.focus !== 'function' || !usable(el)) return false;
    focusEl(el);
    return document.activeElement === el;
}
