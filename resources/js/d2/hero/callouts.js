/**
 * The callouts of one hero document (Design 2.1 brief D16, §6): they are printed by the server in their final place
 * (<x-d2.hero.stage>, App\Design\D2\HeroStage), so this module never lays anything out: it only draws them in once
 * the paper has settled, and fades them out when the document leaves.
 *
 *   dot     scales in on the snappy spring;
 *   leader  drawn with stroke-dashoffset 1 → 0 (the path has pathLength 1) in 400 ms ease-out, 60 ms after its dot;
 *   label   (or the phone's numbered marker) fades in from 4 px towards the document as the leader completes;
 *   stagger 90 ms between the three callouts.
 * Both layouts (wide: labels, phone: markers) are in the markup; animating the one CSS hides costs nothing.
 * fill: 'backwards' holds every part hidden until its turn; when the animations end the printed state shows.
 */
import { springs } from '../motion/springs.js';
import { EASE_OUT, timing } from './spring.js';

export const STAGGER = 90;
export const LINE_MS = 400;
export const LABEL_MS = 200;
const LINE_AFTER_DOT = 60;

/** The callouts of one document, grouped by id: { id, side, dots, lines, label }. */
export function partsOf(docEl) {
    const layer = docEl?.querySelector('[data-hero-callouts]');
    if (!layer) return [];
    return Array.from(layer.querySelectorAll('.hero-callout'), (label) => {
        const id = label.dataset.callout;
        return {
            id,
            side: label.classList.contains('hero-callout--start') ? 'start' : 'end',
            dots: Array.from(layer.querySelectorAll(`.hero-dot[data-callout="${id}"]`)),
            lines: Array.from(layer.querySelectorAll(`.hero-line[data-callout="${id}"]`)),
            label,
        };
    });
}

/**
 * Draw the callouts of docEl in after `delay` ms. Returns { finished, finish() }.
 * @param {HTMLElement} docEl  @param {{dir: 1|-1, delay?: number}} options
 */
export function drawIn(docEl, { dir = 1, delay = 0 } = {}) {
    const anims = [];
    const snappy = timing(springs.snappy, 16, 0.25);
    partsOf(docEl).forEach((c, i) => {
        const t = delay + i * STAGGER;
        // The label comes from the document's side: towards the inline end for a start-side label.
        const from = (c.side === 'start' ? 4 : -4) * dir;
        for (const dot of c.dots) anims.push(dot.animate({ transform: ['scale(0)', 'scale(1)'] }, { ...snappy, delay: t, fill: 'backwards' }));
        for (const line of c.lines) anims.push(line.animate({ strokeDashoffset: [1, 0] }, { duration: LINE_MS, easing: EASE_OUT, delay: t + LINE_AFTER_DOT, fill: 'backwards' }));
        // transform, not translate: the phone's marker is centred on its leader with translate.
        anims.push(c.label.animate({ opacity: [0, 1], transform: [`translateX(${from}px)`, 'none'] }, {
            duration: LABEL_MS,
            easing: EASE_OUT,
            delay: t + LINE_AFTER_DOT + LINE_MS * 0.6,
            fill: 'backwards',
        }));
    });
    return {
        finished: Promise.all(anims.map((a) => a.finished)).catch(() => {}),
        finish() {
            for (const anim of anims) {
                try {
                    anim.finish();
                } catch {
                    anim.cancel();
                }
            }
        },
        cancel() {
            for (const anim of anims) anim.cancel();
        },
    };
}

/** Fade the callout layer of docEl out (held at 0 until cancel()). */
export function fadeOut(docEl, duration = 120) {
    const layer = docEl?.querySelector('[data-hero-callouts]');
    if (!layer) return null;
    return layer.animate({ opacity: [1, 0] }, { duration, easing: EASE_OUT, fill: 'forwards' });
}
