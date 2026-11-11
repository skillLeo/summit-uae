/**
 * Hero stage controller (lazy chunk, imported by Alpine d2HeroStage in components/hero.js, so no other page pays for
 * it). Design 2.1 brief D15–D17 and §6 "Hero document switch"; the owner's option 3 (the Phase 1 pile with the
 * brief's rules) and autoplay.
 *
 * mount(root) on <x-d2.hero.visual>:
 *   1. Entrance, once (html.hero-armed from the boot script: home page, motion allowed, opened at its top): after the
 *      hero's fonts (600 ms after the first paint at the latest) the six sheets are dealt onto the pile from the
 *      bottom up (deal.js, about 1.2 s), the document on top lands last and its callouts are drawn (callouts.js).
 *      Not armed (reduced motion, a #section link, the boot script's 2.5 s safety timeout already fired, opened
 *      scrolled past the hero): the composed pile is already on the page and nothing changes. A phone whose stage is
 *      below the fold waits until it scrolls into view.
 *   2. The switch (the pager: segments, previous / next, ← / →; a click on a back sheet; the autoplay):
 *        - the old top's callouts fade out (120 ms) and its ink fades out (160 ms) as it goes back to its own place
 *          in the pile on the spring {stiffness 170, damping 20} (its lifted shadow settles);
 *        - the chosen sheet lifts out of its place (240 ms: pulled towards the inline-end side, a little up and
 *          straighter, scale 1.035, its shadow grows), then (now the top of the pile) travels onto the top on the same
 *          spring, rotates to 0 and settles, its ink fading in as it arrives; the other sheets take their new depths;
 *        - once the paper has settled its callouts draw in (stroke-dashoffset, 400 ms), then the labels.
 *      Transform and opacity only, a fixed stage: no layout shift. Reduced motion: the switch is instant.
 *   3. The pointer (hover.js: fine pointer, from 768 px, motion allowed), from the end of the entrance: depth
 *      parallax and the 2° tilt, the pile breathing open, the preview of a hovered or focused segment's sheet.
 *   4. The autoplay (autoplay.js; the home hero only, never under reduced motion): a dwell of data-autoplay ms after
 *      each settle (the pager fills the segment shown), then the next document (wrapping); held while the pointer is
 *      over the stage or the pager, while keyboard focus is in the hero, while the hero is off screen and while the
 *      tab is hidden; stopped for good by any manual choice; the toggle plays or pauses it.
 * Any error leaves the final, static composition (the armed class is always removed).
 */
import { finePointer, reducedMotion } from '../lib/prefs.js';
import { createAutoplay, DWELL_MS } from './autoplay.js';
import { drawIn, fadeOut } from './callouts.js';
import { deal } from './deal.js';
import { createHover } from './hover.js';
import { pagerControls } from './pager.js';
import { moveToFront, nextOf, pullPose, stepOf } from './pile.js';
import { EASE_OUT, timing } from './spring.js';

/** The switch's spring (brief §6). */
export const SWITCH_SPRING = { stiffness: 170, damping: 20, mass: 1 };
/** The chosen sheet's lift out of the pile (ms), the old top's ink and callouts fading out, the new ink fading in. */
export const PULL_MS = 240;
export const INK_OUT_MS = 160;
export const INK_IN = { delay: 150, duration: 260 };
const CALLOUTS_OUT_MS = 120;
/** Opacity of the lifted ("air") shadow: pulled out of the pile, and on top of it (stage.css .is-active). */
const AIR_PULLED = 0.85;
const AIR_TOP = 0.3;

const wait = (ms) => new Promise((resolve) => window.setTimeout(resolve, ms));

/**
 * The switch's schedule (pure; Vitest: stage.test.js): the lift, then the travel onto the top for `distance` canvas
 * px on the brief's spring, the ink fading in during the travel, settled at `settled` ms.
 */
export function switchPlan(distance) {
    const spring = timing(SWITCH_SPRING, distance, 0.5);
    return {
        pull: PULL_MS,
        travel: spring.duration,
        easing: spring.easing,
        inkOut: INK_OUT_MS,
        inkIn: { delay: INK_IN.delay, duration: INK_IN.duration },
        settled: PULL_MS + spring.duration,
    };
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

const fine = (event) => event.pointerType === 'mouse' || event.pointerType === 'pen';
const safely = (anim) => {
    try {
        anim?.cancel();
    } catch {
        /* gone */
    }
};

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
    const docs = new Map(
        Array.from(stage.querySelectorAll('[data-hero-doc]'), (el) => {
            const type = el.dataset.heroDoc;
            return [type, {
                type,
                el,
                sheet: el.querySelector('[data-sheet]'),
                paper: el.querySelector('[data-doc-paper]'),
                callouts: el.querySelector('[data-hero-callouts]'),
                air: el.querySelector('.doc__shadow--air'),
                rest: el.querySelector('.doc__shadow--rest'),
                // The ink: every child of its papers but the cheque's waves and the memo's fold, and the watermark.
                ink: Array.from(el.querySelectorAll('.doc__sheet > :not(.cheque__waves):not(.doc__fold), .doc__wm')),
            }];
        }),
    );
    const notes = Array.from(root.querySelectorAll('[data-notes]'));
    const tiltEl = stage.querySelector('[data-tilt]');
    let order = [...docs.values()].sort((a, b) => Number(a.el.dataset.depth) - Number(b.el.dataset.depth)).map((d) => d.type);
    let active = order[0];

    let entrance = null;     // the running entrance (deal.js)
    let drawing = null;      // the running callout draw-in
    let moves = [];          // the running switch's animations
    let pending = null;      // a switch whose chosen sheet is still being lifted out (the pile not yet re-ordered)
    let generation = 0;
    let timers = [];
    let watcher = null;
    let visible = null;

    /* ------------------------------------------------------------------ poses */
    let poses = new Map();
    /** A document's pose as CSS draws it (physical canvas px and degrees): its place in the pile, or the top. */
    function poseOf(d, top) {
        const key = `${d.type}|${top ? 1 : 0}`;
        if (!poses.has(key)) {
            const cs = getComputedStyle(d.el);
            const n = (name) => parseFloat(cs.getPropertyValue(name)) || 0;
            poses.set(key, top ? { x: n('--tx') * dir, y: n('--ty'), rot: 0 } : { x: n('--px') * dir, y: n('--py'), rot: n('--pr') * dir });
        }
        return poses.get(key);
    }
    /** The sheet's width on the canvas (px). */
    const widthOf = (d) => (d.paper?.offsetWidth || 360) * (parseFloat(getComputedStyle(d.el).getPropertyValue('--s')) || 1);

    function applyOrder() {
        order.forEach((type, depth) => {
            const d = docs.get(type);
            d.el.dataset.depth = String(depth);
            d.el.classList.toggle('is-active', depth === 0);
        });
        stage.dataset.active = order[0];
    }

    /* ------------------------------------------------------------------ pointer */
    const hover = createHover({
        area: stage,
        tiltEl,
        dir,
        docs: () => order.map((type, depth) => {
            const d = docs.get(type);
            const pile = poseOf(d, false);
            const top = poseOf(d, true);
            return { ...d, depth, pose: { x: pile.x - top.x, y: pile.y - top.y, rot: pile.rot } };
        }),
    });
    const hoverAllowed = () => !isPhone() && finePointer() && !reducedMotion();
    const enableHover = () => {
        if (hoverAllowed()) hover.enable();
    };
    let segPeek = null;
    let sheetPeek = null;
    const peekNow = () => hover.preview(segPeek ?? sheetPeek);

    /* ------------------------------------------------------------------ finishing */
    function finishAll() {
        timers.forEach((t) => window.clearTimeout(t));
        timers = [];
        if (entrance) {
            entrance.finish();
            entrance = null;
        }
        if (pending) {
            order = pending.order;
            applyOrder();
            pending = null;
        }
        for (const anim of moves) safely(anim);
        moves = [];
        if (drawing) {
            const d = drawing;
            drawing = null;
            d.finish();
        }
        for (const d of docs.values()) d.el.classList.remove('is-pending', 'is-leaving');
        html.classList.remove('hero-armed');
        // The composition is final: the autoplay may count again (a switch that follows unsettles it at once).
        auto.settle();
    }

    function showNotes(type) {
        for (const list of notes) list.hidden = list.dataset.notes !== type;
    }

    /** Draw the callouts of the document on top after `delay` ms; the autoplay counts from their end. */
    function draw(d, delay = 0) {
        d.el.classList.remove('is-pending');
        if (reducedMotion()) {
            auto.settle();
            return;
        }
        const mine = drawIn(d.el, { dir, delay });
        drawing = mine;
        mine.finished.then(() => {
            if (drawing !== mine) return;
            drawing = null;
            auto.settle();
        });
    }

    /* ------------------------------------------------------------------ the switch */
    function select(type, step = 1, { manual = false } = {}) {
        if (!docs.has(type)) return;
        if (manual) auto.stop();
        finishAll();
        if (type === active) {
            pager?.update(active);
            return;
        }
        auto.unsettle();
        const gen = ++generation;
        const from = docs.get(active);
        const to = docs.get(type);
        const next = moveToFront(order, type);
        active = type;
        pager?.update(type);
        showNotes(type);

        if (reducedMotion()) {
            order = next;
            applyOrder();
            hover.refresh();
            draw(to);
            return;
        }

        // The old top: its callouts and ink fade out as it goes back to its own place in the pile.
        const fromTop = poseOf(from, true);
        const fromPile = poseOf(from, false);
        from.el.classList.remove('is-active');
        from.el.classList.add('is-leaving');
        const back = timing(SWITCH_SPRING, Math.hypot(fromPile.x - fromTop.x, fromPile.y - fromTop.y), 0.5);
        const backOpts = { duration: back.duration, easing: back.easing };
        moves.push(
            fadeOut(from.el, CALLOUTS_OUT_MS),
            ...from.ink.map((e) => e.animate([{ opacity: 1, offset: 0 }], { duration: INK_OUT_MS, easing: EASE_OUT })),
            from.sheet.animate([{ translate: `${fromTop.x}px ${fromTop.y}px`, rotate: '0deg', offset: 0 }], backOpts),
            from.air?.animate([{ opacity: AIR_TOP, offset: 0 }], backOpts),
        );

        // The chosen sheet lifts out of its place (still under the sheets above it).
        const pose = poseOf(to, false);
        const out = pullPose(pose, widthOf(to), dir);
        const pullOpts = { duration: PULL_MS, easing: EASE_OUT, fill: 'forwards' };
        const outFrame = { translate: `${out.x}px ${out.y}px`, rotate: `${out.rot}deg`, scale: String(out.scale) };
        const pull = to.sheet.animate([{ translate: `${pose.x}px ${pose.y}px`, rotate: `${pose.rot}deg`, scale: '1' }, outFrame], pullOpts);
        const pullAir = to.air?.animate({ opacity: [0, AIR_PULLED] }, pullOpts);
        moves.push(pull, pullAir);
        moves = moves.filter(Boolean);
        pending = { order: next };

        pull.finished.then(() => {
            if (gen !== generation || !pending) return;
            // Now it is the top of the pile: it travels onto the top, its ink fading in as it arrives.
            pending = null;
            order = next;
            applyOrder();
            from.el.classList.remove('is-leaving');
            to.el.classList.add('is-pending');
            const plan = switchPlan(out.distance);
            const travelOpts = { duration: plan.travel, easing: plan.easing };
            const travel = to.sheet.animate([{ ...outFrame, offset: 0 }], travelOpts);
            moves.push(
                travel,
                to.air?.animate([{ opacity: AIR_PULLED, offset: 0 }], travelOpts),
                ...to.ink.map((e) => e.animate({ opacity: [0, 1] }, { duration: plan.inkIn.duration, delay: plan.inkIn.delay, easing: EASE_OUT, fill: 'backwards' })),
            );
            moves = moves.filter(Boolean);
            safely(pull);
            safely(pullAir);
            hover.refresh();
            travel.finished.then(() => {
                if (gen !== generation) return;
                moves = [];
                // The leaders draw in once the paper has settled.
                draw(to);
            }).catch(() => {});
        }).catch(() => {});
    }

    /* ------------------------------------------------------------------ autoplay */
    const pagerEl = root.querySelector('[data-hero-pager]');
    const dwell = Number(root.dataset.autoplay) || 0;
    const auto = createAutoplay({
        dwell: dwell || DWELL_MS,
        playing: dwell > 0 && !reducedMotion(),
        startTimer: (ms) => pager?.fill(ms) ?? null,
        onAdvance: () => select(nextOf(pager?.types ?? order, active, 1), 1),
        onChange: ({ playing }) => pager?.setPlaying(playing),
    });
    const pager = pagerEl
        ? pagerControls(pagerEl, {
            dir,
            onSelect: (type, step) => select(type, step, { manual: true }),
            onToggle: () => auto.toggle(),
            onPreview: (type) => {
                segPeek = type && type !== active ? type : null;
                peekNow();
            },
        })
        : null;
    pager?.setPlaying(auto.playing);
    const toggle = pagerEl?.querySelector('[data-pager-toggle]') ?? null;

    // Holds: the pointer over the stage or the pager (not over the toggle itself), keyboard focus in the hero (not on
    // the toggle), the hero off screen, the tab hidden.
    const zones = { stage: false, pager: false, toggle: false };
    const syncHover = () => (zones.stage || (zones.pager && !zones.toggle) ? auto.hold('hover') : auto.release('hover'));
    const zone = (name, on) => (event) => {
        if (!fine(event)) return;
        zones[name] = on;
        syncHover();
    };
    const listeners = [];
    const listen = (el, type, fn, opts) => {
        if (!el) return;
        el.addEventListener(type, fn, opts);
        listeners.push(() => el.removeEventListener(type, fn, opts));
    };
    listen(stage, 'pointerenter', zone('stage', true));
    listen(stage, 'pointerleave', zone('stage', false));
    listen(pagerEl, 'pointerenter', zone('pager', true));
    listen(pagerEl, 'pointerleave', zone('pager', false));
    listen(toggle, 'pointerenter', zone('toggle', true));
    listen(toggle, 'pointerleave', zone('toggle', false));
    listen(area, 'focusin', (event) => {
        const t = event.target;
        if (t !== toggle && t.matches?.(':focus-visible')) auto.hold('focus');
        else auto.release('focus');
    });
    listen(area, 'focusout', (event) => {
        if (!area.contains(event.relatedTarget)) auto.release('focus');
    });
    const onVisibility = () => (document.visibilityState === 'hidden' ? auto.hold('hidden') : auto.release('hidden'));
    listen(document, 'visibilitychange', onVisibility);
    onVisibility();
    if (typeof IntersectionObserver === 'function') {
        visible = new IntersectionObserver((entries) => {
            const e = entries[entries.length - 1];
            if (e.isIntersecting && e.intersectionRatio >= 0.25) auto.release('offscreen');
            else auto.hold('offscreen');
        }, { threshold: [0, 0.25, 0.5] });
        visible.observe(stage);
    }

    // A back sheet under the pointer peeks up; a click on it brings it to the top (a pointer shortcut: the pager is
    // the accessible control).
    listen(stage, 'pointerover', (event) => {
        if (!fine(event)) return;
        const doc = event.target.closest?.('[data-hero-doc]');
        sheetPeek = doc && !doc.classList.contains('is-active') && event.target.closest('.doc__sheet, .phone') ? doc.dataset.heroDoc : null;
        peekNow();
    });
    listen(stage, 'pointerleave', () => {
        sheetPeek = null;
        peekNow();
    });
    listen(stage, 'click', (event) => {
        const doc = event.target.closest?.('[data-hero-doc]');
        if (!doc || doc.dataset.heroDoc === active || !event.target.closest('.doc__sheet, .phone')) return;
        select(doc.dataset.heroDoc, stepOf(pager?.types ?? order, active, doc.dataset.heroDoc), { manual: true });
    });

    /* ------------------------------------------------------------------ wiring */
    function onPhone() {
        finishAll();
        poses = new Map();
        if (hoverAllowed()) enableHover();
        else hover.disable({ now: true });
    }

    function onMotionPref() {
        if (reducedMotion()) {
            auto.stop();
            finishAll();
            hover.disable({ now: true });
        } else {
            enableHover();
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
        enableHover();
        auto.settle();
    }

    /** fromPaint: on the storyboard's clock (the first sheet 350 ms after the first paint); else now. */
    function startEntrance(fromPaint) {
        const top = docs.get(active);
        const t0 = firstPaint() ?? performance.now();
        const now = performance.now();
        const k = canvas.getBoundingClientRect().width / (canvas.offsetWidth || 1);
        const start = fromPaint ? Math.max(0, t0 + 350 - now) : 0;
        top.el.classList.add('is-pending');
        // From the bottom of the pile up; the document on top lands last.
        const sheets = [...order].reverse().map((type) => {
            const d = docs.get(type);
            const p = poseOf(d, type === active);
            return { el: d.sheet, x: p.x, y: p.y, rot: p.rot, air: d.air, rest: d.rest };
        });
        entrance = deal({ sheets, dir, mobile: isPhone(), k, start });
        html.classList.remove('hero-armed');
        // The callouts once the document rests (and not before 1.5 s after the first paint).
        const at = fromPaint ? Math.max(entrance.restAt, t0 + 1500 - now) : entrance.restAt;
        draw(top, at);
        timers.push(window.setTimeout(() => {
            entrance = null;
            enableHover();
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
        visible?.disconnect();
        finishAll();
        auto.destroy();
        hover.destroy();
        pager?.destroy();
        listeners.forEach((off) => off());
        phone.removeEventListener('change', onPhone);
        motionPref.removeEventListener('change', onMotionPref);
        window.removeEventListener('pageshow', onPageShow);
        window.removeEventListener('beforeprint', onPrint);
    };
}
