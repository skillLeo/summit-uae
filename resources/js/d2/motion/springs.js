/**
 * Spring presets (upgrade spec §6.5) for Motion (motion.dev). The same physics are compiled to CSS
 * linear() easings in tokens.css (--spring-*) by scripts/d2/springs.mjs; keep both in sync.
 */
export const springs = {
    snappy: { type: 'spring', stiffness: 400, damping: 30, mass: 1 },
    ui: { type: 'spring', stiffness: 260, damping: 26, mass: 1 },
    paper: { type: 'spring', stiffness: 170, damping: 20, mass: 1.1 },
    sheet: { type: 'spring', stiffness: 400, damping: 40, mass: 1 },
    bar: { type: 'spring', stiffness: 200, damping: 26, mass: 1 },
    parallax: { type: 'spring', stiffness: 120, damping: 20, mass: 1 },
};
