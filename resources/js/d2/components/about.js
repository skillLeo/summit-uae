/**
 * About page team (P06 #team, resources/views/designs/d2/inner/about.blade.php): x-data="d2AboutTeam" on the
 * team section (final pass, home-mobile; the P03 guide pattern).
 *
 * - Phones (below 768 px, pages/about.css): each member shows the profile card and the first paragraph of the
 *   approved biography; the disclosure ([data-am-toggle], aria-expanded) opens and closes the rest of the
 *   biography and the areas in place (.is-open; .is-revealed plays the entrance once).
 * - Deep links: a link to a member (#team-ahmed: the head's portrait tiles, the home team cards) or to an
 *   anchor inside a profile opens it first, then lands below the sticky header with keyboard focus on the
 *   target (resources-kit.js deepLinks); a hashchange (the browser's Back) does the same.
 * From 768 px, in print and without JavaScript every profile shows in full and nothing changes.
 */
import { deepLinks } from './resources-kit.js';

export default (Alpine) => {
    Alpine.data('d2AboutTeam', () => {
        let cleanups = [];

        return {
            init() {
                const root = this.$root;
                const onToggle = (event) => {
                    const toggle = event.target instanceof Element ? event.target.closest('[data-am-toggle]') : null;
                    const member = toggle?.closest('[data-about-member]');
                    if (!member || !root.contains(member)) return;
                    this.openMember(member, !member.classList.contains('is-open'), { reveal: true });
                };
                root.addEventListener('click', onToggle);
                cleanups.push(() => root.removeEventListener('click', onToggle));

                cleanups.push(deepLinks(root, { reveal: (target) => this.revealTarget(target) }));
            },

            destroy() {
                cleanups.forEach((fn) => fn());
                cleanups = [];
            },

            /** A link to a member or into a profile: open a folded phone profile (true when the page changed). */
            revealTarget(target) {
                const member = target instanceof Element ? target.closest('[data-about-member]') : null;
                if (!member || member.classList.contains('is-open')) return false;
                const more = member.querySelector('[data-am-more]');
                const folded = !!more && getComputedStyle(more).display === 'none';
                this.openMember(member, true);
                return folded;
            },

            /** Open (or close) the rest of a profile; reveal: play the entrance (the disclosure only). */
            openMember(member, open = true, { reveal = false } = {}) {
                member.classList.toggle('is-open', open);
                member.classList.toggle('is-revealed', open && reveal);
                member.querySelector('[data-am-toggle]')?.setAttribute('aria-expanded', String(open));
            },
        };
    });
};
