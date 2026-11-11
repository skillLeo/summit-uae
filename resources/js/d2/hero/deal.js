/**
 * The hero's entrance, once per page view (Design 2.1 brief §3.6 rule 7 "the hero entrance once", §6; the owner's
 * Phase 1 deal): the six sample documents are dealt onto the pile one after another, from the bottom of the pile to
 * the top, and settle in their own places; the document on top lands last, straight, and its callouts then draw in
 * (hero/stage.js).
 *
 *   order    the pile from the bottom up; sheet i starts at start + i·80 ms (phones i·100);
 *   from     x = dir·62vw (phones 110vw) beyond its place, y −24 px, rotate dir·8° more, lifted (scale 1.03, its
 *            "air" shadow on);
 *   to       its own place (a single keyframe at offset 0 ends on the underlying CSS value: the sheet's pose, or the
 *            top of the pile), on the paper spring {170, 20, mass 1.1} (spring.js: linear() easing), ending once the
 *            spring stays within 1 px of rest (≈ 800 ms for the 62vw travel at 1440 px): the whole deal is over in
 *            about 1.2 s. The lifted shadow fades into the resting one along the way.
 * WAAPI on the individual translate / rotate / scale properties and the shadow layers' opacity (never box-shadow), all
 * created up front with their delays on the document timeline, so a busy main thread cannot hold them back;
 * fill: 'backwards' keeps each sheet off-stage until its turn, so html.hero-armed can go as soon as they exist.
 */
import { springs } from '../motion/springs.js';
import { timing } from './spring.js';

/** The move ends when it stays within this many px of rest. */
const SETTLE_PX = 1;

/**
 * The entrance's schedule in ms from its start, without touching the DOM (Vitest: deal.test.js).
 * @param {{count: number, reach: number, mobile: boolean, start?: number}} options
 * @returns {{stagger: number, duration: number, easing: string, delays: number[], landed: number[], restAt: number}}
 */
export function schedule({ count, reach, mobile, start = 350 }) {
    const stagger = mobile ? 100 : 80;
    const paper = timing(springs.paper, reach, SETTLE_PX);
    const delays = Array.from({ length: count }, (_, i) => start + i * stagger);
    const landed = delays.map((d) => d + paper.duration);
    return { stagger, duration: paper.duration, easing: paper.easing, delays, landed, restAt: count ? landed[count - 1] : start };
}

/**
 * Start the entrance now. Returns { restAt (ms from now), finish() } (finish jumps to the end).
 * @param {{sheets: Array<{el: HTMLElement, x: number, y: number, rot: number, air?: HTMLElement|null, rest?: HTMLElement|null, top?: boolean}>, dir: 1|-1, mobile: boolean, k?: number, start?: number}} options
 *   sheets from the bottom of the pile up: the .hero-sheet element and its resting pose (physical canvas px and
 *   degrees, as CSS draws it); k is the canvas scale (so 62vw stays 62 % of the viewport on a scaled canvas); start
 *   is the delay of the first sheet in ms.
 */
export function deal({ sheets, dir, mobile, k = 1, start = 350 }) {
    const reach = ((mobile ? 1.1 : 0.62) * window.innerWidth) / (k || 1);
    const plan = schedule({ count: sheets.length, reach, mobile, start });
    const anims = [];
    let stopped = false;

    sheets.forEach((s, i) => {
        const options = { duration: plan.duration, easing: plan.easing, delay: plan.delays[i], fill: 'backwards' };
        anims.push(s.el.animate([{ translate: `${s.x + reach * dir}px ${s.y - 24}px`, rotate: `${s.rot + 8 * dir}deg`, scale: '1.03', offset: 0 }], options));
        if (s.air) anims.push(s.air.animate([{ opacity: 1, offset: 0 }], options));
        if (s.rest) anims.push(s.rest.animate({ opacity: [0, 1] }, options));
    });

    return {
        restAt: plan.restAt,
        finish() {
            if (stopped) return;
            stopped = true;
            for (const anim of anims) {
                try {
                    anim.finish();
                } catch {
                    anim.cancel();
                }
            }
        },
    };
}
