/**
 * The pile under the pointer (Design 2.1 brief §6 "pointer tilt of at most 2 degrees on desktop only"; the owner's
 * "it was animating on hover before", the Phase 1 parallax). Only with a fine pointer (mouse / pen), from 768 px, never
 * under reduced motion (the stage enables it):
 *   depth     each sheet follows the pointer by its depth (pile.js hoverTargets: up to 12 canvas px for the top one,
 *             40 % of that for the bottom one), and the whole pile tilts towards it (tilt.js tiltFor: at most 2°);
 *   breathing on pointer enter the back sheets fan out a little along their own offset and rotation, and close again
 *             on leave;
 *   preview   the sheet of a hovered or focused pager segment, or a back sheet under the pointer, peeks up out of the
 *             pile with part of its lifted shadow.
 * Every value follows its target through the parallax spring {stiffness 120, damping 20}. It writes `transform` on
 * each .hero-sheet (CSSOM, allowed by the CSP; it composes with the pose's translate / rotate / scale that the deal and
 * the switch animate), the top document's callout layer (so its dots stay on their fields), the tilt layer, and the
 * opacity of a previewed sheet's lifted shadow. The loop runs only while something moves.
 */
import { springs } from '../motion/springs.js';
import { hoverTargets } from './pile.js';
import { step } from './spring.js';
import { tiltFor } from './tilt.js';

const KEYS = ['tx', 'ty', 'r', 'air'];
const clamp = (v, lo, hi) => Math.min(hi, Math.max(lo, v));
const still = (s) => Math.abs(s.tx.x) < 0.01 && Math.abs(s.ty.x) < 0.01 && Math.abs(s.r.x) < 0.001;

/**
 * @param {{
 *   area: HTMLElement,                      the element whose pointer position counts (the stage)
 *   tiltEl: HTMLElement|null,
 *   dir: 1|-1,
 *   docs: () => Array<{ type: string, el: HTMLElement, sheet: HTMLElement, callouts: HTMLElement|null, air: HTMLElement|null, depth: number, pose: { x: number, y: number, rot: number } }>,
 * }} options
 */
export function createHover({ area, tiltEl, dir, docs }) {
    const states = new Map();
    const tilt = { rx: { x: 0, v: 0 }, ry: { x: 0, v: 0 } };
    let pointer = null;
    let open = false;
    let peek = null;
    let enabled = false;
    let raf = 0;
    let last = 0;

    const stateOf = (type) => {
        let s = states.get(type);
        if (!s) {
            s = Object.fromEntries(KEYS.map((k) => [k, { x: 0, v: 0 }]));
            states.set(type, s);
        }
        return s;
    };

    function paint(list) {
        for (const d of list) {
            const s = stateOf(d.type);
            const t = still(s) ? '' : `translate(${s.tx.x.toFixed(2)}px, ${s.ty.x.toFixed(2)}px) rotate(${s.r.x.toFixed(3)}deg)`;
            if (d.sheet.style.transform !== t) d.sheet.style.transform = t;
            if (d.callouts) {
                const c = d.depth === 0 && !still(s) ? `translate(${s.tx.x.toFixed(2)}px, ${s.ty.x.toFixed(2)}px)` : '';
                if (d.callouts.style.transform !== c) d.callouts.style.transform = c;
            }
            if (d.air) {
                const a = s.air.x > 0.002 ? String(clamp(s.air.x, 0, 1).toFixed(3)) : '';
                if (d.air.style.opacity !== a) d.air.style.opacity = a;
            }
        }
        if (tiltEl) {
            const { rx, ry } = tilt;
            tiltEl.style.transform = Math.abs(rx.x) < 0.001 && Math.abs(ry.x) < 0.001
                ? ''
                : `perspective(1400px) rotateX(${rx.x.toFixed(3)}deg) rotateY(${ry.x.toFixed(3)}deg)`;
        }
    }

    function frame(now) {
        const dt = Math.min(0.05, Math.max(0, (now - last) / 1000));
        last = now;
        const list = docs();
        const targets = hoverTargets({
            pointer: enabled ? pointer : null,
            open: enabled && open,
            peek: enabled ? peek : null,
            docs: list.map((d) => ({ type: d.type, depth: d.depth, pose: d.pose })),
            dir,
        });
        let moving = false;
        for (const d of list) {
            const s = stateOf(d.type);
            const goal = targets.get(d.type) ?? { tx: 0, ty: 0, r: 0, air: 0 };
            for (const k of KEYS) moving = step(s[k], goal[k], springs.parallax, dt) || moving;
        }
        const g = enabled && pointer ? tiltFor(pointer.x, pointer.y, dir) : { rx: 0, ry: 0 };
        moving = step(tilt.rx, g.rx, springs.parallax, dt) || moving;
        moving = step(tilt.ry, g.ry, springs.parallax, dt) || moving;
        paint(list);
        raf = moving ? requestAnimationFrame(frame) : 0;
    }

    function kick() {
        if (raf) return;
        last = performance.now();
        raf = requestAnimationFrame(frame);
    }

    const fine = (event) => event.pointerType === 'mouse' || event.pointerType === 'pen';

    function onMove(event) {
        if (!enabled || !fine(event)) return;
        const r = area.getBoundingClientRect();
        if (!r.width || !r.height) return;
        pointer = {
            x: clamp((event.clientX - (r.left + r.width / 2)) / (r.width / 2), -1, 1),
            y: clamp((event.clientY - (r.top + r.height / 2)) / (r.height / 2), -1, 1),
        };
        open = true;
        kick();
    }

    function onLeave() {
        pointer = null;
        open = false;
        kick();
    }

    area.addEventListener('pointermove', onMove, { passive: true });
    area.addEventListener('pointerleave', onLeave, { passive: true });

    return {
        enable() {
            enabled = true;
        },
        get enabled() {
            return enabled;
        },
        /** The previewed sheet (a pager segment or a back sheet under the pointer), or null. */
        preview(type) {
            if (peek === type) return;
            peek = type;
            if (enabled) kick();
        },
        /** The pile changed (a switch): follow the new depths. */
        refresh() {
            if (enabled || raf) kick();
        },
        /** Stop following the pointer and ease back to rest (or jump there with `now`). */
        disable({ now = false } = {}) {
            enabled = false;
            pointer = null;
            open = false;
            peek = null;
            if (now) {
                cancelAnimationFrame(raf);
                raf = 0;
                states.clear();
                tilt.rx = { x: 0, v: 0 };
                tilt.ry = { x: 0, v: 0 };
                paint(docs());
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
