// Unit tests for the Design 2 QA harness (pure parts). Run: npm run qa:d2:selftest (node --test).
import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { test } from 'node:test';
import sharp from 'sharp';
import { findBands, mergeIntervals } from './bands.mjs';
import { PAGES, VIEWPORTS, WIDTHS, captureName, heroName } from './config.mjs';
import { compareImages, compareRgba } from './pixels.mjs';
import { script } from './runtime.mjs';
import { capturedSince, failingChecks, warningChecks } from './verdict.mjs';

test('mergeIntervals joins overlapping and touching intervals and keeps the boundary labels', () => {
    const merged = mergeIntervals([
        [300, 400, 'c'],
        [0, 100, 'a'],
        [50, 120, 'b'],
        [120, 130, 'touch'],
        [900, 905, 'd'],
        [10, 5, 'invalid'],
    ]);
    assert.deepEqual(merged, [
        [0, 130, 'a', 'touch'],
        [300, 400, 'c', 'c'],
        [900, 905, 'd', 'd'],
    ]);
});

test('mergeIntervals: a contained interval does not change the run', () => {
    assert.deepEqual(mergeIntervals([[0, 500, 'big'], [100, 200, 'inner']]), [[0, 500, 'big', 'big']]);
});

test('findBands reports only gaps taller than the limit, with the content around them', () => {
    const merged = mergeIntervals([
        [0, 100, 0],
        [260, 300, 1], // 160 px gap: allowed (not taller than 160)
        [500, 600, 2], // 200 px gap: a band
    ]);
    const bands = findBands(merged, { min: 160, height: 800, labels: ['header', 'intro', 'cards'] });
    assert.deepEqual(bands, [
        { top: 300, bottom: 500, height: 200, above: 'intro', below: 'cards', edge: null },
        { top: 600, bottom: 800, height: 200, above: 'cards', below: null, edge: 'bottom' },
    ]);
});

test('findBands flags an empty top edge and ignores the page end without a height', () => {
    const bands = findBands([[400, 450, 'a', 'a']], { min: 160 });
    assert.equal(bands.length, 1);
    assert.equal(bands[0].edge, 'top');
    assert.equal(bands[0].height, 400);
});

const image = (w, h, rgba = [255, 255, 255, 255]) => ({ width: w, height: h, data: Buffer.from(Array.from({ length: w * h }, () => rgba).flat()) });

test('compareRgba: identical images have no different pixel', () => {
    const r = compareRgba(image(4, 3), image(4, 3));
    assert.equal(r.different, 0);
    assert.equal(r.sameSize, true);
    assert.equal(r.diff, null);
});

test('compareRgba counts every channel difference exactly, honours a tolerance and draws the diff', () => {
    const a = image(4, 3);
    const b = image(4, 3);
    b.data[(1 * 4 + 2) * 4 + 1] = 254; // pixel (2, 1), green channel off by one
    const exact = compareRgba(a, b, { withDiff: true });
    assert.equal(exact.different, 1);
    assert.deepEqual(exact.rows, [1, 1]);
    assert.deepEqual([...exact.diff.subarray((1 * 4 + 2) * 4, (1 * 4 + 2) * 4 + 4)], [255, 0, 200, 255]);
    assert.equal(compareRgba(a, b, { tolerance: 1 }).different, 0);
});

test('compareRgba: pixels outside the common area count as different', () => {
    const r = compareRgba(image(4, 3), image(4, 5));
    assert.equal(r.sameSize, false);
    assert.equal(r.different, 8);
    assert.equal(r.total, 20);
});

test('compareImages decodes PNGs and writes a diff image only when they differ', async () => {
    const dir = mkdtempSync(path.join(tmpdir(), 'qa-d2-'));
    try {
        const white = await sharp({ create: { width: 10, height: 10, channels: 4, background: '#ffffff' } }).png().toBuffer();
        const dot = await sharp(white).composite([{ input: { create: { width: 1, height: 1, channels: 4, background: '#000000' } }, left: 3, top: 4 }]).png().toBuffer();
        const same = await compareImages(white, white, { diffFile: path.join(dir, 'same.png') });
        assert.equal(same.different, 0);
        assert.throws(() => readFileSync(path.join(dir, 'same.png')));
        const diff = await compareImages(white, dot, { diffFile: path.join(dir, 'diff.png') });
        assert.equal(diff.different, 1);
        assert.deepEqual(diff.rows, [4, 4]);
        assert.ok(readFileSync(path.join(dir, 'diff.png')).length > 0);
    } finally {
        rmSync(dir, { recursive: true, force: true });
    }
});

test('capture and hero names match the qa/reference naming contract', () => {
    assert.equal(captureName('home', 'en', 1440), 'home-en-1440');
    assert.equal(captureName('faq', 'ar', 390, 'nojs'), 'faq-ar-390-nojs');
    assert.equal(heroName('ru', 768, 'reduce', 600), 'hero-ru-768-reduce-0600');
    assert.equal(heroName('en', 390, 'motion'), 'hero-en-390-motion');
});

test('the page list covers the spec (home, service, guide, case, FAQ, contact, 404) at four widths', () => {
    assert.deepEqual(PAGES.map((p) => p.key), ['home', 'service', 'resources', 'case-studies', 'faq', 'contact', 'calculators', '404']);
    assert.equal(PAGES.find((p) => p.key === '404').status, 404);
    assert.deepEqual(WIDTHS, [1440, 1280, 768, 390]);
    for (const w of WIDTHS) assert.equal(VIEWPORTS[w].viewport.width, w);
    assert.equal(VIEWPORTS[390].isMobile, true);
});

test('script() injects the helpers and serialises the argument', () => {
    const add = (x, y) => x + y;
    // eslint-disable-next-line no-eval
    const value = (0, eval)(script((arg, { add }) => add(arg.a, arg.b), { a: 2, b: 3 }, { add }));
    assert.equal(value, 5);
});

test('failing and warning checks: warn and info never fail the verdict', () => {
    const checks = { a: { ok: true }, b: { ok: false }, c: { ok: false, warn: true }, d: { ok: true, info: true }, e: { ok: false, info: true } };
    assert.deepEqual(failingChecks(checks).map(([id]) => id), ['b']);
    assert.deepEqual(warningChecks(checks).map(([id]) => id), ['c']);
});

test('the verdict judges only the captures written since this invocation started', () => {
    // A harness-only run (npm run qa:d2:selftest) starts after an earlier run's hero captures were written.
    const earlier = { name: 'hero-en-1440-reduce', capturedAt: '2026-11-10T07:57:01.000Z', checks: { noMotion: { ok: false } } };
    const later = { name: 'home-en-1440', capturedAt: '2026-11-10T11:44:09.500Z', checks: {} };
    assert.deepEqual(capturedSince([earlier, later, null], '2026-11-10T11:44:00.000Z'), [later]);
    assert.deepEqual(capturedSince([earlier], '2026-11-10T11:44:00.000Z'), []);
    assert.deepEqual(capturedSince([earlier, later], null), [earlier, later]);
    assert.deepEqual(capturedSince([{ name: 'no-time' }], '2026-11-10T11:44:00.000Z'), []);
});
