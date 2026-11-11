/**
 * d2ServicePage: a service page S01–S11 (resources/views/designs/d2/inner/service.blade.php).
 *
 * The page is complete without JavaScript: every consultation link already carries the service
 * (App\Design\D2\Prefill::url(['service' => 'S06']) → /xx/?service=S06#consultation-form), and the inner kit's
 * modules run the table of contents (modules/toc.js) and the reading progress (modules/reading-progress.js).
 * Here only the wizard contract of docs/d2/engineering.md §8: when the consultation wizard
 * (#consultation-form) is on this page, a plain click on a link carrying data-prefill-service hands the service
 * to it through prefill() (lib/prefill.js: the d2:prefill event, then a scroll to the form) instead of
 * navigating. Modified clicks and pages without the wizard keep the link's own behaviour.
 *
 * The side rail's CTA card (≥ 1280 px, #svc-cta-card) steps aside while the final consultation panel (#request)
 * is on screen, as the phone's sticky CTA bar does (its `until`): the panel repeats the card's approved heading
 * and both actions, so one viewport never shows the same CTA twice beside the header's (QA round 1). The card
 * fades out (.is-away: opacity, then visibility, so it also leaves the tab order and the accessibility tree
 * until the panel leaves the screen); IntersectionObserver only, the strip under the sticky header excluded.
 */
import { prefill } from '../lib/prefill.js';
import { serviceCode } from './service-filter.js';

/** Hide the rail card while the final panel is on screen; returns a cleanup. */
function stepAside(root) {
    const card = root.querySelector('#svc-cta-card');
    const panel = root.querySelector('#request');
    if (!card || !panel || typeof IntersectionObserver !== 'function') return () => {};
    let io = null;
    let top = -1;
    const build = () => {
        const header = document.querySelector('[data-d2-header]');
        const next = header ? Math.round(header.getBoundingClientRect().height) : 0;
        if (io && next === top) return;
        top = next;
        io?.disconnect();
        io = new IntersectionObserver((entries) => {
            for (const entry of entries) card.classList.toggle('is-away', entry.isIntersecting);
        }, { rootMargin: `${-top}px 0px 0px 0px` });
        io.observe(panel);
    };
    build();
    let timer = 0;
    const onResize = () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(build, 120);
    };
    window.addEventListener('resize', onResize, { passive: true });
    return () => {
        window.clearTimeout(timer);
        window.removeEventListener('resize', onResize);
        io?.disconnect();
        card.classList.remove('is-away');
    };
}

export default (Alpine) => {
    Alpine.data('d2ServicePage', () => {
        let off = null;
        let aside = null;

        return {
            init() {
                const root = this.$root;
                aside = stepAside(root);
                const onClick = (event) => {
                    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                    const link = event.target instanceof Element ? event.target.closest('a[data-prefill-service]') : null;
                    if (!link || !document.getElementById('consultation-form')) return;
                    const service = serviceCode(link.dataset.prefillService);
                    if (!service) return;
                    event.preventDefault();
                    prefill({ service });
                };
                root.addEventListener('click', onClick);
                off = () => root.removeEventListener('click', onClick);
            },

            destroy() {
                off?.();
                off = null;
                aside?.();
                aside = null;
            },
        };
    });
};
