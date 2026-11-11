// Section 04 method: pinned on desktop with the active step highlighted by a CSS scroll-driven
// animation. Browsers without scroll timelines get the GSAP ScrollTrigger fallback (loaded only
// there). Static on mobile and under reduced motion.
import { mq } from '../lib/util';

let fallback = null;

async function startFallback(method) {
    const track = method.querySelector('.method__track');
    const steps = [...method.querySelectorAll('[data-method-step]')];
    const [{ gsap }, { ScrollTrigger }] = await Promise.all([import('gsap'), import('gsap/ScrollTrigger')]);
    gsap.registerPlugin(ScrollTrigger);
    fallback = ScrollTrigger.create({
        trigger: track,
        start: 'top top',
        end: 'bottom bottom',
        scrub: true,
        onUpdate: (self) => {
            method.style.setProperty('--method-progress', self.progress.toFixed(4));
            steps.forEach((step, i) => step.classList.toggle('is-reached', self.progress >= i / steps.length));
        },
    });
}

export function initMethod() {
    const method = document.querySelector('[data-method]');
    if (!method) return;
    const query = mq('(min-width: 1024px) and (prefers-reduced-motion: no-preference)');
    const supportsTimeline = window.CSS?.supports?.('animation-timeline: view()') ?? false;

    const apply = () => {
        if (query.matches && !supportsTimeline && !fallback) {
            startFallback(method);
        } else if (!query.matches && fallback) {
            fallback.kill();
            fallback = null;
            method.style.removeProperty('--method-progress');
        }
    };
    apply();
    query.addEventListener('change', apply);
}
