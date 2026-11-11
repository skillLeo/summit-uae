/**
 * Carousel (<x-d2.ui.carousel>, resources/views/components/d2/ui/carousel.blade.php; upgrade spec §8, §12 E).
 * The rail itself is CSS (scroll-snap, resources/css/d2/components/carousel.css) and works without
 * JavaScript. d2Carousel adds:
 *   - position dots: one button per visible slide, the dot of the slide at the snap line is current
 *     (aria-current="true"); a dot scrolls its slide to the start. The dots are one Tab stop: ←/→ (reversed
 *     in RTL), Home and End move to a dot and scroll its slide in. Every dot is a --dot-hit (48 px) square;
 *   - the pager instead of the dots on a rail with more slides than 48 px dots fit in its row (final pass,
 *     home-mobile: twelve dots were 29 px wide): previous / next buttons from <template data-carousel-pager>
 *     (aria-disabled at either end, ←/→/Home/End work on them too) around the position ("3/12", proposed
 *     mobile.carousel.count) and a polite "Card 3 of 12" for screen readers;
 *   - role="group", aria-roledescription and the name, only while the element is a carousel (a
 *     `data-until` carousel becomes a plain list from that width and is announced as one);
 *   - a focusable track (←/→/Home/End) when the slides hold nothing focusable themselves;
 *   - keyboard focus on a slide that is cut by the rail's edge (the browser only scrolls the focused link
 *     itself into view) brings the whole slide to the snap line, so a focused card is never half hidden;
 *   - a #fragment inside a slide (on load and on hashchange) snaps that slide to the start;
 *   - .carousel--ready on the root once the dots are drawn (the row reserved for them under html.js gives
 *     way in the same task, components/carousel.css);
 *   - the bubbling event `d2-carousel-change` { index, count } when the current slide changes.
 * No autoplay. Scrolling honours prefers-reduced-motion (lib/prefs.js scrollBehavior()). Filtered slides
 * (hidden, display:none) get no dot: the dots follow the slides through a MutationObserver.
 */
import { scrollBehavior } from '../lib/prefs.js';

const FOCUSABLE = 'a[href], button, input, select, textarea, summary, [tabindex]:not([tabindex="-1"]), [contenteditable="true"]';
const END_SLACK = 2; // px: a track scrolled this close to its end shows its last slide as current

/** Index of the smallest value (ties keep the first); -1 for an empty list. */
export function nearestIndex(distances) {
    let best = -1;
    for (let i = 0; i < distances.length; i += 1) {
        if (best === -1 || distances[i] < distances[best] - 0.5) best = i;
    }
    return best;
}

/**
 * The slide a key moves to, or null when the key is not a carousel key. "Next" is the reading direction:
 * ArrowRight in LTR, ArrowLeft in RTL.
 */
export function keyTarget(key, index, count, rtl = false) {
    if (count < 1) return null;
    const next = rtl ? 'ArrowLeft' : 'ArrowRight';
    const previous = rtl ? 'ArrowRight' : 'ArrowLeft';
    if (key === next) return Math.min(count - 1, index + 1);
    if (key === previous) return Math.max(0, index - 1);
    if (key === 'Home') return 0;
    if (key === 'End') return count - 1;
    return null;
}

/** Dots fit when every one gets its full target (`target` px) in a row `width` px wide. */
export function dotsFit(count, width, target) {
    if (!(target > 0)) return true;
    return count * target <= width + 0.5;
}

/** The dot's accessible name from its template ("Card :n of :total"); also the pager's position (":n/:total"). */
export function dotLabel(template, n, total) {
    return String(template || '')
        .replace(/:n\b/g, String(n))
        .replace(/:total\b/g, String(total));
}

/**
 * Horizontal distance from a slide's start edge to the track's snap line (start edge + scroll padding),
 * for a scroll by that delta (scrollTo(scrollLeft + delta)): positive moves the content towards the inline end.
 * Works for LTR and RTL alike.
 */
export function snapDelta(slide, track, pad, rtl) {
    return rtl ? slide.right - (track.right - pad) : slide.left - (track.left + pad);
}

/** A slide's box is cut by the track's visible box (either inline edge, 1 px tolerance). */
export function cut(slide, track) {
    return slide.left < track.left - 1 || slide.right > track.right + 1;
}

function visible(el) {
    return !el.hidden && el.getClientRects().length > 0;
}

function setup(root) {
    const track = root.querySelector('[data-carousel-track]');
    if (!track) return () => {};
    const dotsBox = root.querySelector('[data-carousel-dots]');
    const pagerTemplate = root.querySelector('template[data-carousel-pager]');
    const until = Number(root.dataset.until) || 0;
    const wide = until ? window.matchMedia(`(width >= ${until}px)`) : null;
    const rtl = () => getComputedStyle(track).direction === 'rtl';

    let slides = [];
    let dots = [];
    let pager = null; // { prev, next, position, label } while the pager replaces the dots
    let mode = 'dots';
    let index = 0;
    let frame = 0;
    let active = false; // carousel mode (not a plain list)
    const cleanups = [];
    const listen = (target, type, handler, options) => {
        target.addEventListener(type, handler, options);
        cleanups.push(() => target.removeEventListener(type, handler, options));
    };

    const padStart = () => {
        const value = parseFloat(getComputedStyle(track).scrollPaddingInlineStart);
        return Number.isFinite(value) ? value : 0;
    };
    const overflowing = () => track.scrollWidth - track.clientWidth > 1;
    const atEnd = () => Math.abs(track.scrollLeft) >= track.scrollWidth - track.clientWidth - END_SLACK;

    function current() {
        if (slides.length === 0) return 0;
        if (atEnd() && Math.abs(track.scrollLeft) > END_SLACK) return slides.length - 1;
        const box = track.getBoundingClientRect();
        const pad = padStart();
        const isRtl = rtl();
        return Math.max(0, nearestIndex(slides.map((slide) => Math.abs(snapDelta(slide.getBoundingClientRect(), box, pad, isRtl)))));
    }

    const hit = () => parseFloat(getComputedStyle(root).getPropertyValue('--dot-hit')) || 48;
    const wantedMode = () => (pagerTemplate && !dotsFit(slides.length, root.clientWidth, hit()) ? 'pager' : 'dots');

    function paintPager() {
        if (!pager) return;
        const total = slides.length;
        if (pager.position) pager.position.textContent = dotLabel(dotsBox.dataset.count || ':n/:total', index + 1, total);
        if (pager.label) pager.label.textContent = dotLabel(dotsBox.dataset.dotLabel, index + 1, total);
        for (const [button, off] of [[pager.prev, index <= 0], [pager.next, index >= total - 1]]) {
            if (!button) continue;
            if (off) button.setAttribute('aria-disabled', 'true');
            else button.removeAttribute('aria-disabled');
        }
    }

    function paint(focus = false) {
        if (mode === 'pager') {
            paintPager();
            return;
        }
        dots.forEach((dot, i) => {
            const on = i === index;
            if (on) dot.setAttribute('aria-current', 'true');
            else dot.removeAttribute('aria-current');
            dot.tabIndex = on ? 0 : -1;
        });
        if (focus && dots[index]) dots[index].focus({ preventScroll: true });
    }

    function sync() {
        frame = 0;
        if (!active) return;
        const next = current();
        if (next === index) return;
        index = next;
        paint();
        root.dispatchEvent(new CustomEvent('d2-carousel-change', { bubbles: true, detail: { index, count: slides.length } }));
    }

    function goTo(target, { focus = false, instant = false } = {}) {
        const slide = slides[target];
        if (!slide) return;
        const delta = snapDelta(slide.getBoundingClientRect(), track.getBoundingClientRect(), padStart(), rtl());
        // An absolute destination: Chrome snaps a relative scrollBy() in its direction, so even a sub-pixel
        // correction jumped a whole card. Already at the snap line: no scroll at all.
        if (Math.abs(delta) >= 1) track.scrollTo({ left: track.scrollLeft + delta, behavior: instant ? 'auto' : scrollBehavior() });
        if (target !== index) {
            index = target;
            root.dispatchEvent(new CustomEvent('d2-carousel-change', { bubbles: true, detail: { index, count: slides.length } }));
        }
        paint(focus);
    }

    function buildDots(next = wantedMode()) {
        if (!dotsBox) return;
        const template = dotsBox.dataset.dotLabel || '';
        mode = next;
        dots = [];
        pager = null;
        dotsBox.classList.toggle('carousel__dots--pager', mode === 'pager');
        if (mode === 'pager') {
            // Filled before it joins the page, so the polite position is not announced on load.
            const fragment = pagerTemplate.content.cloneNode(true);
            pager = {
                prev: fragment.querySelector('[data-carousel-step="-1"]'),
                next: fragment.querySelector('[data-carousel-step="1"]'),
                position: fragment.querySelector('[data-carousel-position]'),
                label: fragment.querySelector('[data-carousel-position-label]'),
            };
            paintPager();
            dotsBox.replaceChildren(fragment);
            return;
        }
        dotsBox.replaceChildren();
        dots = slides.map((slide, i) => {
            const dot = document.createElement('button');
            dot.type = 'button';
            dot.className = 'carousel__dot';
            dot.dataset.index = String(i);
            dot.setAttribute('aria-label', dotLabel(template, i + 1, slides.length));
            const mark = document.createElement('span');
            mark.className = 'carousel__dot-mark';
            mark.setAttribute('aria-hidden', 'true');
            dot.append(mark);
            dotsBox.append(dot);
            return dot;
        });
    }

    function label(on) {
        const named = root.dataset.labelledby ? 'aria-labelledby' : root.dataset.label ? 'aria-label' : null;
        if (on) {
            root.setAttribute('role', 'group');
            root.setAttribute('aria-roledescription', root.dataset.roledescription || '');
            if (named) root.setAttribute(named, named === 'aria-labelledby' ? root.dataset.labelledby : root.dataset.label);
        } else {
            for (const name of ['role', 'aria-roledescription', 'aria-labelledby', 'aria-label']) root.removeAttribute(name);
        }
    }

    /** Re-reads the slides and the mode (resize, breakpoint, filtered slides). */
    function refresh() {
        const before = slides.length;
        slides = Array.from(track.children).filter(visible);
        const on = !(wide && wide.matches) && slides.length > 1;
        active = on;
        label(on);
        const scrolls = on && overflowing();
        if (dotsBox) {
            const next = wantedMode();
            if (before !== slides.length || next !== mode || (mode === 'dots' && dots.length !== slides.length)) buildDots(next);
            dotsBox.hidden = !scrolls;
        }
        // Keyboard access to the rail when the slides hold nothing focusable themselves.
        if (on && scrolls && !track.querySelector(FOCUSABLE)) track.tabIndex = 0;
        else track.removeAttribute('tabindex');
        index = on ? current() : 0;
        paint();
    }

    function onKey(event) {
        if (!active || event.altKey || event.ctrlKey || event.metaKey) return;
        const fromDot = event.target instanceof Element && event.target.closest('.carousel__dot, [data-carousel-step]');
        if (!fromDot && event.target !== track) return;
        const target = keyTarget(event.key, index, slides.length, rtl());
        if (target === null) return;
        event.preventDefault();
        goTo(target, { focus: !!fromDot });
    }

    // Focus moved onto a slide the rail's edge cuts: snap that slide in (home-mobile). Measured on the next
    // frame, after the browser's own focus scroll.
    let focusFrame = 0;
    function onFocus(event) {
        if (!active) return;
        const slide = slides.find((item) => item.contains(event.target));
        if (!slide) return;
        window.cancelAnimationFrame(focusFrame);
        focusFrame = window.requestAnimationFrame(() => {
            if (active && cut(slide.getBoundingClientRect(), track.getBoundingClientRect())) goTo(slides.indexOf(slide));
        });
    }

    // A #fragment inside a slide (Design 1's #preview-case-04, #guide-g04-title, #gc-gc-02 …): that slide
    // snaps to the start. The browser's own fragment scroll only makes the target visible and, in RTL, can
    // stop a slide short (home-mobile). At once on load, smoothly on a later hashchange.
    let hashFrame = 0;
    function fromHash(instant) {
        window.cancelAnimationFrame(hashFrame);
        hashFrame = window.requestAnimationFrame(() => {
            if (!active) return;
            let id = '';
            try {
                id = decodeURIComponent(window.location.hash.slice(1));
            } catch {
                return;
            }
            const target = id ? document.getElementById(id) : null;
            const slide = target && track.contains(target) ? slides.find((item) => item.contains(target)) : null;
            if (slide) goTo(slides.indexOf(slide), { instant });
        });
    }

    listen(track, 'scroll', () => {
        if (!frame) frame = window.requestAnimationFrame(sync);
    }, { passive: true });
    listen(window, 'hashchange', () => fromHash(false));
    if (document.readyState === 'complete') fromHash(true);
    else listen(window, 'load', () => fromHash(true), { once: true });
    listen(track, 'scrollend', sync);
    listen(track, 'keydown', onKey);
    listen(track, 'focusin', onFocus);
    if (dotsBox) {
        listen(dotsBox, 'keydown', onKey);
        listen(dotsBox, 'click', (event) => {
            const target = event.target instanceof Element ? event.target : null;
            const step = target?.closest('[data-carousel-step]');
            if (step) {
                if (step.getAttribute('aria-disabled') !== 'true') goTo(Math.max(0, Math.min(slides.length - 1, index + Number(step.dataset.carouselStep))));
                return;
            }
            const dot = target?.closest('.carousel__dot');
            if (dot) goTo(Number(dot.dataset.index));
        });
    }
    if (wide) listen(wide, 'change', refresh);

    // Layout changes (resize, breakpoint, a filter hiding slides) are batched into one refresh per frame.
    let pending = 0;
    const schedule = () => {
        if (!pending) pending = window.requestAnimationFrame(() => {
            pending = 0;
            refresh();
        });
    };
    let resize = null;
    if ('ResizeObserver' in window) {
        resize = new ResizeObserver(schedule);
        resize.observe(track);
    } else {
        listen(window, 'resize', schedule);
    }
    // Only slides joining or leaving, or a slide's own hidden / class / style changing, matter here.
    const mutations = new MutationObserver((records) => {
        if (records.some((record) => record.target === track || record.target.parentElement === track)) schedule();
    });
    mutations.observe(track, { childList: true, subtree: true, attributes: true, attributeFilter: ['hidden', 'class', 'style'] });

    refresh();
    // In the same task that showed the dots: the row reserved under html.js (carousel.css) gives way to them.
    root.classList.add('carousel--ready');

    return () => {
        root.classList.remove('carousel--ready');
        cleanups.forEach((fn) => fn());
        if (resize) resize.disconnect();
        mutations.disconnect();
        if (frame) window.cancelAnimationFrame(frame);
        if (pending) window.cancelAnimationFrame(pending);
        if (focusFrame) window.cancelAnimationFrame(focusFrame);
        if (hashFrame) window.cancelAnimationFrame(hashFrame);
    };
}

export default (Alpine) => {
    Alpine.data('d2Carousel', () => {
        // Per-instance teardown, outside Alpine's reactive state.
        let teardown = null;
        return {
            init() {
                teardown = setup(this.$root);
            },
            destroy() {
                if (teardown) teardown();
                teardown = null;
            },
        };
    });
};
