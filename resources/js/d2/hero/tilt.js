/**
 * Pointer tilt of the hero stage (Design 2.1 brief §6: "pointer tilt of at most 2 degrees on desktop only"). Only
 * with a fine pointer (mouse / pen), from 768 px, never under reduced motion: the whole composition (file, document,
 * callouts) turns towards the pointer around its centre, rotateX / rotateY together never beyond 2°, through a spring
 * {stiffness 120, damping 20} per axis, and returns to rest on pointerleave. RTL inverts the x axis. It writes the
 * tilt layer's `transform` (CSSOM, allowed by the CSP); the loop runs only while something moves.
 */
import { springs } from '../motion/springs.js';
import { step } from './spring.js';

export const MAX_DEG = 2;

/** The tilt for a pointer at (x, y) in [-1, 1] from the area's centre: { rx, ry } in degrees, |(rx, ry)| ≤ MAX_DEG. */
export function tiltFor(x, y, dir = 1) {
    const len = Math.hypot(x, y);
    const f = len > 1 ? 1 / len : 1;
    return { rx: -MAX_DEG * y * f, ry: MAX_DEG * x * f * dir };
}

/** @param {{area: HTMLElement, el: HTMLElement, dir: 1|-1}} options */
export function createTilt({ area, el, dir }) {
    const state = { rx: { x: 0, v: 0 }, ry: { x: 0, v: 0 } };
    let goal = { rx: 0, ry: 0 };
    let enabled = false;
    let raf = 0;
    let last = 0;

    function paint() {
        const { rx, ry } = state;
        el.style.transform = Math.abs(rx.x) < 0.001 && Math.abs(ry.x) < 0.001
            ? ''
            : `perspective(1400px) rotateX(${rx.x.toFixed(3)}deg) rotateY(${ry.x.toFixed(3)}deg)`;
    }

    function frame(now) {
        const dt = Math.min(0.05, Math.max(0, (now - last) / 1000));
        last = now;
        let moving = step(state.rx, goal.rx, springs.parallax, dt);
        moving = step(state.ry, goal.ry, springs.parallax, dt) || moving;
        paint();
        raf = moving ? requestAnimationFrame(frame) : 0;
    }

    function kick() {
        if (raf) return;
        last = performance.now();
        raf = requestAnimationFrame(frame);
    }

    function onMove(event) {
        if (!enabled || (event.pointerType !== 'mouse' && event.pointerType !== 'pen')) return;
        const r = area.getBoundingClientRect();
        if (!r.width || !r.height) return;
        goal = tiltFor((event.clientX - (r.left + r.width / 2)) / (r.width / 2), (event.clientY - (r.top + r.height / 2)) / (r.height / 2), dir);
        kick();
    }

    function onLeave() {
        goal = { rx: 0, ry: 0 };
        kick();
    }

    area.addEventListener('pointermove', onMove, { passive: true });
    area.addEventListener('pointerleave', onLeave, { passive: true });

    return {
        enable() {
            enabled = true;
        },
        /** Stop following the pointer and ease back to rest (or jump there with `now`). */
        disable({ now = false } = {}) {
            enabled = false;
            goal = { rx: 0, ry: 0 };
            if (now) {
                cancelAnimationFrame(raf);
                raf = 0;
                state.rx = { x: 0, v: 0 };
                state.ry = { x: 0, v: 0 };
                paint();
            } else {
                kick();
            }
        },
        destroy() {
            this.disable({ now: true });
            area.removeEventListener('pointermove', onMove);
            area.removeEventListener('pointerleave', onLeave);
        },
    };
}
