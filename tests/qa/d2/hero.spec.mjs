/**
 * Design 2 hero frames (upgrade spec §12 Phase B acceptance and G1, checklist §9.8-9.9): the home page in
 * EN / AR / RU at 1440, 1280, 768 and 390, with motion and with prefers-reduced-motion, as viewport frames at
 * 0, 600, 1200, 2000 and 3000 ms into qa/shots/<date>/hero/ (one PNG per frame, one filmstrip, one JSON).
 *
 * Frames come from a DevTools screencast that runs from before navigation, so each frame is what was on
 * screen at that moment (screenshots taken on a timer would stall and drift): frame "0" is the first frame
 * painted after first contentful paint; frame t is the last frame painted at or before t. The clock starts
 * at the first contentful paint, as the storyboard's times do (spec §4.3, "times from first paint"; the deal
 * itself runs on that clock, hero/stage.js), or (phones, where the stage sits below the fold and deals once it
 * scrolls into view) at the moment the harness scrolls the stage into view. The JSON lists each frame's
 * actual time; the time from navigation to the first paint (server response included) is recorded as
 * firstPaintGap, a warning above the 2.0 s LCP budget.
 *
 * Checks (soft assertions): no console errors · the H1 is visible from the first frame and never animated ·
 * LCP is the H1 · CLS <= 0.02 · D15: every sheet of the pile behind the top one shows no ink at rest · motion:
 * every document of the pile rests and is visible by 2000 ms, three callouts show by 3000 ms, documents are dealt
 * in from the inline-end side mirrored in RTL (from the right in LTR, from the left in RTL) · reduced motion: the
 * first frame already shows the final composition and the stage is pixel-identical in the first painted frame and
 * at 3000 ms (no autoplay under reduced motion).
 * The owner's autoplay: the first automatic switch comes a dwell (HeroStage::DWELL, 2.8 s) after the top document's
 * callouts are drawn (about 2.3 s after the clock's origin), so every sample up to 3000 ms still shows the composed
 * pile with the first document on top; the harness's pointer stays off the stage (no pointer depth).
 */
import { test } from '@playwright/test';
import sharp from 'sharp';
import { newContext, untilPageTime, watchPage } from '../../../qa/lib/browser.mjs';
import { ACTIVE_LOCALES, HERO_MOTIONS, HERO_TIMES, HERO_WIDTHS, LOCALES, RUN, VIEWPORTS, heroName, pageUrl, runDir } from '../../../qa/lib/config.mjs';
import { compareImages } from '../../../qa/lib/pixels.mjs';
import { heroProbe, script, stageShare } from '../../../qa/lib/runtime.mjs';
import { filmstrip, writeJson, writePng } from '../../../qa/lib/shots.mjs';
import { settle } from '../../../qa/lib/verdict.mjs';

const LAST = Math.max(...HERO_TIMES);
const CLS_MAX = 0.02;
const REST_PX = 1; // a document "rests" when its centre is within 1 px of its final position

/** A frame where every pixel has the same colour (nothing painted yet). */
async function uniform(buffer) {
    const { channels } = await sharp(buffer).stats();
    return channels.every((c) => c.min === c.max);
}

/**
 * Index of the first frame that shows the page: from navigation, the first non-blank frame (the image
 * itself decides, so DevTools and page clocks never need to agree); from a scroll, the first frame after it.
 * A frame at another size than the last one (the screencast sometimes sends its first frames of an emulated
 * phone at a transient size, e.g. 350×757 for 390×844) is skipped: its crops would never line up.
 */
async function firstShown(frames, { origin, series }) {
    const last = frames.length ? await sharp(frames[frames.length - 1].buffer).metadata() : null;
    for (const [i, f] of frames.entries()) {
        if (f.t < (series === 'nav' ? 0 : origin)) continue;
        if (last) {
            const m = await sharp(f.buffer).metadata();
            if (m.width !== last.width || m.height !== last.height) continue;
        }
        if (series !== 'nav' || !(await uniform(f.buffer))) return i;
    }
    return -1;
}

/**
 * The frame on screen at each target time: the last frame painted at or before it (the screencast only
 * sends a frame when the screen changes). A target before the first paint gets the first painted frame,
 * flagged notPaintedYet.
 */
function pickFrames(frames, times, { origin, firstIndex }) {
    const usable = firstIndex < 0 ? [] : frames.slice(firstIndex);
    return times.map((t) => {
        let pick = null;
        for (const f of usable) if (f.t <= origin + t + 1) pick = f;
        const early = pick === null && t > 0;
        pick ??= usable[0] ?? null;
        return { target: t, frame: pick, paintedAt: pick ? Math.round(pick.t - origin) : null, notPaintedYet: early };
    });
}

const frameLabel = (p) =>
    p.notPaintedYet
        ? `${p.target} ms · not painted yet (first paint ${p.paintedAt} ms)`
        : p.target === 0
          ? `0 ms · first paint ${p.paintedAt} ms`
          : `${p.target} ms · on screen since ${p.paintedAt} ms`;

const bySeries = (probe, series) => (probe?.samples ?? []).filter((s) => s.series === series);
const sampleAt = (samples, t) => samples.find((s) => s.target === t) ?? null;

/** Documents of `a` whose centre is within REST_PX of `b` and whose opacity matches. */
function restingDocs(a, b) {
    if (!a?.stage || !b?.stage) return null;
    const final = new Map(b.stage.docs.map((d) => [d.type, d]));
    return a.stage.docs.map((d) => {
        const f = final.get(d.type);
        const dx = f ? d.cx - f.cx : null;
        const dy = f ? d.cy - f.cy : null;
        return {
            type: d.type,
            dx,
            dy,
            opacity: d.opacity,
            rests: Boolean(f) && Math.abs(dx) <= REST_PX && Math.abs(dy) <= REST_PX && Math.abs(d.opacity - f.opacity) <= 0.02,
            visible: d.opacity >= 0.99 && d.inside,
        };
    });
}

for (const width of HERO_WIDTHS) {
    for (const locale of ACTIVE_LOCALES) {
        for (const motion of HERO_MOTIONS) {
            test(`hero-${locale}-${width}-${motion}`, async ({ browser }, testInfo) => {
                const name = heroName(locale, width, motion);
                const dir = LOCALES[locale].dir === 'rtl' ? -1 : 1;
                const record = { name, locale, width, motion, run: RUN, url: pageUrl(locale, ''), capturedAt: new Date().toISOString(), times: HERO_TIMES };
                const context = await newContext(browser, { width, locale, variant: motion === 'reduce' ? 'reduced' : 'default' });
                await context.addInitScript(heroProbe, HERO_TIMES);
                const page = await context.newPage();
                const watch = watchPage(page);
                const cdp = await context.newCDPSession(page);
                const raw = [];
                let capturing = true;
                cdp.on('Page.screencastFrame', ({ data, metadata, sessionId }) => {
                    if (capturing) raw.push({ ts: (metadata?.timestamp ?? Date.now() / 1000) * 1000, data });
                    cdp.send('Page.screencastFrameAck', { sessionId }).catch(() => {});
                });
                const vp = VIEWPORTS[width];
                const dsf = vp.deviceScaleFactor ?? 1;
                try {
                    await cdp.send('Page.startScreencast', {
                        format: 'png',
                        everyNthFrame: 1,
                        maxWidth: vp.viewport.width * dsf,
                        maxHeight: vp.viewport.height * dsf,
                    });
                    const response = await page.goto(record.url, { waitUntil: 'commit', timeout: 60_000 });
                    record.status = response?.status() ?? null;
                    await page.waitForLoadState('domcontentloaded');

                    // Phones: the stage waits below the fold; once its script owns the hero (html.hero-live,
                    // after fonts or 600 ms), scrolling it into view starts the deal.
                    let origin = 0;
                    let series = 'nav';
                    // Decide on the styled page (load = stylesheets and modules in), never on an unstyled layout.
                    await page.waitForLoadState('load');
                    if (motion === 'motion') {
                        await page.waitForFunction(() => document.documentElement.classList.contains('hero-live') || !document.documentElement.classList.contains('hero-armed'), null, { timeout: 8000 }).catch(() => {});
                    }
                    // The storyboard's clock (spec §4.3): first contentful paint, as the probe's "nav" series.
                    await page.waitForFunction(() => window.__qaHero?.result().fcp != null, null, { timeout: 8000 }).catch(() => {});
                    origin = (await page.evaluate(() => window.__qaHero?.result().fcp ?? null)) ?? 0;
                    const paintOrigin = origin;
                    await untilPageTime(page, origin + 700);
                    const share = await page.evaluate(script(stageShare));
                    record.stageShareAtLoad = share === null ? null : Math.round(share * 100) / 100;
                    // The stage script still holds the pile off-stage (html.hero-armed) once it waits for the stage to
                    // scroll into view (phones: less than a fifth of it above the tab bar), whatever share shows.
                    const waiting = motion === 'motion' && (await page.evaluate(() => document.documentElement.classList.contains('hero-armed')));
                    record.waitingForScroll = waiting;
                    if (share !== null && (share < 0.35 || waiting)) {
                        await page.waitForTimeout(150);
                        origin = await page.evaluate(() => {
                            document.querySelector('[data-hero-stage]').scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'instant' });
                            return performance.now();
                        });
                        series = 'scroll';
                        await page.evaluate(([times, o]) => window.__qaHero?.sampleAt(times, o, 'scroll'), [HERO_TIMES, origin]);
                    }
                    record.clock = series === 'nav' ? `first contentful paint (${Math.round(origin)} ms after navigation start)` : `stage scrolled into view at ${Math.round(origin)} ms`;
                    record.firstPaintAfterNavigation = Math.round(paintOrigin);
                    record.ttfb = await page.evaluate(() => Math.round(performance.getEntriesByType('navigation')[0]?.responseStart ?? 0) || null);
                    await untilPageTime(page, origin + LAST + 300);
                    // A busy main thread delays the probe's timers: wait (briefly) for every sample of the series.
                    await page
                        .waitForFunction(([s, n]) => (window.__qaHero?.result().samples ?? []).filter((x) => x.series === s).length >= n, [series, HERO_TIMES.length], { timeout: 4000 })
                        .catch(() => {});
                    capturing = false;
                    await cdp.send('Page.stopScreencast').catch(() => {});
                    const timeOrigin = await page.evaluate(() => performance.timeOrigin);
                    const probe = await page.evaluate(() => window.__qaHero?.result() ?? null);
                    watch.freeze();

                    // ---------------------------------------------------------------- frames
                    const frames = raw.map((f) => ({ t: f.ts - timeOrigin, buffer: Buffer.from(f.data, 'base64') })).sort((a, b) => a.t - b.t);
                    const firstIndex = await firstShown(frames, { origin, series });
                    const picks = pickFrames(frames, HERO_TIMES, { origin, firstIndex });
                    record.screencastFrames = frames.length;
                    record.firstPaint = firstIndex < 0 ? null : Math.round(frames[firstIndex].t);
                    record.fcp = probe?.fcp ?? null;
                    record.frames = [];
                    for (const p of picks) {
                        const file = `hero/${heroName(locale, width, motion, p.target)}.png`;
                        if (p.frame) await writePng(runDir(file), p.frame.buffer);
                        record.frames.push({ target: p.target, paintedAt: p.paintedAt, notPaintedYet: p.notPaintedYet, label: frameLabel(p), file: p.frame ? file : null });
                    }
                    const shown = picks.filter((p) => p.frame);
                    if (shown.length) {
                        await filmstrip(
                            shown.map((p) => ({ buffer: p.frame.buffer, label: `${frameLabel(p)} · ${locale.toUpperCase()} ${width} ${motion}` })),
                            runDir(`hero/${name}-strip.png`),
                        );
                        record.strip = `hero/${name}-strip.png`;
                    }

                    // ---------------------------------------------------------------- measurements
                    const samples = bySeries(probe, series);
                    const s0 = sampleAt(samples, 0) ?? samples[0] ?? null;
                    const sEnd = sampleAt(samples, LAST);
                    const s2000 = sampleAt(samples, 2000);
                    const lcp = probe?.lcp?.length ? probe.lcp[probe.lcp.length - 1] : null;
                    const cls = (probe?.shifts ?? []).filter((s) => s.t <= origin + LAST + 300).reduce((n, s) => n + s.value, 0);
                    const h1Animated = samples.some((s) => s.h1?.animations?.length);
                    record.probe = { samples, lcp: probe?.lcp ?? [], shifts: probe?.shifts ?? [] };
                    const checks = {
                        console: { errors: watch.errors.slice(0, 30), count: watch.errors.length, ok: watch.errors.length === 0 },
                        screencast: { frames: frames.length, ok: frames.length > 0 },
                        stage: { present: Boolean(sEnd?.stage), canvas: sEnd?.stage?.canvas ?? null, docs: sEnd?.stage?.docs?.length ?? 0, ok: Boolean(sEnd?.stage?.docs?.length) },
                        h1Visible: { opacity: s0?.h1?.opacity ?? null, at: s0?.t ?? null, ok: (s0?.h1?.opacity ?? 0) >= 0.99 },
                        h1NotAnimated: { animations: [...new Set(samples.flatMap((s) => s.h1?.animations ?? []))], ok: !h1Animated },
                        lcpIsH1: { element: lcp?.el ?? null, at: lcp?.t ?? null, ok: Boolean(lcp?.inH1) },
                        cls: { value: Math.round(cls * 10000) / 10000, max: CLS_MAX, ok: cls <= CLS_MAX },
                        // The gap before the storyboard's clock starts (recorded, not failing: the server response).
                        firstPaintGap: { firstPaint: record.firstPaintAfterNavigation, ttfb: record.ttfb, budget: 2000, ok: record.firstPaintAfterNavigation <= 2000, warn: true },
                        // D15: at rest, the sheets behind the top one show only their paper.
                        backSheetsBlank: (() => {
                            const back = (sEnd?.stage?.docs ?? []).filter((d) => !d.top);
                            const inked = back.filter((d) => d.ink > 0.01).map((d) => `${d.type} ink=${d.ink}`);
                            const top = (sEnd?.stage?.docs ?? []).find((d) => d.top);
                            return { back: back.length, inked, topInk: top?.ink ?? null, ok: back.length === 5 && inked.length === 0 && (top?.ink ?? 0) >= 0.99 };
                        })(),
                    };
                    if (motion === 'motion') {
                        const rest = restingDocs(s2000, sEnd);
                        const callouts = sEnd?.stage ? Math.max(sEnd.stage.labelsVisible, sEnd.stage.canvas === 'mobile' ? sEnd.stage.pinsVisible : 0) : 0;
                        // Entry side: in the first sample where documents are clearly in flight (more than 40 px or a
                        // fifth of their width from rest: spring overshoot near the end does not count), they must sit
                        // on the side they come from (+x in LTR, -x in RTL).
                        const flight = samples
                            .filter((s) => s.target > 0 && s.target < 2000)
                            .map((s) => ({ s, docs: (restingDocs(s, sEnd) ?? []).filter((d) => d.dx !== null && Math.abs(d.dx) > Math.max(40, (s.stage?.docs.find((x) => x.type === d.type)?.w ?? 0) / 5)) }))
                            .find((x) => x.docs.length);
                        const wrongSide = (flight?.docs ?? []).filter((d) => Math.sign(d.dx) !== dir).map((d) => `${d.type} dx=${Math.round(d.dx)}`);
                        checks.docsRestBy2000 = { docs: rest, ok: Boolean(rest?.length) && rest.every((d) => d.rests && d.visible) };
                        checks.calloutsBy3000 = { visible: callouts, labels: sEnd?.stage?.labelsVisible ?? 0, pins: sEnd?.stage?.pinsVisible ?? 0, ok: callouts >= 3 };
                        checks.entrySide = {
                            expected: dir === 1 ? 'from the right (LTR)' : 'from the left (RTL)',
                            sampledAt: flight?.s.target ?? null,
                            inFlight: flight?.docs.length ?? 0,
                            wrongSide,
                            ok: Boolean(flight) && wrongSide.length === 0,
                            warn: !flight,
                        };
                    } else {
                        const rest = restingDocs(s0, sEnd);
                        // From the first paint on, nothing on the stage may change: compare the first painted frame
                        // with the last (hero-perf, round 2: with the clock at first paint, 600 ms would miss a font
                        // swap right after it). Named f600 for the diff file's stable name.
                        const f600 = picks[0]?.frame;
                        const fEnd = picks.find((p) => p.target === LAST)?.frame;
                        let still = null;
                        const rect = sEnd?.stage?.rect;
                        if (f600 && fEnd && rect) {
                            // Compare the stage only: text filled in by scripts elsewhere (office hours) is not motion.
                            // Screencast frames come at CSS pixels or device pixels: scale by the frame's own width
                            // (hero-perf, round 2: a fixed device scale cropped the phones' stage to one row).
                            const crop = async (buffer) => {
                                const meta = await sharp(buffer).metadata();
                                const sc = meta.width / vp.viewport.width;
                                const left = Math.max(0, Math.min(meta.width - 1, Math.round(rect[0] * sc)));
                                const top = Math.max(0, Math.min(meta.height - 1, Math.round(rect[1] * sc)));
                                const w = Math.max(1, Math.min(meta.width - left, Math.round(rect[2] * sc)));
                                const h = Math.max(1, Math.min(meta.height - top, Math.round(rect[3] * sc)));
                                return sharp(buffer).extract({ left, top, width: w, height: h }).png().toBuffer();
                            };
                            const cmp = await compareImages(await crop(f600.buffer), await crop(fEnd.buffer), {
                                tolerance: 0,
                                diffFile: runDir(`hero/${name}-stage-600-vs-${LAST}-diff.png`),
                            });
                            still = {
                                region: 'stage',
                                framesAt: [Math.round(f600.t - origin), Math.round(fEnd.t - origin)],
                                different: cmp.different,
                                ratio: Math.round(cmp.ratio * 100000) / 100000,
                                rows: cmp.rows,
                            };
                        }
                        const callouts = s0?.stage ? Math.max(s0.stage.labelsVisible, s0.stage.pinsVisible) : 0;
                        checks.finalAtFirstFrame = { docs: rest, callouts, ok: Boolean(rest?.length) && rest.every((d) => d.rests && d.visible) && callouts >= 3 };
                        checks.noMotion = { ...(still ?? { different: null }), ok: still !== null && still.different === 0 };
                    }
                    record.checks = checks;
                } catch (error) {
                    record.error = String(error?.stack || error).split('\n').slice(0, 4).join(' | ');
                    throw error;
                } finally {
                    writeJson(runDir(`hero/${name}.json`), record);
                    await context.close();
                }

                await testInfo.attach('hero.json', { body: JSON.stringify({ ...record, probe: undefined }, null, 2), contentType: 'application/json' });
                settle(testInfo, record.checks);
            });
        }
    }
}
