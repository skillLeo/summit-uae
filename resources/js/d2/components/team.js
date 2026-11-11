/**
 * Team profile cards (the home "Our Team" block #our-team, off by default, and the About page; spec §12 C8).
 *
 * d2TeamCta  the "Request a consultation with …" link on a profile card
 *            (resources/views/components/d2/team/profile.blade.php). Without JavaScript it is a plain
 *            link to the consultation form with ?consultant=team-… (App\Design\D2\Prefill::url). Here a
 *            plain click hands the consultant to the wizard instead: prefill() dispatches d2:prefill and
 *            scrolls to the form on this page, or opens the consultation URL elsewhere. Modified clicks
 *            (new tab, new window) keep the link's own behaviour.
 * On phones the team cards (like the practice areas, case studies and guides) are the swipe rail of
 * <x-d2.ui.carousel> (components/carousel.js, d2Carousel): its position dots replaced d2CardRail.
 */
import { prefill } from '../lib/prefill.js';

/** The consultant code a card's link carries (team-ahmed …), or null when it is not a valid code. */
export function consultantOf(element) {
    const code = element?.dataset?.consultant;
    return typeof code === 'string' && /^team-[a-z]+$/.test(code) ? code : null;
}

/** A click that should keep the browser's own link behaviour (other button, modifier key, handled). */
export function isModified(event) {
    return !!event && !!(event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey);
}

export default (Alpine) => {
    Alpine.data('d2TeamCta', () => ({
        request(event) {
            if (isModified(event)) return;
            const consultant = consultantOf(this.$root);
            if (!consultant) return;
            event.preventDefault();
            prefill({ consultant });
        },
    }));
};
