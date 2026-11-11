#!/usr/bin/env node
/**
 * Design 2 spring presets (upgrade spec §6.5) → CSS linear() easings + durations for tokens.css, so
 * UI micro-interactions (chips, tabs, segmented pill, accordion, sheets) get the same physics as the
 * Motion-driven hero without shipping JavaScript. The JS presets live in resources/js/d2/motion/springs.js.
 *
 *   node scripts/d2/springs.mjs        prints the token block
 */
export const SPRINGS = {
    snappy: { stiffness: 400, damping: 30, mass: 1 },
    ui: { stiffness: 260, damping: 26, mass: 1 },
    paper: { stiffness: 170, damping: 20, mass: 1.1 },
    sheet: { stiffness: 400, damping: 40, mass: 1 },
    bar: { stiffness: 200, damping: 26, mass: 1 },
};

/** Position (0 → 1) of a damped spring released at rest, at time t seconds. */
function position({ stiffness: k, damping: c, mass: m }, t) {
    const w0 = Math.sqrt(k / m);
    const zeta = c / (2 * Math.sqrt(k * m));
    if (zeta < 1) {
        const wd = w0 * Math.sqrt(1 - zeta * zeta);
        return 1 - Math.exp(-zeta * w0 * t) * (Math.cos(wd * t) + ((zeta * w0) / wd) * Math.sin(wd * t));
    }
    if (zeta === 1) {
        return 1 - Math.exp(-w0 * t) * (1 + w0 * t);
    }
    const r1 = -w0 * (zeta - Math.sqrt(zeta * zeta - 1));
    const r2 = -w0 * (zeta + Math.sqrt(zeta * zeta - 1));
    const a = r2 / (r2 - r1);
    const b = -r1 / (r2 - r1);
    return 1 - (a * Math.exp(r1 * t) + b * Math.exp(r2 * t));
}

/** Settling time: the spring stays within 0.1 % of rest from here on. */
function settle(spring) {
    const dt = 0.001;
    let last = 0;
    for (let t = 0; t < 4; t += dt) {
        if (Math.abs(position(spring, t) - 1) > 0.001) last = t;
    }
    return Math.ceil((last + dt) * 100) / 100;
}

export function linear(spring, points = 40) {
    const duration = settle(spring);
    const values = [];
    for (let i = 0; i <= points; i++) {
        const v = i === points ? 1 : position(spring, (duration * i) / points);
        values.push(Number(v.toFixed(4)));
    }
    return { duration: Math.round(duration * 1000), easing: `linear(${values.join(', ')})` };
}

if (import.meta.url === `file://${process.argv[1]}`) {
    const lines = [];
    for (const [name, spring] of Object.entries(SPRINGS)) {
        const { duration, easing } = linear(spring);
        lines.push(`  --spring-${name}: ${easing};`, `  --spring-${name}-dur: ${duration}ms;`);
    }
    process.stdout.write(lines.join('\n') + '\n');
}
