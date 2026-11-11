/**
 * Self-test of the Design 2 harness's in-page checks (project "harness", no site needed): a synthetic page
 * with one of each violation must be caught by measurePage + findBands, and exempt content must not be.
 * Guards against checks that silently pass (a capture that "passes everything" must mean something).
 */
import { expect, test } from '@playwright/test';
import { findBands, mergeIntervals } from '../../../qa/lib/bands.mjs';
import { describeEl, measurePage, script } from '../../../qa/lib/runtime.mjs';

const PAGE = `<!doctype html><html lang="en" dir="ltr"><head><meta charset="utf-8"><style>
body{margin:0;font:16px/1.5 sans-serif;background:#F7F6F3}
.wide{width:600px;height:20px;background:#ddd}
.small{font-size:12px}
.gap{height:400px}
.sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%)}
.tiny{font-size:10px}
.doc{width:200px;height:60px;background:#fff}
.doc .fine{font-size:9px}
.doc__wm-line::before{content:attr(data-wm)}
a.u{text-decoration:underline}
.prose a{text-decoration:underline}
.card{width:300px;height:250px;background:#fff;border:1px solid #ccc}
</style></head><body>
<h1>Title</h1><h1>Second title</h1>
<p id="dup">one</p><p id="dup">two</p>
<div class="wide"></div>
<p class="small">tiny text</p>
<div class="gap"></div>
<p>after the gap</p>
<span class="sr-only">hidden <span class="tiny">x</span></span>
<div class="doc" data-doc="cheque"><span class="fine">fine print</span><span class="doc__wm"></span></div>
<div class="doc" data-doc="memo"><span class="fine">fine print</span><span class="doc__wm"><span class="doc__wm-line" data-wm="SAMPLE · ILLUSTRATION"></span></span></div>
<nav><a class="u" href="#a">underlined nav link</a></nav>
<p>Read <a class="u" href="#b">a link in a sentence</a> here.</p>
<div class="prose"><a href="#c">prose link</a></div>
<p>This law firm answers 24/7.</p>
<p>⟦missing.key⟧</p>
<h4>skipped level</h4>
<div class="card"></div>
</body></html>`;

test('measurePage and findBands catch every seeded violation and respect the exemptions', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.setContent(PAGE);
    const m = await page.evaluate(
        script(
            measurePage,
            { viewportWidth: 390, minText: 14, exempt: '.doc, [data-doc]', banned: [{ id: 'law firm', source: 'law\\s+firm' }, { id: '24/7', source: '24\\s*/\\s*7' }], marker: '⟦' },
            { describeEl, mergeIntervals },
        ),
    );
    const bands = findBands(m.content.intervals, { min: 160, height: m.size.height, labels: m.content.labels });

    expect(m.overflow.px).toBe(210);
    expect(m.overflow.culprits[0].el).toContain('div.wide');
    expect(m.smallText.count).toBe(1); // .small only: sr-only text and sample-document fine print are exempt
    expect(m.smallText.samples[0].text).toBe('tiny text');
    expect(m.h1.count).toBe(2);
    expect(m.duplicateIds).toEqual([{ id: 'dup', n: 2 }]);
    expect(bands).toHaveLength(1);
    expect(bands[0].height).toBeGreaterThanOrEqual(400);
    expect(bands[0].above).toContain('tiny text');
    expect(m.docs.count).toBe(2);
    expect(m.docs.withoutWatermark).toHaveLength(1); // the ::before data-wm watermark counts
    expect(m.docs.withoutWatermark[0]).toContain('doc');
    expect(m.underlinedLinks).toHaveLength(1); // the nav link; sentence and .prose links are running text
    expect(m.banned.map((b) => b.id).sort()).toEqual(['24/7', 'law firm']);
    expect(m.missingCopy.length).toBeGreaterThan(0);
    expect(m.headings.skipCount).toBe(1);
    expect(m.headings.skips[0]).toMatchObject({ from: 'h1', to: 'h4' });
});

test('a clean page passes every check', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.setContent('<!doctype html><html lang="ar" dir="rtl"><body style="margin:0;font:16px sans-serif"><h1>عنوان</h1><p>نص</p><h2>قسم</h2><p>نص آخر</p></body></html>');
    const m = await page.evaluate(script(measurePage, { viewportWidth: 1440, minText: 14, exempt: '.doc', banned: [], marker: '⟦' }, { describeEl, mergeIntervals }));
    expect(m.html).toMatchObject({ lang: 'ar', dir: 'rtl' });
    expect(m.overflow.px).toBe(0);
    expect(m.smallText.count).toBe(0);
    expect(m.h1).toMatchObject({ count: 1, visible: 1 });
    expect(m.duplicateIds).toEqual([]);
    expect(m.headings.skipCount).toBe(0);
    expect(findBands(m.content.intervals, { min: 160, labels: m.content.labels })).toEqual([]);
});
