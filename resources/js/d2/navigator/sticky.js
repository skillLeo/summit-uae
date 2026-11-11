/**
 * The starting file beside the Matter Navigator list (≥ 1280 px, Design 2.1 D29). navigator.css makes the card's slot
 * position: sticky with top: 96 px (the header plus 32), so it stays in view while the list scrolls with the page.
 * A file taller than the screen would then hide its end (its actions, its last mistakes) until the list ran out.
 * stickyFile() keeps such a file readable: it moves the sticky offset (--nav-card-top) with the page's scroll,
 * between 96 px (the file's start in view) and screen height - file height - 24 px (its end in view), so scrolling
 * down carries the file up to its end and scrolling up brings its start back. A file that fits keeps 96 px.
 * Nothing animates: the offset follows the scroll one frame at a time (transform-free, no layout shift elsewhere).
 */

/** The next sticky offset after the page scrolled by `delta` px (down > 0). */
export function nextOffset(offset, delta, { base, height, viewport, gap = 24 }) {
    const min = Math.min(base, viewport - height - gap);
    return Math.min(base, Math.max(min, offset - delta));
}

/**
 * Wires `el` (the sticky slot). `active()` says when the sticky layout applies (the desktop media query).
 * Returns { reset(), destroy() }: reset() shows the file from its start again (a new file was chosen).
 */
export function stickyFile(el, { active = () => true, gap = 24 } = {}) {
    let base = 0;
    let offset = 0;
    let lastY = window.scrollY;
    let frame = 0;

    const apply = () => {
        el.style.setProperty('--nav-card-top', `${offset}px`);
    };

    /** The CSS offset (96 px), read with ours taken away; then the file starts in view. */
    const measure = () => {
        el.style.removeProperty('--nav-card-top');
        base = active() ? parseFloat(getComputedStyle(el).top) || 0 : 0;
        offset = base;
        lastY = window.scrollY;
        if (active()) apply();
    };

    const update = () => {
        frame = 0;
        const y = window.scrollY;
        const delta = y - lastY;
        lastY = y;
        if (!active()) return;
        const next = nextOffset(offset, delta, { base, height: el.offsetHeight, viewport: window.innerHeight, gap });
        if (next !== offset) {
            offset = next;
            apply();
        }
    };

    const onScroll = () => {
        if (!frame) frame = window.requestAnimationFrame(update);
    };

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', measure);
    // The file grows or shrinks (another service, the sheet giving the card back): keep the offset in range.
    const observer = 'ResizeObserver' in window ? new ResizeObserver(() => update()) : null;
    observer?.observe(el);
    measure();

    return {
        reset() {
            if (!active()) return;
            offset = base;
            lastY = window.scrollY;
            apply();
        },
        measure,
        destroy() {
            window.removeEventListener('scroll', onScroll);
            window.removeEventListener('resize', measure);
            observer?.disconnect();
            if (frame) window.cancelAnimationFrame(frame);
            el.style.removeProperty('--nav-card-top');
        },
    };
}
