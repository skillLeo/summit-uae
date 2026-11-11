// Hero "The File": seven slips settle once (per session) from scattered positions onto the
// gold spine; the spine draws, then the four step ticks fade in. WAAPI only, transforms and
// opacity only. The final frame is plain CSS, so reduced motion / no JS / a repeat visit
// simply show it.
import { isRtl, mq, reducedMotion } from '../lib/util';

const EASE_OUT = 'cubic-bezier(0.22, 1, 0.36, 1)';
const IN_OUT = 'cubic-bezier(0.65, 0, 0.35, 1)';

// Small deterministic PRNG so the scatter looks composed rather than noisy.
function prng(seed) {
    let a = seed >>> 0;
    return () => {
        a = (a + 0x6d2b79f5) >>> 0;
        let t = a;
        t = Math.imul(t ^ (t >>> 15), t | 1);
        t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

function intro(file) {
    const slips = [...file.querySelectorAll('[data-slip]')];
    const spine = file.querySelector('[data-file-spine]');
    const ticks = [...file.querySelectorAll('[data-file-tick]')];
    const vertical = mq('(min-width: 1024px)').matches;
    const dir = isRtl() ? -1 : 1;
    const rand = prng(20261023);
    const scale = vertical ? 1 : 0.6; // the band is shallower on phones and tablets

    slips.forEach((el, i) => {
        const sx = rand() > 0.5 ? 1 : -1;
        const sy = rand() > 0.5 ? 1 : -1;
        const dx = (40 + rand() * 80) * sx * dir * scale;  // ±40–120px
        const dy = (40 + rand() * 80) * sy * scale;
        const r = -9 + rand() * 16;                       // −9° … +7°
        el.animate(
            [
                { opacity: 0, transform: `translate(${dx.toFixed(1)}px, ${dy.toFixed(1)}px) rotate(${r.toFixed(2)}deg)` },
                { opacity: 1, transform: 'none' },
            ],
            { duration: 780, delay: 120 + i * 60, easing: EASE_OUT, fill: 'backwards' },
        );
    });

    if (spine) {
        spine.animate(
            [{ transform: vertical ? 'scaleY(0)' : 'scaleX(0)' }, { transform: 'none' }],
            { duration: 400, delay: 700, easing: IN_OUT, fill: 'backwards' },
        );
    }

    ticks.forEach((el, i) => {
        el.animate(
            [{ opacity: 0, transform: 'translateY(4px)' }, { opacity: 1, transform: 'none' }],
            { duration: 180, delay: 1000 + i * 90, easing: EASE_OUT, fill: 'backwards' },
        );
    });
}

function nudge(file) {
    const slips = [...file.querySelectorAll('[data-slip]')];
    let frame = 0;
    let near = null;
    file.addEventListener('pointermove', (e) => {
        if (frame) return;
        frame = requestAnimationFrame(() => {
            frame = 0;
            let best = null;
            let bestD = Infinity;
            for (const s of slips) {
                const r = s.getBoundingClientRect();
                const d = Math.hypot(e.clientX - (r.left + r.width / 2), e.clientY - (r.top + Math.min(r.height / 2, 32)));
                if (d < bestD) {
                    bestD = d;
                    best = s;
                }
            }
            if (best !== near) {
                near?.classList.remove('is-near');
                best?.classList.add('is-near');
                near = best;
            }
        });
    });
    file.addEventListener('pointerleave', () => {
        near?.classList.remove('is-near');
        near = null;
    });
}

export function initHero() {
    const root = document.documentElement;
    const file = document.querySelector('[data-file]');
    if (!file) {
        root.classList.remove('hero-intro');
        return;
    }
    if (root.classList.contains('hero-intro') && !reducedMotion() && typeof file.animate === 'function') {
        intro(file);
        try {
            window.sessionStorage.setItem('summit.intro', '1');
        } catch {
            // Private mode: the intro may replay; harmless.
        }
    }
    root.classList.remove('hero-intro');

    if (!reducedMotion() && mq('(pointer: fine) and (min-width: 1024px)').matches) nudge(file);
}
