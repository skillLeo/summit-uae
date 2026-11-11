/**
 * d2ToolRent: <x-d2.tool.rent> (resources/views/components/d2/tool/rent.blade.php). The check's page
 * script (resources/js/d2/tools/rent-view.js, with the pure bands in rent.js) is a lazy chunk, loaded
 * when the tool's section nears the viewport or the tool is focused.
 */
import { whenNear } from '../tools/lazy.js';

export default (Alpine) => {
    Alpine.data('d2ToolRent', () => ({
        init() {
            whenNear(this.$root, () => import('../tools/rent-view.js'));
        },
    }));
};
