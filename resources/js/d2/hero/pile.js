/**
 * The hero pile's pure rules (no DOM; Vitest: pile.test.js). Design 2.1 brief D15, §6; the owner's option 3 (the
 * Phase 1 pile with the brief's rules) and autoplay.
 *
 *   order     the pile from the top down (document types). Bringing a document to the top moves it to the front;
 *             the old top lies right under it and the others keep their order (moveToFront).
 *   cycle     the pager's order wraps around: after the last document comes the first (stepOf / nextOf).
 *   pull      the chosen sheet is pulled out of its place towards the inline-end side (the side the brief's switch
 *             comes from), a little up and straighter, lifted towards the viewer (pullPose); the spring then brings it
 *             onto the top of the pile.
 *   depth     the pointer moves each sheet by its depth: the top one most, the bottom one least (depthFactor), up to
 *             PARALLAX_PX; on pointer enter the back sheets fan out a little further along their own offset and
 *             rotation ("breathing open"); a previewed sheet (its pager segment hovered or focused, or the sheet
 *             itself under the pointer) peeks up out of the pile (hoverTargets).
 * Poses are { x, y, rot } in canvas px and degrees, physical (x already mirrored for RTL).
 */

/** The pull-out: share of the sheet's width (clamped, canvas px), the rise (px), the lift scale. */
export const PULL = { share: 0.42, min: 120, max: 190, rise: 18, lift: 1.035, straighten: 0.5 };
/** Pointer depth: the top sheet moves up to this many canvas px; the bottom one 40 % of it. */
export const PARALLAX_PX = 12;
/** "Breathing open": back sheets move this share further along their own offset (px, capped) and rotation. */
export const FAN = { offset: 0.3, maxPx: 6, turn: 0.45, maxDeg: 1.8 };
/** A previewed sheet peeks up by this much (canvas px), out towards its own side, with part of its lifted shadow. */
export const PEEK = { up: 22, out: 6, air: 0.6 };

const clamp = (v, lo, hi) => Math.min(hi, Math.max(lo, v));

/** The pile with `type` on top; the old top right under it, the rest in their order. */
export function moveToFront(order, type) {
    if (!order.includes(type)) return order.slice();
    return [type, ...order.filter((t) => t !== type)];
}

/** The document `step` places after `type` in the pager's list, wrapping around. */
export function nextOf(list, type, step = 1) {
    if (!list.length) return null;
    const i = Math.max(0, list.indexOf(type));
    return list[(((i + step) % list.length) + list.length) % list.length];
}

/** +1 when `to` comes after `from` in the pager's list, -1 before it. */
export function stepOf(list, from, to) {
    return list.indexOf(to) >= list.indexOf(from) ? 1 : -1;
}

/** Where a chosen sheet is pulled out to, from its pose (physical; dir 1 LTR, -1 RTL); width in canvas px. */
export function pullPose(pose, width, dir = 1) {
    const pull = clamp(width * PULL.share, PULL.min, PULL.max);
    return { x: pose.x + dir * pull, y: pose.y - PULL.rise, rot: pose.rot * PULL.straighten, scale: PULL.lift, distance: Math.hypot(pull, PULL.rise) };
}

/** 1 for the top sheet … 0.4 for the bottom one. */
export function depthFactor(depth, count = 6) {
    if (count <= 1) return 1;
    return 1 - (0.6 * clamp(depth, 0, count - 1)) / (count - 1);
}

/**
 * The hover offsets of every sheet: Map(type → { tx, ty, r, air }) (canvas px, degrees, extra air-shadow opacity).
 * pointer: { x, y } in [-1, 1] from the stage centre (physical), or null; open: the pointer is over the pile;
 * peek: the previewed type or null; docs: [{ type, depth, pose: { x, y, rot } }] (pose offsets from the pile's centre,
 * physical). dir mirrors the pointer's x axis in RTL (as the tilt does).
 */
export function hoverTargets({ pointer = null, open = false, peek = null, docs = [], dir = 1 }) {
    const out = new Map();
    const px = pointer ? clamp(pointer.x, -1, 1) * dir : 0;
    const py = pointer ? clamp(pointer.y, -1, 1) : 0;
    for (const d of docs) {
        const f = depthFactor(d.depth, docs.length);
        let tx = PARALLAX_PX * f * px;
        let ty = PARALLAX_PX * f * py;
        let r = 0;
        let air = 0;
        if (d.depth > 0) {
            if (open) {
                tx += clamp(d.pose.x * FAN.offset, -FAN.maxPx, FAN.maxPx);
                ty += clamp(d.pose.y * FAN.offset, -FAN.maxPx, FAN.maxPx);
                r += clamp(d.pose.rot * FAN.turn, -FAN.maxDeg, FAN.maxDeg);
            }
            if (peek === d.type) {
                const side = Math.sign(d.pose.x) || 1;
                tx += side * PEEK.out;
                ty -= PEEK.up;
                r -= d.pose.rot * 0.25;
                air = PEEK.air;
            }
        }
        out.set(d.type, { tx, ty, r, air });
    }
    return out;
}
