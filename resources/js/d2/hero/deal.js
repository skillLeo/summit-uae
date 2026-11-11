/**
 * The hero's entrance, once per page view (Design 2.1 brief §3.6 rule 7 "the hero entrance once", §6): the file's
 * two blank sheets and then the document come in from the inline-end side and settle, the document last, at
 * rotation 0.
 *
 *   order    sheet 1, sheet 2, the document; element i starts at start + i·110 ms (phones i·140);
 *   from     x = dir·62vw (phones 110vw) beyond the final place, y −24 px, rotate dir·8°;
 *   to       the element's own final place (single-keyframe animations end on the underlying CSS value: the sheets'
 *            offsets and rotations, the document's 0), on the paper spring {170, 20, mass 1.1} (spring.js: linear()
 *            easing), ending once the spring stays within 1 px of rest (≈ 800 ms for the 62vw travel at 1440 px);
 *            the document's lifted ("air") shadow fades into its resting one along the way.
 * WAAPI on the individual translate / rotate properties and the shadow layers' opacity (never box-shadow), all
 * created up front with their delays on the document timeline, so a busy main thread cannot hold them back;
 * fill: 'backwards' keeps each element off-stage until its turn, so html.hero-armed can go as soon as they exist.
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
    const stagger = mobile ? 140 : 110;
    const paper = timing(springs.paper, reach, SETTLE_PX);
    const delays = Array.from({ length: count }, (_, i) => start + i * stagger);
    const landed = delays.map((d) => d + paper.duration);
    return { stagger, duration: paper.duration, easing: paper.easing, delays, landed, restAt: count ? landed[count - 1] : start };
}

/**
 * Start the entrance now. Returns { restAt (ms from now), finish() } (finish jumps to the end).
 * @param {{els: HTMLElement[], dir: 1|-1, mobile: boolean, k?: number, start?: number}} options
 *   els in order (the sheets, then the document's paper); k is the canvas scale (so 62vw stays 62 % of the
 *   viewport on a scaled canvas); start is the delay of the first element in ms.
 */
export function deal({ els, dir, mobile, k = 1, start = 350 }) {
    const reach = ((mobile ? 1.1 : 0.62) * window.innerWidth) / (k || 1);
    const plan = schedule({ count: els.length, reach, mobile, start });
    const anims = [];
    let stopped = false;

    els.forEach((el, i) => {
        const options = { duration: plan.duration, easing: plan.easing, delay: plan.delays[i], fill: 'backwards' };
        anims.push(el.animate([{ translate: `${reach * dir}px -24px`, rotate: `${8 * dir}deg` }], options));
        const air = el.querySelector('.doc__shadow--air');
        const rest = el.querySelector('.doc__shadow--rest');
        if (air) anims.push(air.animate({ opacity: [1, 0] }, options));
        if (rest) anims.push(rest.animate({ opacity: [0, 1] }, options));
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
