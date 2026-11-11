/**
 * The 404's search field (resources/views/designs/d2/errors/404.blade.php; final pass, inner-pages):
 * x-data="d2NotFoundSearch" on its <form role="search">.
 *
 * Submitting it (Enter, the phone keyboard's search key, the search button at its start) hands the typed words to the
 * command palette through the header's protocol (docs/d2/engineering.md §4a): a cancelable window event
 * 'd2:palette-open' with detail { source: 'click', query }. The palette claims it (preventDefault) and opens
 * with the words in its field. Unclaimed (no palette on the page), the form's action opens: the Knowledge
 * Centre, without the words. Typing alone changes nothing on the page (WCAG 3.2.2). The words are never put
 * in an address, stored or sent: the field has no name, so even a submission without this script carries
 * nothing. Without JavaScript the form is hidden and the list of the main pages replaces it (error.css).
 */

/** The visitor's words as the palette receives them: one line, single spaces, at most `max` characters. */
export function paletteQuery(value, max = 200) {
    if (typeof value !== 'string') return '';
    return value.replace(/\s+/gu, ' ').trim().slice(0, max).trim();
}

export default (Alpine) => {
    Alpine.data('d2NotFoundSearch', () => {
        let off = null;

        return {
            init() {
                const form = this.$root;
                const onSubmit = (event) => {
                    event.preventDefault();
                    this.search();
                };
                form.addEventListener('submit', onSubmit);
                off = () => form.removeEventListener('submit', onSubmit);
            },

            destroy() {
                if (off) off();
                off = null;
            },

            /** Open the palette with the typed words (or follow the form's action when nothing claims it). */
            search() {
                const form = this.$root;
                const input = form.querySelector('[data-nf-query]');
                const event = new CustomEvent('d2:palette-open', {
                    cancelable: true,
                    detail: { source: 'click', query: paletteQuery(input ? input.value : '') },
                });
                window.dispatchEvent(event);
                if (!event.defaultPrevented && form.action) window.location.assign(form.action);
            },
        };
    });
};
