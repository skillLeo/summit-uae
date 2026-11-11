/**
 * d2Wizard: the consultation wizard (<x-d2.wizard.form>, resources/views/components/d2/wizard/form.blade.php).
 *
 * The markup is a complete plain form that posts to /{locale}/enquiries without JavaScript. This component is
 * the eager part only (wizard/lazy.js): it loads wizard/controller.js, which turns the form into five steps,
 * when the wizard is about to be used, queues what the visitor does meanwhile, and puts the plain form back
 * if the controller cannot start, so the visitor can always send the request.
 */
import { lazyWizard } from '../wizard/lazy.js';

export default (Alpine) => {
    Alpine.data('d2Wizard', () => {
        let handle = null;

        return {
            init() {
                handle = lazyWizard(this.$root);
            },

            destroy() {
                handle?.destroy();
                handle = null;
            },
        };
    });
};
