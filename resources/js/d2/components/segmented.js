/**
 * d2Segmented: the sliding pill of <x-d2.ui.segmented> (resources/views/components/d2/ui/segmented.blade.php).
 *
 * The control is a native radio group, so selection, keyboard (arrow keys) and forms work without this
 * script. Here we only measure the checked segment and move a white pill under it: the position and width
 * are custom properties on the root (--seg-x, --seg-w; set through CSSOM, which the CSP allows) and CSS
 * moves the pill with the ui spring. In RTL the pill is anchored at the inline start (the right edge), so
 * x is negative there.
 *
 * Each change also dispatches a bubbling CustomEvent 'd2-change' with detail { name, value }; the radios'
 * own 'change' event bubbles as well (event.target.value). When the options do not fit, the scroller
 * scrolls sideways, keeps the checked segment in view and fades the edges that hide more options.
 */
/** Many options on a narrow screen make the scroller scroll: keep the checked segment in view. */
function reveal(scroller, option) {
    if (!scroller || scroller.scrollWidth <= scroller.clientWidth + 1) return;
    const edge = 8;
    const s = scroller.getBoundingClientRect();
    const o = option.getBoundingClientRect();
    // Relative moves work in both directions (RTL scrollLeft runs from 0 to negative values).
    if (o.left < s.left) scroller.scrollLeft -= s.left - o.left + edge;
    else if (o.right > s.right) scroller.scrollLeft += o.right - s.right + edge;
}

/** While the scroller overflows, mark which ends still hide segments (CSS fades those edges). */
function edges(root, scroller) {
    if (!scroller) return;
    const max = scroller.scrollWidth - scroller.clientWidth;
    const overflow = max > 1;
    root.classList.toggle('is-overflow', overflow);
    if (!overflow) return;
    const pos = Math.abs(scroller.scrollLeft); // RTL scrollLeft runs from 0 to negative values
    root.classList.toggle('at-start', pos <= 1);
    root.classList.toggle('at-end', pos >= max - 1);
}

export default (Alpine) => {
    Alpine.data('d2Segmented', () => {
        // Per-instance handles live in the closure, outside Alpine's reactive state.
        let observer = null;
        let frame = 0;

        return {
            init() {
                const root = this.$root;
                // Position the pill before showing it (no slide-in from the start edge on load).
                this.place(false);
                root.classList.add('is-ready');
                const scroller = this.$refs.scroller;
                if (scroller) scroller.addEventListener('scroll', () => edges(root, scroller), { passive: true });
                if ('ResizeObserver' in window) {
                    // Track width changes (viewport, container) and label widths (web fonts arriving).
                    observer = new ResizeObserver(() => this.schedule());
                    observer.observe(this.$refs.track);
                    for (const option of root.querySelectorAll('.segmented__option')) observer.observe(option);
                }
                requestAnimationFrame(() => root.classList.add('is-animated'));
            },

            destroy() {
                if (observer) observer.disconnect();
                cancelAnimationFrame(frame);
            },

            /** x-on:change on the root: one of its radios changed. */
            sync(event) {
                const input = event && event.target;
                if (!input || input.type !== 'radio' || !input.classList.contains('segmented__input')) return;
                this.place(true);
                this.$root.dispatchEvent(new CustomEvent('d2-change', {
                    bubbles: true,
                    detail: { name: input.name, value: input.value },
                }));
            },

            schedule() {
                cancelAnimationFrame(frame);
                frame = requestAnimationFrame(() => this.place(false));
            },

            /** Measure the checked segment with layout values (a transformed ancestor does not skew them). */
            place(animate) {
                const root = this.$root;
                const track = this.$refs.track;
                const input = root.querySelector('.segmented__input:checked');
                const option = input ? root.querySelector(`label[for="${CSS.escape(input.id)}"]`) : null;
                if (!option || !track) {
                    root.classList.remove('has-value');
                    return;
                }
                const rtl = getComputedStyle(root).direction === 'rtl';
                // offsetLeft is measured from the track's padding edge; the pill sits at inset-inline-start: 0.
                const x = rtl ? -(track.clientWidth - option.offsetLeft - option.offsetWidth) : option.offsetLeft;
                if (!animate) root.classList.remove('is-animated');
                root.style.setProperty('--seg-x', `${x}px`);
                root.style.setProperty('--seg-w', `${option.offsetWidth}px`);
                root.classList.add('has-value');
                reveal(this.$refs.scroller, option);
                edges(root, this.$refs.scroller);
                if (!animate && root.classList.contains('is-ready')) {
                    // Apply the jump without a transition, then re-enable the spring on the next frame.
                    void track.offsetWidth;
                    requestAnimationFrame(() => root.classList.add('is-animated'));
                }
            },
        };
    });
};
