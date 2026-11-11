/**
 * Pointer tilt of the hero pile (Design 2.1 brief §6: "pointer tilt of at most 2 degrees on desktop only"): the whole
 * pile turns towards the pointer around its centre, rotateX / rotateY together never beyond 2°. hero/hover.js follows
 * this target through the parallax spring (fine pointer, from 768 px, motion allowed) and eases back on pointerleave.
 * RTL inverts the x axis.
 */
export const MAX_DEG = 2;

/** The tilt for a pointer at (x, y) in [-1, 1] from the area's centre: { rx, ry } in degrees, |(rx, ry)| ≤ MAX_DEG. */
export function tiltFor(x, y, dir = 1) {
    const len = Math.hypot(x, y);
    const f = len > 1 ? 1 / len : 1;
    return { rx: -MAX_DEG * y * f, ry: MAX_DEG * x * f * dir };
}
