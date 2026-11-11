// Local guide search in the search sheet (approved Knowledge Centre strings). Nothing is sent
// anywhere: it filters the nine guide titles and summaries already in the page.
import { normalize } from '../lib/util';

export function guideSearch() {
    return {
        query: '',
        init() {
            this.items = [...this.$el.querySelectorAll('[data-search-item]')].map((el) => ({ el, text: normalize(el.dataset.searchText) }));
            this.empty = this.$el.querySelector('[data-search-empty]');
            this.$watch('query', () => this.filter());
        },
        filter() {
            const q = normalize(this.query);
            let visible = 0;
            for (const item of this.items) {
                const show = !q || item.text.includes(q);
                item.el.hidden = !show;
                if (show) visible++;
            }
            this.empty.hidden = visible > 0;
        },
    };
}
