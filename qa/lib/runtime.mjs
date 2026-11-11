/**
 * In-page QA functions for the Design 2 harness. Each one is serialised with Function.prototype.toString()
 * and evaluated through the DevTools protocol (`page.evaluate(script(...))`), which the page's CSP does not
 * restrict. They must therefore be self-contained: no imports and no module-scope references; the helpers
 * a function needs are injected by `script()` and arrive as its second argument.
 */

/** Builds an evaluable expression: `fn(arg, deps)` with `deps` = the named helper functions. */
export function script(fn, arg = null, deps = {}) {
    const names = Object.keys(deps);
    const head = names.map((name) => `const ${name} = ${deps[name].toString()};`).join('\n');
    return `(() => {\n${head}\nreturn (${fn.toString()})(${JSON.stringify(arg)}, { ${names.join(', ')} });\n})()`;
}

/** Short readable label for an element: tag#id.class "text" in <section>. */
export function describeEl(el) {
    if (!el || el.nodeType !== 1) return String((el && el.nodeName) || el);
    const name = (e) => {
        const tag = e.tagName.toLowerCase();
        const id = e.id ? `#${e.id}` : '';
        const cls = (e.getAttribute('class') || '').trim().split(/\s+/).filter(Boolean).slice(0, 2).map((c) => `.${c}`).join('');
        return `${tag}${id}${cls}`;
    };
    const raw = el.getAttribute('aria-label') || (el.childElementCount < 40 ? el.textContent : '') || '';
    const text = raw.replace(/\s+/g, ' ').trim().slice(0, 48);
    const section = el.parentElement ? el.parentElement.closest('section, header, footer, aside, nav, dialog, [data-sheet]') : null;
    return `${name(el)}${text ? ` "${text}"` : ''}${section ? ` in ${name(section)}` : ''}`;
}

/**
 * Everything measured on a settled page (scrolled to the top): document facts, horizontal overflow,
 * text below the minimum size, headings, duplicate ids, the content intervals for empty bands, sample
 * documents and their watermark, banned claims, missing-copy markers, underlined links and proposed copy.
 * opts: { viewportWidth, minText, exempt (selector), banned: [{ id, source }], marker }.
 */
export function measurePage(opts, { describeEl, mergeIntervals }) {
    const html = document.documentElement;
    const body = document.body;
    const vw = opts.viewportWidth;
    const sy = window.scrollY;
    const out = {
        url: location.href,
        title: document.title,
        html: {
            lang: html.getAttribute('lang'),
            dir: html.getAttribute('dir'),
            design: html.dataset.design ?? null,
            page: html.dataset.page ?? null,
            classes: html.className,
        },
        size: {
            innerWidth: window.innerWidth,
            innerHeight: window.innerHeight,
            scrollWidth: html.scrollWidth,
            height: Math.max(html.scrollHeight, body ? body.scrollHeight : 0),
        },
    };
    if (!body) return out;

    const alpha = (c) => {
        if (!c || c === 'transparent' || c === 'none') return 0;
        let m = c.match(/\/\s*([\d.]+)(%?)\s*\)\s*$/);
        if (m) return parseFloat(m[1]) / (m[2] ? 100 : 1);
        m = c.match(/^rgba\(([^)]+)\)$/i);
        if (m) {
            const parts = m[1].split(',').map((s) => s.trim());
            if (parts.length === 4) return parseFloat(parts[3]);
        }
        return 1;
    };
    const shown = (el) =>
        typeof el.checkVisibility === 'function'
            ? el.checkVisibility({ opacityProperty: true, visibilityProperty: true, contentVisibilityAuto: true })
            : el.getClientRects().length > 0;

    // ------------------------------------------------------------ one pass over every element
    const all = body.querySelectorAll('*');
    const fixedRoots = [];
    const hiddenRoots = [];
    const clippers = new Map();
    const candidates = [];
    const REPLACED = new Set(['img', 'svg', 'video', 'canvas', 'iframe', 'object', 'embed', 'input', 'select', 'textarea', 'button', 'progress', 'meter', 'hr']);
    for (const el of all) {
        const cs = getComputedStyle(el);
        if (cs.position === 'fixed') fixedRoots.push(el);
        if (cs.clipPath.includes('inset(50%)') || /^rect\(0(px)?,?\s*0(px)?/.test(cs.clip)) hiddenRoots.push(el);
        if (cs.overflowY !== 'visible' && cs.display !== 'contents') clippers.set(el, null);
        const tag = el.tagName.toLowerCase();
        const insideSvg = el.parentElement && el.parentElement.closest('svg');
        if (insideSvg) continue;
        const replaced = REPLACED.has(tag) && !(tag === 'input' && el.type === 'hidden');
        const roleImg = el.getAttribute('role') === 'img';
        const bg = alpha(cs.backgroundColor) > 0;
        const bgImage = cs.backgroundImage && cs.backgroundImage !== 'none';
        const border = ['Top', 'Right', 'Bottom', 'Left'].some(
            (s) => parseFloat(cs[`border${s}Width`]) > 0 && !['none', 'hidden'].includes(cs[`border${s}Style`]) && alpha(cs[`border${s}Color`]) > 0,
        );
        const shadow = Boolean(cs.boxShadow) && cs.boxShadow !== 'none';
        if (!replaced && !roleImg && !bg && !bgImage && !border && !shadow) continue;
        candidates.push({ el, replaced: replaced || roleImg, bgColor: cs.backgroundColor, bg, bgImage, border, shadow });
    }
    const within = (roots, el) => roots.some((r) => r === el || r.contains(el));
    const clipRect = (el) => {
        let r = clippers.get(el);
        if (r === null) {
            r = el.getBoundingClientRect();
            clippers.set(el, r);
        }
        return r;
    };
    /** The vertical extent of a client rect after the nearest vertical clippers (null when fully clipped). */
    const clipY = (el, top, bottom) => {
        for (let a = el.parentElement; a && a !== body && a !== html; a = a.parentElement) {
            if (!clippers.has(a)) continue;
            const r = clipRect(a);
            top = Math.max(top, r.top);
            bottom = Math.min(bottom, r.bottom);
            if (bottom - top < 0.5) return null;
        }
        return [top, bottom];
    };

    // ------------------------------------------------------------ horizontal overflow
    const overflowPx = Math.max(0, html.scrollWidth - vw, window.innerWidth - vw);
    const culprits = [];
    if (overflowPx > 0) {
        const listed = [];
        for (const el of all) {
            if (listed.some((l) => l.contains(el))) continue;
            const r = el.getBoundingClientRect();
            if (r.width <= 1 || r.height <= 1) continue;
            if (r.right <= vw + 0.5 && r.left >= -0.5) continue;
            let clipped = false;
            for (let a = el.parentElement; a && a !== body; a = a.parentElement) {
                if (getComputedStyle(a).overflowX !== 'visible') {
                    clipped = true;
                    break;
                }
            }
            if (clipped || within(hiddenRoots, el)) continue;
            listed.push(el);
            culprits.push({ el: describeEl(el), left: Math.round(r.left), right: Math.round(r.right), width: Math.round(r.width) });
            if (culprits.length >= 12) break;
        }
    }
    out.overflow = { px: overflowPx, culprits };

    // ------------------------------------------------------------ text: small sizes + band intervals
    const els = [];
    const indexOf = new Map();
    const ref = (el) => {
        let i = indexOf.get(el);
        if (i === undefined) {
            i = els.push(el) - 1;
            indexOf.set(el, i);
        }
        return i;
    };
    const intervals = [];
    const small = [];
    let smallCount = 0;
    const checkedSize = new Set();
    const range = document.createRange();
    const walker = document.createTreeWalker(body, NodeFilter.SHOW_TEXT);
    while (walker.nextNode()) {
        const node = walker.currentNode;
        if (!/\S/.test(node.data)) continue;
        const el = node.parentElement;
        if (!el || el.closest('script, style, noscript, template, option')) continue;
        if (!shown(el) || within(hiddenRoots, el)) continue;
        range.selectNodeContents(node);
        const rects = [...range.getClientRects()].filter((r) => r.width > 0.5 && r.height > 0.5);
        if (!rects.length) continue;
        if (!checkedSize.has(el)) {
            checkedSize.add(el);
            if (!el.closest(opts.exempt)) {
                let size = parseFloat(getComputedStyle(el).fontSize);
                if (el.closest('svg') && typeof el.getScreenCTM === 'function') {
                    const m = el.getScreenCTM();
                    if (m) size *= Math.hypot(m.a, m.b);
                }
                if (size < opts.minText - 0.05) {
                    smallCount += 1;
                    if (small.length < 25) small.push({ size: Math.round(size * 10) / 10, text: node.data.replace(/\s+/g, ' ').trim().slice(0, 60), el: describeEl(el) });
                }
            }
        }
        if (within(fixedRoots, el)) continue;
        for (const r of rects) {
            const y = clipY(el, r.top, r.bottom);
            if (y) intervals.push([y[0] + sy, y[1] + sy, ref(el)]);
        }
    }
    out.smallText = { min: opts.minText, count: smallCount, samples: small };

    // ------------------------------------------------------------ boxes: replaced elements, cards, surfaces
    const effectiveBg = (el) => {
        for (let a = el.parentElement; a; a = a.parentElement) {
            const c = getComputedStyle(a).backgroundColor;
            if (alpha(c) > 0) return c;
        }
        return 'rgb(255, 255, 255)';
    };
    for (const c of candidates) {
        const { el } = c;
        if (within(fixedRoots, el) || within(hiddenRoots, el) || !shown(el)) continue;
        const r = el.getBoundingClientRect();
        if (r.width < 1 || r.height < 1) continue;
        const full = r.width >= vw * 0.9;
        if (c.replaced || !full) {
            const y = clipY(el, r.top, r.bottom);
            if (y) intervals.push([y[0] + sy, y[1] + sy, ref(el)]);
            continue;
        }
        // A full-width surface is page background: only its edges are content, and only when they show.
        const differs = c.border || c.shadow || c.bgImage || (c.bg && c.bgColor !== effectiveBg(el));
        if (!differs) continue;
        const y = clipY(el, r.top, r.bottom);
        if (!y) continue;
        intervals.push([y[0] + sy, y[0] + sy + 1, ref(el)]);
        intervals.push([y[1] + sy - 1, y[1] + sy, ref(el)]);
    }
    const merged = mergeIntervals(intervals);
    const labels = {};
    for (const m of merged) {
        for (const i of [m[2], m[3]]) if (i !== null && !(i in labels)) labels[i] = describeEl(els[i]);
    }
    out.content = { intervals: merged.map((m) => [Math.round(m[0]), Math.round(m[1]), m[2], m[3]]), labels };

    // ------------------------------------------------------------ headings and ids
    const h1s = [...document.querySelectorAll('h1')];
    out.h1 = {
        count: h1s.length,
        visible: h1s.filter((h) => shown(h) && h.getClientRects().length > 0).length,
        texts: h1s.map((h) => h.textContent.replace(/\s+/g, ' ').trim().slice(0, 90)),
    };
    const headings = [...document.querySelectorAll('h1, h2, h3, h4, h5, h6')].filter((h) => !h.closest('template, [hidden], dialog:not([open])'));
    const skips = [];
    let prev = 0;
    for (const h of headings) {
        const level = Number(h.tagName[1]);
        if (prev && level > prev + 1) skips.push({ from: `h${prev}`, to: `h${level}`, el: describeEl(h) });
        prev = level;
    }
    out.headings = { count: headings.length, first: headings[0] ? headings[0].tagName.toLowerCase() : null, skips: skips.slice(0, 12), skipCount: skips.length };
    const ids = new Map();
    for (const el of document.querySelectorAll('[id]')) {
        if (el.id) ids.set(el.id, (ids.get(el.id) || 0) + 1);
    }
    out.duplicateIds = [...ids].filter(([, n]) => n > 1).map(([id, n]) => ({ id, n }));

    // ------------------------------------------------------------ sample documents (hard rule 7)
    // The watermark text is either the element's text or drawn by ::before from data-wm on its lines.
    const watermarkText = (wm) => {
        const own = wm.textContent.trim();
        if (own) return own;
        return [...wm.querySelectorAll('[data-wm]')]
            .filter((line) => shown(line) && !['none', 'normal'].includes(getComputedStyle(line, '::before').content))
            .map((line) => line.getAttribute('data-wm').trim())
            .join(' ')
            .trim();
    };
    const docs = [...document.querySelectorAll('.doc[data-doc]')].filter((d) => shown(d) && d.getClientRects().length > 0);
    out.docs = {
        count: docs.length,
        withoutWatermark: docs
            .filter((d) => {
                const wm = d.querySelector('.doc__wm');
                return !wm || !shown(wm) || !watermarkText(wm);
            })
            .map((d) => describeEl(d))
            .slice(0, 12),
    };

    // ------------------------------------------------------------ copy: banned claims, missing markers, proposed
    const text = body.innerText || '';
    out.banned = [];
    for (const { id, source } of opts.banned) {
        const re = new RegExp(source, 'giu');
        let m;
        while ((m = re.exec(text)) && out.banned.length < 20) {
            out.banned.push({ id, context: text.slice(Math.max(0, m.index - 30), m.index + m[0].length + 30).replace(/\s+/g, ' ').trim() });
        }
    }
    const marker = opts.marker;
    const missing = [];
    if (text.includes(marker)) {
        const re = new RegExp(`${marker}[^\\n]{0,60}`, 'gu');
        for (const m of text.match(re) || []) if (missing.length < 12) missing.push(`text: ${m}`);
    }
    for (const el of document.querySelectorAll('[aria-label], [alt], [title], [placeholder], meta[content]')) {
        for (const attr of ['aria-label', 'alt', 'title', 'placeholder', 'content']) {
            const v = el.getAttribute(attr);
            if (v && v.includes(marker) && missing.length < 12) missing.push(`${attr}: ${v.slice(0, 80)}`);
        }
    }
    if (document.title.includes(marker)) missing.push(`title: ${document.title}`);
    out.missingCopy = missing;
    out.proposed = document.querySelectorAll('[data-copy-status="proposed"]').length;

    // ------------------------------------------------------------ §9.7 no underlined links outside running text
    out.underlinedLinks = [...document.querySelectorAll('a[href]')]
        .filter((a) => shown(a) && a.getClientRects().length > 0 && !within(hiddenRoots, a))
        .filter((a) => getComputedStyle(a).textDecorationLine.includes('underline'))
        // Running text: .prose, .t-link, or a link inside a sentence (paragraph, definition, table cell, quote).
        .filter((a) => !a.closest('.prose, .t-link, p, dd, td, blockquote, figcaption'))
        .map((a) => describeEl(a))
        .slice(0, 12);
    return out;
}

/** Waits for running, finite, document-timeline animations (CSS and WAAPI) to finish; returns their count. */
export async function settleAnimations(maxMs) {
    const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
    const finite = (a) => {
        const t = a.effect && a.effect.getComputedTiming ? a.effect.getComputedTiming() : null;
        return Boolean(t) && a.timeline === document.timeline && Number.isFinite(t.endTime) && (a.playState === 'running' || a.pending);
    };
    const pending = document.getAnimations().filter(finite);
    if (pending.length) await Promise.race([Promise.all(pending.map((a) => a.finished.catch(() => null))), sleep(maxMs || 3000)]);
    return pending.length;
}

/**
 * Freezes the page for screenshots, once: finite document-timeline animations jump to their end, infinite
 * ones (pulses, spinners) are cancelled to their base state; scroll-driven animations are left alone.
 * (Playwright's animations: 'disabled' re-scans every animation whenever one starts, and segment captures
 * fire resize events that start many transitions: on long pages that costs seconds per segment.)
 */
export function freezeAnimations() {
    let finished = 0;
    let cancelled = 0;
    for (const a of document.getAnimations()) {
        if (a.timeline !== document.timeline) continue;
        const t = a.effect && a.effect.getComputedTiming ? a.effect.getComputedTiming() : null;
        if (!t) continue;
        try {
            if (Number.isFinite(t.endTime)) {
                if (a.playState !== 'finished') {
                    a.finish();
                    finished += 1;
                }
            } else {
                a.cancel();
                cancelled += 1;
            }
        } catch {
            // an animation that cannot finish (no end) stays as it is
        }
    }
    return { finished, cancelled };
}

/**
 * Waits until scripts stop writing inline styles or classes (JS-driven tweens such as GSAP or a spring
 * write `style` every frame; getAnimations() cannot see them): resolves after `quietMs` without such a
 * mutation, or after `maxMs`. Returns { mutations, waited, timedOut }.
 */
export async function settleStyles(opts) {
    const { quietMs = 400, maxMs = 4000 } = opts || {};
    return new Promise((resolve) => {
        const start = performance.now();
        let last = start;
        let mutations = 0;
        const observer = new MutationObserver((list) => {
            mutations += list.length;
            last = performance.now();
        });
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['style', 'class'], subtree: true });
        const tick = () => {
            const now = performance.now();
            if (now - last >= quietMs || now - start >= maxMs) {
                observer.disconnect();
                resolve({ mutations, waited: Math.round(now - start), timedOut: now - start >= maxMs });
            } else {
                requestAnimationFrame(tick);
            }
        };
        requestAnimationFrame(tick);
    });
}

/** Share of the hero stage inside the viewport (null without a stage). */
export function stageShare() {
    const stage = document.querySelector('[data-hero-stage]');
    if (!stage) return null;
    const r = stage.getBoundingClientRect();
    if (!r.height) return 0;
    const visible = Math.max(0, Math.min(r.bottom, window.innerHeight) - Math.max(r.top, 0));
    return visible / r.height;
}

/**
 * Hero probe (installed as an init script, before any page script): samples the hero at the storyboard
 * times (series "nav", from first contentful paint as the storyboard's clock, spec §4.3; t = 0 is sampled
 * at first contentful paint) and, on request,
 * at times from a later origin (series "scroll": phones deal when the stage scrolls into view). Also
 * records LCP candidates and layout shifts. Self-contained (serialised by Playwright).
 */
export function heroProbe(times) {
    if (window.__qaHero) return;
    const state = { samples: [], lcp: [], shifts: [], fcp: null, origin: performance.timeOrigin };
    const label = (el) => {
        if (!el || el.nodeType !== 1) return null;
        const cls = (el.getAttribute('class') || '').trim().split(/\s+/).filter(Boolean).slice(0, 2).map((c) => `.${c}`).join('');
        return `${el.tagName.toLowerCase()}${el.id ? `#${el.id}` : ''}${cls}`;
    };
    const opacity = (el) => {
        let o = 1;
        for (let a = el; a && a.nodeType === 1; a = a.parentElement) o *= parseFloat(getComputedStyle(a).opacity);
        return Math.round(o * 1000) / 1000;
    };
    const visible = (el) => el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden' && opacity(el) > 0.95;
    const sample = (series, target) => {
        const rec = { series, target, t: Math.round(performance.now()), scrollY: Math.round(window.scrollY), h1: null, stage: null };
        const h1 = document.querySelector('h1');
        if (h1) {
            const r = h1.getBoundingClientRect();
            rec.h1 = {
                opacity: opacity(h1),
                animations: h1.getAnimations({ subtree: true }).map((a) => a.animationName || a.transitionProperty || a.id || 'animation'),
                rect: [Math.round(r.x), Math.round(r.y), Math.round(r.width), Math.round(r.height)],
            };
        }
        const stage = document.querySelector('[data-hero-stage]');
        if (stage) {
            const sr = stage.getBoundingClientRect();
            // Design 2.1 Phase 2 (D15-D17): one canvas; the document shown is [data-hero-doc].is-active (the other five
            // wait in place with visibility: hidden), its paper is [data-doc-paper], its callouts .hero-callout (labels
            // from 768 px, numbered markers on phones) and its dots .hero-dot. "mobile" = the phone layout (< 768 px).
            const canvas = [...stage.querySelectorAll('[data-canvas]')].find((c) => c.getClientRects().length > 0 && getComputedStyle(c).display !== 'none');
            const s = {
                canvas: canvas ? (window.innerWidth < 768 ? 'mobile' : 'desktop') : null,
                rect: [Math.round(sr.x), Math.round(sr.y), Math.round(sr.width), Math.round(sr.height)],
                armed: document.documentElement.classList.contains('hero-armed'),
                docs: [],
                labels: 0,
                labelsVisible: 0,
                pins: 0,
                pinsVisible: 0,
            };
            if (canvas) {
                const active = canvas.querySelector('[data-hero-doc].is-active');
                for (const d of canvas.querySelectorAll('[data-hero-doc]')) {
                    if (d !== active && getComputedStyle(d).visibility === 'hidden') continue;
                    const b = d.querySelector('[data-doc-paper]') || d;
                    const r = b.getBoundingClientRect();
                    s.docs.push({
                        type: d.dataset.heroDoc,
                        top: d === active,
                        cx: Math.round((r.x + r.width / 2) * 10) / 10,
                        cy: Math.round((r.y + r.height / 2) * 10) / 10,
                        w: Math.round(r.width),
                        h: Math.round(r.height),
                        opacity: opacity(b),
                        inside: r.right > sr.left && r.left < sr.right && r.bottom > sr.top && r.top < sr.bottom,
                    });
                }
                const labels = active ? [...active.querySelectorAll('.hero-callout')] : [];
                const pins = active ? [...active.querySelectorAll('.hero-dot')] : [];
                s.labels = labels.length;
                s.labelsVisible = labels.filter(visible).length;
                s.pins = pins.length;
                s.pinsVisible = pins.filter(visible).length;
            }
            rec.stage = s;
        }
        state.samples.push(rec);
    };
    const at = (series, list, origin) => {
        for (const t of list) {
            if (series === 'nav' && t === 0) continue; // sampled at first contentful paint
            setTimeout(() => sample(series, t), Math.max(0, origin + t - performance.now()));
        }
        if (series !== 'nav' && list.includes(0)) sample(series, 0);
    };
    try {
        new PerformanceObserver((list) => {
            for (const e of list.getEntries()) {
                if (e.name === 'first-contentful-paint' && state.fcp === null) {
                    state.fcp = Math.round(e.startTime);
                    requestAnimationFrame(() => sample('nav', 0));
                    // hero-perf (final pass): the storyboard's clock starts at the first paint (spec §4.3).
                    at('nav', times, state.fcp);
                }
            }
        }).observe({ type: 'paint', buffered: true });
        new PerformanceObserver((list) => {
            for (const e of list.getEntries()) {
                state.lcp.push({ t: Math.round(e.startTime), size: e.size, el: label(e.element), inH1: Boolean(e.element && e.element.closest && e.element.closest('h1')) });
            }
        }).observe({ type: 'largest-contentful-paint', buffered: true });
        new PerformanceObserver((list) => {
            for (const e of list.getEntries()) {
                if (!e.hadRecentInput) state.shifts.push({ t: Math.round(e.startTime), value: e.value, sources: (e.sources || []).map((s) => label(s.node)).filter(Boolean).slice(0, 4) });
            }
        }).observe({ type: 'layout-shift', buffered: true });
    } catch {
        // PerformanceObserver types unsupported: timings stay empty, and the clock falls back to navigation
        at('nav', times, 0);
    }
    window.__qaHero = {
        sampleAt: (list, origin, series) => at(series, list, origin),
        result: () => JSON.parse(JSON.stringify(state)),
    };
}
