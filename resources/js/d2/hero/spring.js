/**
 * Spring physics for the hero, without an animation library (upgrade spec §4.4; docs/d2/engineering.md §5:
 * the hero's JavaScript stays far under its 25 KB gzip budget).
 *
 * - position(): a damped harmonic oscillator released at rest (the same physics as scripts/d2/springs.mjs,
 *   which compiles the --spring-* CSS tokens), so WAAPI moves feel like the CSS springs.
 * - timing(): a WAAPI duration + CSS linear() easing for one move, ending when the spring stays within
 *   `threshold` units (0.5 px by default) of rest for the given travel, so the last frame never jumps.
 *   Browsers without linear() get the ease-out curve with the same duration.
 * - step(): one semi-implicit Euler step for springs that follow a moving target (the idle parallax).
 * Presets: resources/js/d2/motion/springs.js (paper, ui, snappy, parallax).
 */

/** Progress (0 → 1, may overshoot) of a spring released at rest, t seconds after release. */
export function position({ stiffness: k, damping: c, mass: m = 1 }, t) {
    const w0 = Math.sqrt(k / m);
    const zeta = c / (2 * Math.sqrt(k * m));
    if (zeta < 1) {
        const wd = w0 * Math.sqrt(1 - zeta * zeta);
        return 1 - Math.exp(-zeta * w0 * t) * (Math.cos(wd * t) + ((zeta * w0) / wd) * Math.sin(wd * t));
    }
    if (zeta === 1) return 1 - Math.exp(-w0 * t) * (1 + w0 * t);
    const r1 = -w0 * (zeta - Math.sqrt(zeta * zeta - 1));
    const r2 = -w0 * (zeta + Math.sqrt(zeta * zeta - 1));
    const a = r2 / (r2 - r1);
    const b = -r1 / (r2 - r1);
    return 1 - (a * Math.exp(r1 * t) + b * Math.exp(r2 * t));
}

/** Seconds until a move of `distance` units stays within `threshold` units of rest (at most `max`). */
export function settle(spring, distance = 1, threshold = 0.5, max = 3) {
    const tolerance = threshold / Math.max(Math.abs(distance), 1e-6);
    const dt = 1 / 240;
    let last = 0;
    for (let t = 0; t <= max; t += dt) {
        if (Math.abs(position(spring, t) - 1) > tolerance) last = t;
    }
    return Math.min(max, last + dt);
}

/** The largest overshoot past the target, as a fraction of the travel (0 for an overdamped spring). */
export function overshoot(spring, max = 3) {
    let peak = 1;
    for (let t = 0; t <= max; t += 1 / 240) peak = Math.max(peak, position(spring, t));
    return peak - 1;
}

let linearSupport;
export function supportsLinear() {
    if (linearSupport === undefined) {
        linearSupport = typeof CSS !== 'undefined' && typeof CSS.supports === 'function' && CSS.supports('animation-timing-function', 'linear(0, 1)');
    }
    return linearSupport;
}

export const EASE_OUT = 'cubic-bezier(0.22, 1, 0.36, 1)';

const cache = new Map();

/** WAAPI timing for one spring move: { duration (ms), easing }. */
export function timing(spring, distance = 1, threshold = 0.5) {
    const key = `${spring.stiffness}|${spring.damping}|${spring.mass ?? 1}|${Math.round(Math.abs(distance))}|${threshold}`;
    const hit = cache.get(key);
    if (hit) return hit;
    const seconds = settle(spring, distance, threshold);
    const points = Math.min(72, Math.max(16, Math.round(seconds * 60)));
    const values = [];
    for (let i = 0; i <= points; i++) {
        values.push(i === points ? 1 : Number(position(spring, (seconds * i) / points).toFixed(4)));
    }
    const result = {
        duration: Math.round(seconds * 1000),
        easing: supportsLinear() ? `linear(${values.join(', ')})` : EASE_OUT,
    };
    cache.set(key, result);
    return result;
}

/**
 * Advance a spring that follows `target` by dt seconds (sub-stepped for stability).
 * state = { x, v }. Returns true while it is still moving.
 */
export function step(state, target, { stiffness: k, damping: c, mass: m = 1 }, dt) {
    const h = 1 / 240;
    let left = Math.min(dt, 1 / 20);
    while (left > 1e-6) {
        const d = Math.min(h, left);
        const a = (k * (target - state.x) - c * state.v) / m;
        state.v += a * d;
        state.x += state.v * d;
        left -= d;
    }
    const moving = Math.abs(state.v) > 0.002 || Math.abs(target - state.x) > 0.002;
    if (!moving) {
        state.x = target;
        state.v = 0;
    }
    return moving;
}
