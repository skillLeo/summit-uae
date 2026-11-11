/**
 * inertOutside(el): makes everything outside `el` inert (not focusable, not clickable, hidden from
 * assistive technology) by setting `inert` on every sibling of el and of each of its ancestors, up to
 * <body>. Returns restore(), which removes `inert` again from exactly the elements this call changed:
 * an element that was already inert stays inert, and calling restore() twice does nothing.
 *
 * Use it for sheets, drawers and any custom modal surface (a native <dialog> opened with showModal()
 * gets this from the browser). Design 1 review A11Y-02 / NAV-01: aria-modal alone left the page
 * focusable behind the sheet.
 *
 * Live regions are left alone ([aria-live], role=status/alert, the layout's announcer and toast region),
 * so announcements made while the surface is open still reach screen readers. Elements that never take
 * part in the page (script, style, template …) are skipped too.
 */
const SKIP = [
    'script',
    'style',
    'template',
    'link',
    'meta',
    'noscript',
    '[aria-live]',
    '[role="status"]',
    '[role="alert"]',
    '[data-announcer]',
    '[data-toast-region]',
].join(',');

export function inertOutside(el) {
    const changed = [];
    let node = el;
    while (node && node.parentElement && node !== document.body) {
        for (const sibling of node.parentElement.children) {
            if (sibling === node || sibling.inert || sibling.matches(SKIP)) continue;
            sibling.inert = true;
            changed.push(sibling);
        }
        node = node.parentElement;
    }

    let restored = false;
    return function restore() {
        if (restored) return;
        restored = true;
        for (const sibling of changed) sibling.inert = false;
        changed.length = 0;
    };
}
