{{--
    Design 2: runs before first paint (no layout shift).
    - no-js → js;
    - motion-ok when the visitor allows motion (CSS arms entrance states only under it);
    - fine-pointer for parallax and hover-intent features.
    The hero adds its own arming class here (Phase B), always with a safety timeout so content can
    never stay hidden if JavaScript fails.
    - hero-armed (owner: hero): the home hero's sample documents wait off-stage for the deal
      (resources/js/d2/hero/deal.js removes the class as it starts). Only on the home page, with motion
      allowed and when the page opens at its top (a #section link skips the deal). Headings and text
      are never hidden.
    - The hero's safety timeout (home page, 2.5 s, window.__d2HeroArm): unless the hero script has
      claimed the hero (html.hero-live), it removes hero-armed and adds hero-static, the composed final
      state with the document on top and its callouts drawn (as without JavaScript). hero/stage.js clears it
      only when it takes over (to deal once a phone's stage scrolls into view).
    - (Design 2.1, D06: the "metal once per viewport" classes has-metal-in-view and metal-top are gone with
      modules/metal.js: gold is flat and only on the hero's primary button, the header CTA is always Ink.)
    - Font groups (owner: hero-perf; App\Design\D2\FontFaces::groups(), printed just above by the layout): the
      page language's faces ("base") are active from the start; "wide" (another script in the header from
      768 px), "docs" (the sample documents' faces: needed in the first screen on the home page opened at its
      top from 768 px and on the 404 page at every width) and "late" (only below the first screen) are disabled here, before
      the first layout, and enabled once the first contentful paint is out (or at load), so their files load
      after it instead of competing with the first screen. Where the first screen shows the documents, their
      faces start loading right here instead of at the first layout (data-d2-load: font + sample text; on phones
      too when the window is tall enough to show the top of the stage above the tab bar), so the
      stage is complete at the first paint (reduced motion: nothing changes afterwards). Arabic pages also start
      the "base" faces their first screen needs beyond the two preloads (data-d2-early), so inner-page intros
      do not re-wrap when they arrive.
--}}
<script nonce="{{ Vite::cspNonce() }}">
(function (d, w) {
    var h = d.documentElement;
    h.classList.remove('no-js');
    h.classList.add('js');
    var top = !w.location.hash || w.location.hash === '#main-content';
    try {
        if (!w.matchMedia('(prefers-reduced-motion: reduce)').matches) { h.classList.add('motion-ok'); }
        if (w.matchMedia('(pointer: fine)').matches) { h.classList.add('fine-pointer'); }
        if (h.getAttribute('data-page') === 'home') {
            if (h.classList.contains('motion-ok') && top) { h.classList.add('hero-armed'); }
            w.__d2HeroArm = w.setTimeout(function () {
                h.classList.remove('hero-armed');
                if (!h.classList.contains('hero-live')) { h.classList.add('hero-static'); }
            }, 2500);
        }
    } catch (e) {}
    // Font groups: hold what the first screen does not need until the first contentful paint (or load).
    var held = [];
    var release = function () { while (held.length) { held.shift().disabled = false; } };
    try {
        var wide = w.innerWidth >= 768;
        var page = h.getAttribute('data-page');
        // Phones: the home stage starts below the hero text, at 680-870 px from the top (390-430 px wide; Arabic
        // highest, Russian lowest; measured, final pass round 2): its documents show above the 64 px tab bar on
        // tall phones, so their faces load before the first paint there too (reduced motion: nothing redraws).
        var stageTop = { ar: 700, ru: 850 }[h.getAttribute('lang')] || 730;
        var docs = (page === 'home' && top && (wide || w.innerHeight - 64 > stageTop)) || page === 'error';
        var groups = d.querySelectorAll('style[data-d2-fonts]');
        for (var i = 0; i < groups.length; i++) {
            var group = groups[i].getAttribute('data-d2-fonts');
            if (group === 'late' || (group === 'wide' && !wide) || (group === 'docs' && !docs)) {
                groups[i].disabled = true;
                held.push(groups[i]);
            }
        }
        if (held.length) {
            var soon = function () { w.setTimeout(release, 0); };
            // After the first contentful paint (the load event can come first on a light page); where paint
            // timing is missing, at load. A tab opened in the background paints late: 5 s at the latest.
            w.setTimeout(release, 5000);
            if (w.PerformanceObserver && (PerformanceObserver.supportedEntryTypes || []).indexOf('paint') >= 0) {
                new PerformanceObserver(function (list, observer) {
                    if (list.getEntriesByName('first-contentful-paint').length) { observer.disconnect(); soon(); }
                }).observe({ type: 'paint', buffered: true });
            } else {
                w.addEventListener('load', soon);
            }
        }
        var stage = docs && d.fonts && d.fonts.load ? d.querySelector('style[data-d2-fonts="docs"][data-d2-load]') : null;
        var loads = stage ? JSON.parse(stage.getAttribute('data-d2-load')) : [];
        // "base" faces the first screen needs beyond the two preloads (Arabic 400 / 500 and the Latin file).
        var early = d.fonts && d.fonts.load ? d.querySelector('style[data-d2-early]') : null;
        if (early) { loads = JSON.parse(early.getAttribute('data-d2-early')).concat(loads); }
        for (var j = 0; j < loads.length; j++) { d.fonts.load(loads[j][0], loads[j][1]).catch(function () {}); }
    } catch (e) {
        if (!held.length) { return; }
        w.setTimeout(release, 3000);
    }
})(document, window);
</script>
