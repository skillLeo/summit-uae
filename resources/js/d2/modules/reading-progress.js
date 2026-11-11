/**
 * Reading progress (resources/views/components/d2/inner/progress.blade.php; upgrade spec §7.4, §12 Phase D):
 * the 3 px bronze bar under the header grows from 0 (the target's top reaches the header's bottom edge) to 1
 * (its end reaches the bottom of the window). Drawn with transform: scaleX(var(--reading)); the value is set
 * through CSSOM on scroll and resize, at most once per frame, and only when it changes. The bar is
 * decorative (aria-hidden) and stays hidden without JavaScript.
 */

/**
 * 0–1 progress of a target box (viewport coordinates) read between the header's bottom and the window's bottom.
 * A target without a box (display: none, e.g. P03's guides while the finder matches nothing) has nothing to
 * read: 0, never a full bar.
 */
export function progressOf(top, height, headerBottom, viewportHeight) {
    if (!(height > 0)) return 0;
    const visible = Math.max(1, viewportHeight - headerBottom);
    const distance = height - visible;
    if (distance <= 0) return top <= headerBottom ? 1 : 0;
    const p = (headerBottom - top) / distance;
    return Math.min(1, Math.max(0, p));
}

export default function readingProgress() {
    const bars = Array.from(document.querySelectorAll('[data-reading-progress]'));
    if (!bars.length) return;
    const header = document.querySelector('[data-d2-header]');

    const items = bars
        .map((bar) => {
            const id = bar.getAttribute('data-reading-progress') || 'main-content';
            const target = document.getElementById(id) || document.getElementById('main-content');
            const fill = bar.querySelector('.reading-progress__bar');
            return target && fill ? { bar, target, fill, value: -1 } : null;
        })
        .filter(Boolean);
    if (!items.length) return;

    let frame = 0;
    const update = () => {
        frame = 0;
        const headerBottom = header ? Math.max(0, header.getBoundingClientRect().bottom) : 0;
        for (const item of items) {
            const box = item.target.getBoundingClientRect();
            const value = Math.round(progressOf(box.top, box.height, headerBottom, window.innerHeight) * 1000) / 1000;
            if (value === item.value) continue;
            item.value = value;
            item.fill.style.setProperty('--reading', String(value));
        }
    };
    const schedule = () => {
        if (!frame) frame = window.requestAnimationFrame(update);
    };

    for (const item of items) item.bar.classList.add('is-ready');
    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule, { passive: true });
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(schedule).catch(() => {});
    update();
}
