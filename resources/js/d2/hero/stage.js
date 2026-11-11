/**
 * Hero stage controller (lazy chunk, imported by Alpine d2HeroStage in components/hero.js, so no other page pays for
 * it). Design 2.1 brief D15–D17 and §6 "Hero document switch".
 *
 * mount(root) on <x-d2.hero.visual>:
 *   1. Entrance, once (html.hero-armed from the boot script: home page, motion allowed, opened at its top): after the
 *      hero's fonts (600 ms after the first paint at the latest) the file's two sheets and the document come in on
 *      the paper spring (deal.js), then the document's callouts are drawn (callouts.js). Not armed (reduced motion,
 *      a #section link, the boot script's 2.5 s safety timeout already fired, opened scrolled past the hero): the
 *      final state is already on the page and nothing changes. A phone whose stage is below the fold waits until
 *      it scrolls into view.
 *   2. The switch (pager.js: segments, previous / next, ← / → inside the pager): the old document's callouts fade
 *      out (120 ms) and the next paper slides in from the inline-end side (from the inline-start side when going
 *      back) with a small rotation, both settling to 0 on the spring {stiffness 170, damping 20}; its shadow layers
 *      move with it (the lifted shadow fades into the resting one); the file glides along when the new document sits
 *      elsewhere (the cheque sits towards the start). The old paper fades out beneath it. Once the
 *      paper has settled its callouts draw in (stroke-dashoffset, 400 ms). Transform and opacity only, a fixed
 *      stage: no layout shift. Reduced motion: the switch is instant.
 *   3. The pointer tilt (tilt.js, at most 2°, fine pointer, from 768 px, motion allowed), from the end of the
 *      entrance.
 * Any error leaves the final, static composition (the armed class is always removed).
 */
import { finePointer, reducedMotion } from '../lib/prefs.js';
import { drawIn, fadeOut } from './callouts.js';
import { deal } from './deal.js';
import { pagerControls } from './pager.js';
import { EASE_OUT, timing } from './spring.js';
import { createTilt } from './tilt.js';

/** The switch's spring (brief §6). */
export const SWITCH_SPRING = { stiffness: 170, damping: 20, mass: 1 };
/** How far (canvas px) the next paper comes from, and its starting rotation (degrees). */
export const SWITCH_TRAVEL = 220;
export const SWITCH_TURN = 4;
const FADE_OUT_MS = 120;
const PAPER_IN_MS = 140;
const PAPER_OUT_MS = 220;
const AIR_MOVING = 0.7;

const wait = (ms) => new Promise((resolve) => window.setTimeout(resolve, ms));

/**
 * Where the next paper starts (pure; Vitest: stage.test.js): from the inline-end side for a later document (step 1),
 * from the inline-start side for an earlier one (step -1); dir mirrors the inline axis in RTL.
 */
export function slideFrom(step, dir = 1, travel = SWITCH_TRAVEL, turn = SWITCH_TURN) {
    const s = step < 0 ? -1 : 1;
    return { translate: `${s * dir * travel}px -6px`, rotate: `${s * dir * turn}deg` };
}

/** The hero's faces (the H1 and the lead as computed, and the sample documents' faces the boot script started). */
function heroFonts(hero) {
    if (!document.fonts?.load) return Promise.resolve();
    const loads = [];
    for (const el of [hero?.querySelector('#hero-title'), hero?.querySelector('.hero__text p')]) {
        if (!el) continue;
        const cs = getComputedStyle(el);
        loads.push(document.fonts.load(`${cs.fontStyle} ${cs.fontWeight} ${cs.fontSize} ${cs.fontFamily}`, el.textContent.slice(0, 240)));
    }
    try {
        const docs = document.querySelector('style[data-d2-fonts="docs"][data-d2-load]');
        for (const [font, text] of JSON.parse(docs?.getAttribute('data-d2-load') || '[]')) loads.push(document.fonts.load(font, text));
    } catch {
        /* no stage faces listed */
    }
    return Promise.all(loads).catch(() => {});
}

/** First paint (ms on the performance timeline): the entrance's time origin. */
function firstPaint() {
    try {
        const paints = performance.getEntriesByType('paint');
        const entry = paints.find((e) => e.name === 'first-contentful-paint') || paints.find((e) => e.name === 'first-paint');
        if (entry) return entry.startTime;
    } catch {
        /* no paint timing */
    }
    return null;
}

export function mount(root) {
    const html = document.documentElement;
    // Claim the hero: the boot script's safety timeout then leaves it alone.
    html.classList.add('hero-live');
    const stage = root.querySelector('[data-hero-stage]');
    const canvas = stage?.querySelector('[data-canvas]');
    if (!stage || !canvas) {
        html.classList.remove('hero-armed');
        return () => {};
    }
    const dir = getComputedStyle(root).direction === 'rtl' ? -1 : 1;
    const hero = root.closest('[data-hero]');
    const area = hero || root;
    const phone = window.matchMedia('(width < 768px)');
    const motionPref = window.matchMedia('(prefers-reduced-motion: reduce)');
    const forcedPhone = root.hasAttribute('data-phone');
    const isPhone = () => forcedPhone || phone.matches;
    const docs = new Map(Array.from(stage.querySelectorAll('[data-hero-doc]'), (el) => [el.dataset.heroDoc, el]));
    const sheets = Array.from(stage.querySelectorAll('[data-sheet]'));
    const notes = Array.from(root.querySelectorAll('[data-notes]'));
    const tiltEl = stage.querySelector('[data-tilt]');
    const fileEl = stage.querySelector('.hero-file');
    const paperOf = (el) => el?.querySelector('[data-doc-paper]') ?? null;
    let active = [...docs.keys()].find((t) => docs.get(t).classList.contains('is-active')) || stage.dataset.active || [...docs.keys()][0];

    let entrance = null;     // the running entrance (deal.js)
    let drawing = null;      // the running callout draw-in
    let moves = [];          // the running switch's animations
    let settle = null;       // ends the running switch at once (classes in their final state)
    let generation = 0;
    let timers = [];
    let watcher = null;

    /* ------------------------------------------------------------------ tilt */
    const tilt = tiltEl ? createTilt({ area, el: tiltEl, dir }) : null;
    const tiltAllowed = () => !isPhone() && finePointer() && !reducedMotion();
    const enableTilt = () => {
        if (tilt && tiltAllowed()) tilt.enable();
    };

    /* ------------------------------------------------------------------ finishing */
    function finishAll() {
        timers.forEach((t) => window.clearTimeout(t));
        timers = [];
        if (entrance) {
            entrance.finish();
            entrance = null;
        }
        if (settle) settle();
        for (const anim of moves) anim.cancel();
        moves = [];
        if (drawing) {
            drawing.finish();
            drawing = null;
        }
        for (const el of docs.values()) el.classList.remove('is-pending');
        html.classList.remove('hero-armed');
    }

    function showNotes(type) {
        for (const list of notes) list.hidden = list.dataset.notes !== type;
    }

    function draw(docEl, delay = 0) {
        docEl.classList.remove('is-pending');
        if (reducedMotion()) return;
        drawing = drawIn(docEl, { dir, delay });
        const mine = drawing;
        mine.finished.then(() => {
            if (drawing === mine) drawing = null;
        });
    }

    /* ------------------------------------------------------------------ the switch */
    function select(type, step = 1) {
        if (!docs.has(type) || type === active) {
            pager?.update(active);
            return;
        }
        finishAll();
        const gen = ++generation;
        const oldEl = docs.get(active);
        const newEl = docs.get(type);
        // The file follows the document shown (HeroStage dx): measured before and after, so it can glide.
        const fileFrom = fileEl?.offsetLeft ?? 0;
        active = type;
        stage.dataset.active = type;
        const fileShift = fileEl ? fileFrom - fileEl.offsetLeft : 0;
        pager?.update(type);
        showNotes(type);

        if (reducedMotion()) {
            oldEl.classList.remove('is-active');
            newEl.classList.add('is-active');
            return;
        }

        // The old callouts go first; the old paper fades out beneath the new one.
        const out = fadeOut(oldEl, FADE_OUT_MS);
        const oldPaper = paperOf(oldEl);
        const sink = oldPaper?.animate({ opacity: [1, 0] }, { duration: PAPER_OUT_MS, delay: 60, easing: EASE_OUT, fill: 'forwards' });
        oldEl.classList.remove('is-active');
        oldEl.classList.add('is-leaving');
        newEl.classList.add('is-active', 'is-pending');

        const paper = paperOf(newEl);
        const spring = timing(SWITCH_SPRING, SWITCH_TRAVEL, 0.5);
        const slide = paper?.animate([slideFrom(step, dir), { translate: '0px 0px', rotate: '0deg' }], { duration: spring.duration, easing: spring.easing });
        const show = paper?.animate({ opacity: [0, 1] }, { duration: PAPER_IN_MS, easing: 'linear' });
        const air = newEl.querySelector('.doc__shadow--air');
        const lift = air?.animate({ opacity: [AIR_MOVING, 0] }, { duration: spring.duration, easing: spring.easing });
        const glide = Math.abs(fileShift) > 0.5
            ? fileEl.animate([{ translate: `${fileShift}px 0` }, { translate: '0px 0px' }], { duration: spring.duration, easing: spring.easing })
            : null;
        moves = [out, sink, slide, show, lift, glide].filter(Boolean);

        settle = () => {
            settle = null;
            oldEl.classList.remove('is-leaving');
            out?.cancel();
            sink?.cancel();
        };
        const done = slide ? slide.finished : Promise.resolve();
        done.then(() => {
            if (gen !== generation) return;
            moves = [];
            if (settle) settle();
            // The leaders draw in once the paper has settled.
            draw(newEl);
        }).catch(() => {});
    }

    /* ------------------------------------------------------------------ wiring */
    const pagerEl = root.querySelector('[data-hero-pager]');
    const pager = pagerEl ? pagerControls(pagerEl, { dir, onSelect: select }) : null;

    function onPhone() {
        finishAll();
        if (tiltAllowed()) enableTilt();
        else tilt?.disable({ now: true });
    }

    function onMotionPref() {
        if (reducedMotion()) {
            finishAll();
            tilt?.disable({ now: true });
        } else {
            enableTilt();
        }
    }

    function onPageShow(event) {
        if (event.persisted) finishAll();
    }

    /** Printing mid-entrance (or before a phone's stage was scrolled to) prints the final composition. */
    function onPrint() {
        watcher?.disconnect();
        watcher = null;
        finishAll();
    }

    phone.addEventListener('change', onPhone);
    motionPref.addEventListener('change', onMotionPref);
    window.addEventListener('pageshow', onPageShow);
    window.addEventListener('beforeprint', onPrint);

    /* ------------------------------------------------------------------ first run */
    pager?.update(active);
    // The mobile tab bar covers the bottom of the viewport.
    const bottomInset = () => (isPhone() ? 64 : 0);

    /** Share of the stage's height inside the viewport (0 … 1). */
    function visibleShare() {
        const r = stage.getBoundingClientRect();
        if (!r.height || !r.width) return 0;
        const seen = Math.min(r.bottom, window.innerHeight - bottomInset()) - Math.max(r.top, 0);
        return Math.max(0, seen) / r.height;
    }

    function finalNow() {
        html.classList.remove('hero-armed');
        enableTilt();
    }

    /** fromPaint: on the storyboard's clock (the first sheet 350 ms after the first paint); else now. */
    function startEntrance(fromPaint) {
        const docEl = docs.get(active);
        const t0 = firstPaint() ?? performance.now();
        const now = performance.now();
        const k = canvas.getBoundingClientRect().width / (canvas.offsetWidth || 1);
        const start = fromPaint ? Math.max(0, t0 + 350 - now) : 0;
        docEl.classList.add('is-pending');
        entrance = deal({ els: [...sheets, paperOf(docEl)].filter(Boolean), dir, mobile: isPhone(), k, start });
        html.classList.remove('hero-armed');
        // The callouts once the document rests (and not before 1.5 s after the first paint).
        const at = fromPaint ? Math.max(entrance.restAt, t0 + 1500 - now) : entrance.restAt;
        draw(docEl, at);
        timers.push(window.setTimeout(() => {
            entrance = null;
            enableTilt();
        }, at + 900));
    }

    async function run() {
        if (!html.classList.contains('hero-armed') || reducedMotion() || document.visibilityState !== 'visible') {
            finalNow();
            return;
        }
        // The hero's fonts first (no text reflows while the paper moves), but never past 600 ms after the first paint.
        const paint = firstPaint() ?? performance.now();
        await Promise.race([heroFonts(hero), wait(Math.max(0, paint + 600 - performance.now()))]);
        // The boot script's safety timeout may have shown the final state meanwhile: never play the entrance then.
        if (!html.classList.contains('hero-armed')) {
            finalNow();
            return;
        }
        if (visibleShare() >= 0.3) {
            startEntrance(true);
            return;
        }
        if (stage.getBoundingClientRect().top < 0 || typeof IntersectionObserver !== 'function') {
            // Opened scrolled past the hero (restored scroll position): nothing to play.
            finalNow();
            return;
        }
        // Below the fold (short phones): keep the papers off-stage and play once the stage scrolls into view. This
        // script now owns the armed state, with its own fallback.
        window.clearTimeout(window.__d2HeroArm);
        const fallback = window.setTimeout(() => {
            watcher?.disconnect();
            watcher = null;
            finalNow();
        }, 8000);
        watcher = new IntersectionObserver((entries) => {
            if (!entries.some((e) => e.isIntersecting)) return;
            watcher.disconnect();
            watcher = null;
            window.clearTimeout(fallback);
            if (html.classList.contains('hero-armed') && !reducedMotion()) startEntrance(false);
            else finalNow();
        }, { threshold: 0.2, rootMargin: `0px 0px -${bottomInset()}px 0px` });
        watcher.observe(stage);
        timers.push(fallback);
    }

    run().catch((error) => {
        finishAll();
        console.error(error);
    });

    return () => {
        watcher?.disconnect();
        finishAll();
        tilt?.destroy();
        pager?.destroy();
        phone.removeEventListener('change', onPhone);
        motionPref.removeEventListener('change', onMotionPref);
        window.removeEventListener('pageshow', onPageShow);
        window.removeEventListener('beforeprint', onPrint);
    };
}
