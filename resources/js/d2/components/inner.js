/**
 * Inner-page kit Alpine components (resources/views/components/d2/inner/*, designs/d2/inner/legal.blade.php).
 *
 * d2InnerPrint   the "Print this page" button of long reading pages. Its wrapper is not drawn without
 *                JavaScript; under html.js its row is reserved from the first paint (visibility: hidden,
 *                legal.css) and this component reveals it with .is-ready (x-bind:class="readyClass"), so
 *                the button never appears where it cannot work and the head never changes height.
 *                Printing uses the page's own print stylesheet (legal.css).
 */
export default (Alpine) => {
    Alpine.data('d2InnerPrint', () => ({
        isHidden: typeof window.print !== 'function',

        get readyClass() {
            return this.isHidden ? '' : 'is-ready';
        },

        print() {
            window.print();
        },
    }));
};
