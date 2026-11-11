// Who We Help on phones: a swipeable row with a segmented control that follows the scroll.
// Only the two rows scroll horizontally; the page itself is never scrolled by this component.
function centreInRow(row, el) {
    const r = row.getBoundingClientRect();
    const e = el.getBoundingClientRect();
    const delta = e.left + e.width / 2 - (r.left + r.width / 2);
    row.scrollBy({ left: delta, behavior: 'smooth' });
}

export function whoScroller() {
    return {
        init() {
            const list = this.$el.querySelector('[data-who-list]');
            const row = this.$el.querySelector('.who-segments');
            const segments = [...this.$el.querySelectorAll('[data-who-segment]')];
            if (!list || !row || !segments.length) return;

            // Keyboard users must be able to scroll the row when it is a scroller (phones only):
            // the list becomes focusable and named there (it keeps its native list role).
            const media = window.matchMedia('(max-width: 599.98px)');
            const syncRole = () => {
                if (media.matches) {
                    list.setAttribute('tabindex', '0');
                    list.setAttribute('aria-labelledby', 'who-title');
                } else {
                    ['tabindex', 'aria-labelledby'].forEach((a) => list.removeAttribute(a));
                }
            };
            syncRole();
            media.addEventListener('change', syncRole);

            segments.forEach((btn) => {
                btn.addEventListener('click', () => {
                    const item = list.querySelector(`[data-who-item="${btn.dataset.whoSegment}"]`);
                    if (item) centreInRow(list, item);
                });
            });

            if (!('IntersectionObserver' in window)) return;
            let userScrolled = false;
            list.addEventListener('scroll', () => { userScrolled = true; }, { passive: true });
            const io = new IntersectionObserver((entries) => {
                for (const entry of entries) {
                    if (!entry.isIntersecting) continue;
                    const i = entry.target.dataset.whoItem;
                    segments.forEach((s) => s.setAttribute('aria-current', String(s.dataset.whoSegment === i)));
                    const active = segments.find((s) => s.dataset.whoSegment === i);
                    if (active && userScrolled) centreInRow(row, active);
                }
            }, { root: list, threshold: 0.6 });
            list.querySelectorAll('[data-who-item]').forEach((el) => io.observe(el));
        },
    };
}
