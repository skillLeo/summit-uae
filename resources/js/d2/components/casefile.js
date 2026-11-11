/**
 * Case Studies page (P04, resources/views/designs/d2/inner/case-studies.blade.php): x-data="d2Casefiles"
 * on the page root.
 *
 * - Deep links (designer note: "move keyboard focus to the selected story heading"): the directory cards,
 *   the "Back to the case study directory" links (#cases-top) and incoming links such as /xx/case-studies/
 *   #case-04 land below the sticky header (CSS scroll margins) with keyboard focus on the target heading,
 *   also when the same link is followed twice (resources-kit.js deepLinks).
 * - Phones (below 768 px, pages/cases.css; final pass, home-mobile, the P03 guide pattern): each story starts
 *   as its file card. Its disclosure ([data-cf-toggle], aria-expanded) opens and closes the story in place
 *   (.is-open; .is-revealed plays the entrance once); a link to the story or to any anchor inside it (a
 *   directory card, #case-04, a hashchange such as the browser's Back) opens it first (revealTarget), then
 *   lands on the target. From 768 px the whole story always shows and nothing changes.
 * - The directory's phone swipe rail is <x-d2.ui.carousel> (components/carousel.js: position dots).
 * Without JavaScript every story, card and anchor works as plain HTML.
 */
import { deepLinks } from './resources-kit.js';

export default (Alpine) => {
    Alpine.data('d2Casefiles', () => {
        let cleanups = [];

        return {
            init() {
                const root = this.$root;
                // Phone disclosures: one delegated listener for the six stories.
                const onToggle = (event) => {
                    const toggle = event.target instanceof Element ? event.target.closest('[data-cf-toggle]') : null;
                    const file = toggle?.closest('[data-case-file]');
                    if (!file || !root.contains(file)) return;
                    this.openFile(file, !file.classList.contains('is-open'), { reveal: true });
                };
                root.addEventListener('click', onToggle);
                cleanups.push(() => root.removeEventListener('click', onToggle));

                cleanups.push(deepLinks(root, { reveal: (target) => this.revealTarget(target) }));
            },

            destroy() {
                cleanups.forEach((fn) => fn());
                cleanups = [];
            },

            /** A link to a story or into it: open a story shown as its phone card (true when the page changed). */
            revealTarget(target) {
                const file = target instanceof Element ? target.closest('[data-case-file]') : null;
                if (!file || file.classList.contains('is-open')) return false;
                const body = file.querySelector('[data-cf-body]');
                const folded = !!body && body.getClientRects().length === 0;
                this.openFile(file, true);
                return folded;
            },

            /** Open (or close) a story under its card; reveal: play the entrance (the disclosure only). */
            openFile(file, open = true, { reveal = false } = {}) {
                file.classList.toggle('is-open', open);
                file.classList.toggle('is-revealed', open && reveal);
                file.querySelector('[data-cf-toggle]')?.setAttribute('aria-expanded', String(open));
            },
        };
    });
};
