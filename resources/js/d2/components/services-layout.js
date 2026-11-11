/**
 * Practice-area filter helpers (home #services, Design 2.1 D21). Pure functions, no DOM: unit-tested in
 * components/__tests__/services-layout.test.js. Not an Alpine component (no default export), so the
 * components glob in app.js registers nothing for it. The grid is equal (services.css), so there is no span
 * planning any more: a filtered grid closes up in DOM order by itself.
 */

/**
 * Whether a card belongs to an audience. `audiences` is the card's space-separated data-svc-audiences
 * (hero audience keys from config/audience_map.php); 'all' (or nothing) matches every card.
 */
export function matches(audiences, audience) {
    if (!audience || audience === 'all') return true;
    return String(audiences ?? '').split(/\s+/).includes(audience);
}

/**
 * FLIP offset of a card that moved: the distance from its new box back to its old one, measured at the
 * inline-start edge (left in LTR, right in RTL) so a card that changed width slides from its start edge.
 */
export function offset(before, after, rtl = false) {
    return {
        x: rtl ? before.right - after.right : before.left - after.left,
        y: before.top - after.top,
        resized: Math.abs(before.width - after.width) > 1 || Math.abs(before.height - after.height) > 1,
    };
}
