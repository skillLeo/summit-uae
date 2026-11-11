// Unit tests of the design lint's pure rules (scripts/qa/lint-design.mjs). Run: node --test qa/lint/
// The browser half is proven by `npm run qa:lint -- --self-test` (the fixtures in qa/lint/fixture/).
import assert from 'node:assert/strict';
import { readdirSync, statSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';
import {
    ADVICE,
    AREAS,
    AREA_FILES,
    FIXTURES,
    RULES,
    SHARED_CSS,
    STATE_KINDS,
    applyExemptions,
    areaFor,
    areaForFile,
    arrowsIn,
    buildCssIndex,
    colorAlpha,
    commonTexts,
    compareWhatsApp,
    contentText,
    cssRules,
    cssSource,
    cssWinner,
    cuttableInput,
    dashedSides,
    describeWinner,
    digitMix,
    drawnLength,
    endsWithEllipsis,
    findRuleFile,
    globRegExp,
    hasGradient,
    iconVerdict,
    inTypeScale,
    isCardToken,
    isChipToken,
    isKpiToken,
    isMonospace,
    isSafeAreaSource,
    isWhatsAppHref,
    itemKind,
    linearScale,
    longhandNames,
    mulLinear,
    neutralColor,
    normDeclaration,
    normSelector,
    onSpacingScale,
    ownLinear,
    parseArgs,
    primaryFamily,
    readSources,
    readWa01,
    renderMarkdown,
    sampleDate,
    sampleNumber,
    separatorIndexes,
    splitLayers,
    stateKind,
    stepNumber,
    summarise,
    trackedCaps,
    typeScale,
    visibleShadow,
    winnerDeclarations,
    zeroPadded,
} from '../../scripts/qa/lint-design.mjs';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '../..');

test('the rule table has the sixteen rules of §7.1, in order, with unique slugs', () => {
    assert.deepEqual(RULES.map((r) => r.id), Array.from({ length: 16 }, (_, i) => i + 1));
    assert.equal(new Set(RULES.map((r) => r.slug)).size, 16);
    for (const r of RULES) assert.ok(r.title && r.brief, `rule ${r.id} has a title and a brief section`);
    assert.deepEqual(AREAS, ['platform', 'components', 'header', 'footer-shell', 'home-a', 'home-b', 'inner', 'time-digits']);
});

test('the self-test fixtures make every rule fire exactly once on the main pages', () => {
    const once = {};
    for (const f of FIXTURES.filter((x) => x.slug === 'fixture')) {
        for (const [rule, patterns] of Object.entries(f.expect)) once[rule] = (once[rule] ?? 0) + patterns.length;
    }
    assert.deepEqual(Object.keys(once).map(Number).sort((a, b) => a - b), RULES.map((r) => r.id));
    for (const [rule, n] of Object.entries(once)) assert.equal(n, 1, `rule ${rule} fires once`);
    for (const f of FIXTURES) for (const list of Object.values(f.expect)) for (const re of list) assert.ok(re instanceof RegExp);
    // The illustration art of the main fixture covers every rule that is recorded (not skipped) on illustration paper.
    assert.deepEqual(Object.keys(FIXTURES[0].art).map(Number), [3, 5, 9, 10, 13, 16]);
    for (const f of FIXTURES) {
        assert.equal(typeof f.shell, 'boolean', `${f.path}: shell says whether the overlay states run`);
        assert.ok(Array.isArray(f.states) && f.advice && Number.isInteger(f.safeArea), `${f.path}: states, advice and safeArea are declared`);
        for (const id of Object.keys(f.advice).map(Number)) assert.ok(ADVICE.some((a) => a.id === id), `advice ${id} is a §3.6 pattern`);
    }
    assert.deepEqual(FIXTURES[0].advice, {}, 'the main fixture is clean of the advice patterns (its controls)');
    const variants = FIXTURES.find((f) => f.slug === 'variants');
    assert.deepEqual(Object.keys(variants.advice).map(Number), ADVICE.map((a) => a.id), 'the variants fixture plants each advice pattern');
    assert.ok(variants.states.some((s) => s.startsWith('tab ')) && variants.states.includes('details and disclosures open'), 'the states phase is proven on the variants fixture');
    assert.ok(variants.expect[5].length >= 3 && variants.expect[5].some((re) => /placeholder cut/.test(re.source)) && variants.expect[5].some((re) => /chosen option cut/.test(re.source)), 'rule 5 field cuts are planted');
    assert.equal(variants.safeArea, 1, 'one safe-area inset is exempted');
    for (const f of FIXTURES) for (const list of Object.values(f.advice)) for (const re of list) assert.ok(re instanceof RegExp, `${f.path}: advice is matched one to one by patterns`);
    // rules 9 and 10 as drawn: the label at its own 15 px in a canvas at 0.9 fires (13.5 px, 7.2/10.8 px), the one sized ÷ 0.949 does not
    assert.ok(variants.expect[9].some((re) => re.source.includes('13\\.5px \\(15px at scale 0\\.9\\)')), 'rule 9 is measured as drawn');
    assert.ok(variants.expect[10].some((re) => re.source.includes('7\\.2px \\(8px at scale 0\\.9\\)')), 'rule 10 is measured as drawn');
});

test('every kind of state has a fixture state that must be linted, with a violation planted only there', () => {
    const kinds = new Set(FIXTURES.flatMap((f) => f.states).map(stateKind));
    for (const kind of STATE_KINDS) assert.ok(kinds.has(kind), `a fixture lints a state of kind "${kind}"`);
    // each expectation that names a state (area | state | …) names one of the listed states
    const states = new Set(FIXTURES.flatMap((f) => f.states));
    const named = FIXTURES.flatMap((f) => Object.values(f.expect).flat())
        .map((re) => re.source.match(/^\^[\w-]+ \\\| (.+?) \\\| /))
        .filter(Boolean)
        .map((m) => m[1].replace(/\\(.)/g, '$1'))
        .filter((label) => !['page', 'hover'].includes(label));
    for (const label of named) assert.ok(states.has(label), `the state "${label}" is listed as linted`);
    const statesPage = FIXTURES.find((f) => f.slug === 'states');
    assert.equal(statesPage.shell, true, 'the states page runs the shell states (previews, results, tooltip, compact header)');
    assert.deepEqual(statesPage.advice, {}, 'the states page is clean of the advice patterns');
});

test('splitLayers and colorAlpha read computed lists and colours', () => {
    assert.deepEqual(splitLayers('rgba(0, 0, 0, 0.2) 0px 2px 8px 0px, rgb(1 2 3 / 0.5) 0px 1px 1px 0px'), ['rgba(0, 0, 0, 0.2) 0px 2px 8px 0px', 'rgb(1 2 3 / 0.5) 0px 1px 1px 0px']);
    assert.deepEqual(splitLayers('"D2 Sans", "D2 Sans Fallback", system-ui'), ['"D2 Sans"', '"D2 Sans Fallback"', 'system-ui']);
    assert.equal(colorAlpha('transparent'), 0);
    assert.equal(colorAlpha('rgba(0, 0, 0, 0)'), 0);
    assert.equal(colorAlpha('rgb(11 11 12 / 12%)'), 0.12);
    assert.equal(colorAlpha('rgb(11, 11, 12)'), 1);
});

test('rule 6: a shadow counts only when it draws something', () => {
    assert.equal(visibleShadow('none'), false);
    assert.equal(visibleShadow('rgba(0, 0, 0, 0) 0px 2px 8px 0px'), false, 'transparent');
    assert.equal(visibleShadow('rgb(0, 0, 0) 0px 0px 0px 0px'), false, 'all lengths zero');
    assert.equal(visibleShadow('rgba(0, 0, 0, 0.2) 0px 2px 8px 0px'), true);
    assert.equal(visibleShadow('rgba(0, 0, 0, 0) 0px 0px 0px 0px, rgba(11, 11, 12, 0.12) 0px 16px 40px 0px'), true, 'any visible layer');
    assert.equal(visibleShadow('rgba(0, 0, 0, 0.06) 0px 0px 0px 0.5px inset'), true, 'an inset hairline is a shadow');
});

test('rule 7: gradients', () => {
    assert.equal(hasGradient('linear-gradient(90deg, rgb(255, 255, 255), rgb(238, 238, 238))'), true);
    assert.equal(hasGradient('repeating-radial-gradient(circle, red, blue)'), true);
    assert.equal(hasGradient('url("/images/d2/paper-512.avif")'), false);
    assert.equal(hasGradient('none'), false);
});

test('rule 1: the first family decides', () => {
    assert.equal(primaryFamily('"D2 Mono", "D2 Mono Fallback", ui-monospace, monospace'), 'D2 Mono');
    assert.equal(isMonospace('"D2 Mono", "D2 Mono Fallback", ui-monospace, monospace'), true);
    assert.equal(isMonospace('monospace'), true);
    assert.equal(isMonospace('"Courier New", monospace'), true);
    assert.equal(isMonospace('"D2 Sans", "D2 Sans Fallback", "D2 Sans Arabic Script", system-ui, sans-serif'), false);
    assert.equal(isMonospace('Arial, monospace'), false, 'a monospace fallback after a sans is not monospace text');
});

test('rule 2: uppercase or capitals with letter-spacing above 0.02em', () => {
    const style = (o) => ({ textTransform: 'none', fontVariantCaps: 'normal', letterSpacing: 'normal', fontSize: '15px', text: 'Practice areas', ...o });
    assert.match(trackedCaps(style({ textTransform: 'uppercase', letterSpacing: '1.8px' })), /uppercase with letter-spacing 0\.12em/);
    assert.equal(trackedCaps(style({ textTransform: 'uppercase', letterSpacing: '0.3px' })), null, '0.02em exactly is allowed');
    assert.equal(trackedCaps(style({ textTransform: 'uppercase' })), null, 'no tracking');
    assert.equal(trackedCaps(style({ letterSpacing: '1.5px' })), null, 'tracked lower case');
    assert.match(trackedCaps(style({ letterSpacing: '1.5px', text: 'PRACTICE AREAS' })), /text in capitals/);
    assert.match(trackedCaps(style({ letterSpacing: '1.5px', fontVariantCaps: 'all-small-caps' })), /font-variant-caps/);
    assert.equal(trackedCaps(style({ letterSpacing: '-0.5px', textTransform: 'uppercase' })), null, 'negative tracking');
});

test('rule 3: standalone separator characters', () => {
    assert.deepEqual(separatorIndexes('Open now · closes 18:00'), [9]);
    assert.deepEqual(separatorIndexes('·'), [0], 'a separator node of its own');
    assert.deepEqual(separatorIndexes('col·lecció'), [], 'inside a word');
    assert.deepEqual(separatorIndexes('EN • RU ∙ AR'), [3, 8]);
    assert.deepEqual(separatorIndexes('A - B | C'), []);
});

test('rule 4: arrows, with paired guillemets as quotation marks', () => {
    assert.deepEqual(arrowsIn('Read more →'), ['→']);
    assert.deepEqual(arrowsIn('Next ->'), ['->']);
    assert.deepEqual(arrowsIn('Open the guide ↗'), ['↗']);
    assert.deepEqual(arrowsIn('«Подробнее»'), [], 'Russian quotation marks');
    assert.deepEqual(arrowsIn('اقرأ «الدليل» كاملاً'), []);
    assert.deepEqual(arrowsIn('Next »'), ['»'], 'an unpaired guillemet is an arrow');
    assert.deepEqual(arrowsIn('‹ Back'), ['‹']);
    assert.deepEqual(arrowsIn('Explore This Service'), []);
});

test('rule 5: trailing ellipsis', () => {
    assert.equal(endsWithEllipsis('The summary was cut…'), true);
    assert.equal(endsWithEllipsis('The summary was cut...'), true);
    assert.equal(endsWithEllipsis('«Он сказал…»'), true);
    assert.equal(endsWithEllipsis('A complete sentence.'), false);
    assert.equal(endsWithEllipsis('… and then'), false);
});

test('rule 15: digit systems', () => {
    assert.equal(digitMix('من 9 صباحاً حتى ٦ مساءً'), true);
    assert.equal(digitMix('من ٩ صباحاً حتى ٦ مساءً'), false);
    assert.equal(digitMix('من 9 صباحاً حتى 6 مساءً'), false);
    assert.equal(digitMix('۲ و 3'), true, 'Extended Arabic-Indic counts as Eastern');
});

test('rule 10: the spacing scale, either sign', () => {
    for (const v of [0, 4, 8, 12, 16, 20, 24, 32, 48, 64, 96, 128, -24, -32, 23.9]) assert.equal(onSpacingScale(v), true, String(v));
    for (const v of [1, 2.8, 6, 10, 14, 18, 40, 80, -9.6, 23.5]) assert.equal(onSpacingScale(v), false, String(v));
});

test('rule 9: the type scale per width, Arabic body one step larger', () => {
    assert.deepEqual(typeScale(1440, false), [56, 36, 22, 19, 17, 16, 15, 13]);
    assert.deepEqual(typeScale(768, false), [56, 36, 22, 19, 17, 16, 15, 13], 'desktop values from 768 px');
    assert.deepEqual(typeScale(390, false), [40, 28, 20, 17, 16, 15, 14, 13]);
    assert.equal(inTypeScale(18, typeScale(1440, true)), true);
    assert.equal(inTypeScale(18, typeScale(1440, false)), false);
    assert.equal(inTypeScale(40, typeScale(1440, false)), false, 'a mobile size on desktop');
    assert.equal(inTypeScale(14, typeScale(390, false)), true);
    assert.equal(inTypeScale(12, typeScale(390, false)), false);
    assert.equal(inTypeScale(16.99, typeScale(1440, false)), true);
});

test('rule 14: WhatsApp hrefs against WA01', () => {
    const wa01 = 'https://wa.me/971524666191?text=Hello%20Summit%20Legal%20Consultancy%2C%20I%20would%20like';
    assert.equal(isWhatsAppHref(wa01), true);
    assert.equal(isWhatsAppHref('https://api.whatsapp.com/send?phone=971524666191'), true);
    assert.equal(isWhatsAppHref('https://www.whatsapp.com/'), true);
    assert.equal(isWhatsAppHref('https://example.org/wa.me'), false);
    assert.equal(isWhatsAppHref('tel:+971524666191'), false);
    assert.equal(compareWhatsApp(wa01, wa01), null);
    assert.equal(compareWhatsApp('https://wa.me/971524666191?text=Hello Summit Legal Consultancy, I would like', wa01), null, 'the same draft, encoded differently');
    assert.match(compareWhatsApp('https://wa.me/971524666191?text=Hi', wa01), /draft text differs/);
    assert.match(compareWhatsApp('https://wa.me/971524666191', wa01), /no draft text/);
    assert.match(compareWhatsApp('https://wa.me/971500000000?text=Hello%20Summit%20Legal%20Consultancy%2C%20I%20would%20like', wa01), /number/);
    assert.match(compareWhatsApp(`${wa01}&utm_source=site`, wa01), /extra parameter utm_source/);
    assert.match(compareWhatsApp('https://api.whatsapp.com/send?phone=971524666191&text=x', wa01), /address/);
    assert.match(compareWhatsApp(wa01, null), /no approved WA01/);
});

test('rule 13: allowed names and banned places (Icons::ALLOWED)', () => {
    for (const n of ['search', 'menu', 'close', 'cross', 'chevron-down', 'chevron-up', 'phone', 'mail', 'email', 'check', 'upload', 'info', 'warning', 'error', 'carousel-prev', 'carousel-next', 'form-back', 'form-next']) {
        assert.equal(iconVerdict(n, {}), null, n);
    }
    assert.match(iconVerdict('cheque', {}), /not in the allowed list/);
    assert.match(iconVerdict('whatsapp', {}), /drawn "whatsapp" icon/, 'a drawn WhatsApp icon is not the brand mark');
    assert.match(iconVerdict(null, {}), /without data-icon/);
    assert.match(iconVerdict('clock', {}), /outside the contact block/);
    assert.equal(iconVerdict('clock', { contact: true }), null);
    assert.match(iconVerdict('external', { inLink: true }), /stays on this site/);
    assert.equal(iconVerdict('external', { inLink: true, externalLink: true }), null);
    assert.match(iconVerdict('arrow', {}), /outside carousel/);
    assert.equal(iconVerdict('arrow', { step: true }), null);
    assert.match(iconVerdict('chevron', { inLink: true }), /"go" chevron/);
    assert.match(iconVerdict('chevron-start', { inCard: true }), /"go" chevron/);
    assert.equal(iconVerdict('chevron', { inLink: true, disclosure: true }), null);
    assert.equal(iconVerdict('chevron', {}), null, 'a chevron outside links and cards (select, breadcrumb, tab scroll)');
    assert.match(iconVerdict('minus', {}), /outside a stepper/);
    assert.equal(iconVerdict('plus', { stepper: true }), null);
    assert.match(iconVerdict('calendar', {}), /outside a date field/);
    assert.equal(iconVerdict('calendar', { date: true }), null);
    assert.match(iconVerdict('check', { chip: true }), /inside a chip/);
    assert.match(iconVerdict('search', { cardTitle: true }), /inside a card title/);
    assert.match(iconVerdict('info', { kpi: true }), /inside a KPI tile/);
    assert.match(iconVerdict('check', { menuItem: true }), /inside a menu item/);
    assert.equal(iconVerdict('external', { menuItem: true, externalLink: true }), null, 'the ↗ of an external link stays');
    assert.equal(iconVerdict('chevron-down', { menuItem: true, disclosure: true }), null, 'a disclosure chevron stays');
    assert.match(iconVerdict('whatsapp', { inLink: true }), /drawn "whatsapp" icon.*data-brand/, 'the mark is the brand file, never a glyph');
});

test('rule 13: a [data-lint-specimen] gallery shows allowed icons out of context, never banned names', () => {
    for (const n of ['clock', 'external', 'minus', 'plus', 'calendar', 'chevron', 'chevron-start']) assert.equal(iconVerdict(n, { specimen: true }), null, n);
    assert.match(iconVerdict('briefcase', { specimen: true }), /not in the allowed list/);
    assert.match(iconVerdict('arrow', { specimen: true }), /outside carousel/, 'a bare arrow is never a specimen');
    assert.match(iconVerdict('check', { specimen: true, chip: true }), /inside a chip/, 'banned places stay banned');
});

test('card, chip and KPI tokens are blocks or modifiers, never BEM elements', () => {
    for (const t of ['card', 'svc-card', 'guide-card', 'card--interactive', 'contact-card']) assert.equal(isCardToken(t), true, t);
    for (const t of ['card__title', 'acc--card', 'megamenu__col--card', 'cards', 'cardinal']) assert.equal(isCardToken(t), false, t);
    for (const t of ['chip', 'chip--choice', 'filter-chip']) assert.equal(isChipToken(t), true, t);
    for (const t of ['who__chip', 'chip__label', 'chips']) assert.equal(isChipToken(t), false, t);
    assert.equal(isKpiToken('kpi'), true);
    assert.equal(isKpiToken('kpi__value'), false);
});

test('set by: the longhand names of a computed property', () => {
    assert.deepEqual(longhandNames('margin-top', false), ['margin-top', 'margin-block-start']);
    assert.deepEqual(longhandNames('padding-left', false), ['padding-left', 'padding-inline-start']);
    assert.deepEqual(longhandNames('padding-left', true), ['padding-left', 'padding-inline-end'], 'RTL: left is the inline end');
    assert.deepEqual(longhandNames('row-gap', false), ['row-gap']);
    assert.deepEqual(longhandNames('box-shadow', false), ['box-shadow']);
});

test('set by: the winning declaration from the DevTools cascade (CSS.getMatchedStylesForNode)', () => {
    const rule = (selector, props, extra = {}) => ({ rule: { selectorList: { text: selector }, origin: 'regular', style: { cssProperties: props }, ...extra } });
    const ua = rule('td', [{ name: 'padding-inline-start', value: '1px' }, { name: 'padding-block-start', value: '1px' }], { origin: 'user-agent' });
    ua.rule.origin = 'user-agent';
    const reset = rule('*', [{ name: 'margin', value: '0', text: 'margin: 0;' }, { name: 'margin-top', value: '0px' }], { layers: [{ text: 'reset' }] });
    const crumbs = rule('.crumbs__list', [
        { name: 'margin-block', value: 'calc(var(--x) / -2)', text: 'margin-block: calc(var(--x) / -2);' },
        { name: 'margin-block-start', value: '' },
        { name: 'margin-block-end', value: '' },
    ], { layers: [{ text: 'pages' }] });
    const grid = rule('.grid', [{ name: 'gap', value: '10px', text: 'gap: 10px;' }, { name: 'row-gap', value: '10px' }, { name: 'column-gap', value: '10px' }]);
    assert.equal(cssSource({ matchedCSSRules: [ua] }, ['padding-left', 'padding-inline-start']), 'user agent: td { padding-inline-start: 1px }');
    assert.equal(cssSource({ matchedCSSRules: [ua, reset, crumbs] }, ['margin-top', 'margin-block-start']), '@layer pages .crumbs__list { margin-block: calc(var(--x) / -2) }', 'the last rule in cascade order wins');
    assert.equal(cssSource({ matchedCSSRules: [crumbs, reset] }, ['margin-top', 'margin-block-start']), '@layer reset * { margin: 0 }');
    assert.equal(cssSource({ matchedCSSRules: [grid] }, ['row-gap']), '.grid { gap: 10px }', 'gap is the shorthand of row-gap');
    assert.equal(cssSource({ matchedCSSRules: [grid] }, ['margin-top']), null, 'nothing sets it: the initial value');
    const inline = { matchedCSSRules: [grid], inlineStyle: { cssProperties: [{ name: 'row-gap', value: '3px', text: 'row-gap: 3px' }] } };
    assert.equal(cssSource(inline, ['row-gap']), 'inline style { row-gap: 3px } (set by script)');
    const disabled = rule('.off', [{ name: 'box-shadow', value: '0 0 1px red', text: '/* box-shadow: 0 0 1px red; */', disabled: true }]);
    assert.equal(cssSource({ matchedCSSRules: [disabled] }, ['box-shadow']), null, 'a disabled declaration does not set it');
    const before = { matchedCSSRules: [], pseudoElements: [{ pseudoType: 'before', matches: [rule('.wm::before', [{ name: 'box-shadow', value: 'x', text: 'box-shadow: x;' }])] }] };
    assert.equal(cssSource(before, ['box-shadow'], 'before'), '.wm::before { box-shadow: x }');
    assert.equal(cssSource(before, ['box-shadow'], 'after'), null);
    assert.equal(cssSource(null, ['box-shadow']), null);
});

test('set by: inherited font size and weight, nearest ancestor first; nested rules name their parent', () => {
    const rule = (selector, props, extra = {}) => ({ rule: { selectorList: { text: selector }, origin: 'regular', style: { cssProperties: props }, ...extra } });
    const body = rule('body', [{ name: 'font-size', value: 'var(--fs-body)', text: 'font-size: var(--fs-body);' }], { layers: [{ text: 'base' }] });
    const strongUa = rule('strong', [{ name: 'font-weight', value: 'bolder' }]);
    strongUa.rule.origin = 'user-agent';
    const note = rule('.sg-note', [{ name: 'color', value: 'red', text: 'color: red;' }]);
    const matched = { matchedCSSRules: [note], inherited: [{ matchedCSSRules: [] }, { matchedCSSRules: [body] }] };
    assert.equal(cssSource(matched, ['font-size'], null, true), 'inherited: @layer base body { font-size: var(--fs-body) }');
    assert.equal(cssSource(matched, ['font-size']), null, 'without inherit only the own rules count');
    assert.equal(cssSource({ matchedCSSRules: [strongUa] }, ['font-weight'], null, true), 'user agent: strong { font-weight: bolder }');
    const host = { matchedCSSRules: [rule('.wm', [{ name: 'font-size', value: '11px', text: 'font-size: 11px;' }])], pseudoElements: [{ pseudoType: 'before', matches: [] }], inherited: [] };
    assert.equal(cssSource(host, ['font-size'], 'before', true), 'inherited: .wm { font-size: 11px }', 'a pseudo-element inherits from its host');
    const nested = rule('& .timeline__dot', [{ name: 'margin-block-start', value: 'calc(1px)', text: 'margin-block-start: calc(1px);' }], { layers: [{ text: 'components' }], nestingSelectors: ['.timeline', '.sg-cards'] });
    assert.equal(cssSource({ matchedCSSRules: [nested] }, ['margin-top', 'margin-block-start']), '@layer components .sg-cards { .timeline { & .timeline__dot { margin-block-start: calc(1px) } } }');
});

test('generated content text', () => {
    assert.equal(contentText('"→"'), '→');
    assert.equal(contentText('" \\2192"'), ' →');
    assert.equal(contentText('none'), '');
    assert.equal(contentText('normal'), '');
    assert.equal(contentText('attr(data-wm)', (name) => (name === 'data-wm' ? 'SAMPLE · ILLUSTRATION' : '')), 'SAMPLE · ILLUSTRATION');
    assert.equal(contentText('"(" attr(data-n) ")"', () => '3'), '(3)');
    assert.equal(contentText('counter(step) ". "'), '. ');
});

test('areas: where each violation is fixed', () => {
    const base = { design: '2', rule: 13, page: 'home', sg: null, region: 'main', sheet: null, homeSection: 'hero', hours: false, component: null, base: false };
    assert.equal(areaFor({ ...base, design: '1' }), 'design-1');
    assert.equal(areaFor({ ...base, rule: 15 }), 'time-digits');
    assert.equal(areaFor({ ...base, hours: true }), 'time-digits');
    assert.equal(areaFor(base), 'home-a');
    assert.equal(areaFor({ ...base, homeSection: 'guides' }), 'home-b');
    assert.equal(areaFor({ ...base, homeSection: 'consultation-form' }), 'home-b');
    assert.equal(areaFor({ ...base, page: 'preview' }), 'inner');
    assert.equal(areaFor({ ...base, region: 'header' }), 'header');
    assert.equal(areaFor({ ...base, region: 'palette' }), 'home-b');
    assert.equal(areaFor({ ...base, region: 'footer' }), 'footer-shell');
    assert.equal(areaFor({ ...base, region: 'sheet', sheet: 'menu' }), 'footer-shell');
    assert.equal(areaFor({ ...base, region: 'sheet', sheet: 'toc' }), 'inner');
    assert.equal(areaFor({ ...base, region: 'body' }), 'platform');
    assert.equal(areaFor({ ...base, rule: 6, component: 'btn' }), 'components', 'CSS rules go to the UI kit');
    assert.equal(areaFor({ ...base, rule: 13, component: 'btn' }), 'home-a', 'markup rules stay with the section');
    assert.equal(areaFor({ ...base, rule: 9, base: true }), 'platform');
    const sg = { ...base, page: 'styleguide', homeSection: null };
    assert.equal(areaFor({ ...sg, sg: 'controls' }), 'components');
    assert.equal(areaFor({ ...sg, sg: 'icons' }), 'components', 'the icon gallery is the components area\'s');
    assert.equal(areaFor({ ...sg, sg: 'documents' }), 'home-a', 'the sample documents are the hero\'s');
    assert.equal(areaFor({ ...sg, sg: 'mobile' }), 'components');
    assert.equal(areaFor({ ...sg, sg: 'mobile', shellPart: 'footer' }), 'footer-shell', 'tab bar and sheet specimens');
    assert.equal(areaFor({ ...sg, sg: 'shell' }), 'header');
    assert.equal(areaFor({ ...sg, sg: 'shell', shellPart: 'footer' }), 'footer-shell');
    assert.equal(areaFor({ ...sg, sg: null }), 'platform', 'the styleguide frame (page head)');
    assert.equal(areaFor({ ...sg, sg: 'foundations', rule: 6, component: 'btn' }), 'platform', 'a styleguide section wins over the UI-kit class');
    assert.equal(areaFor({ ...sg, sg: 'controls', region: 'header' }), 'header', 'the real header of the styleguide page');
});

test('exemptions: approved ellipses, approved separators and illustration art are not counted', () => {
    const sources = { approvedEllipses: new Set(['Sending request…']), approvedSeparators: ['Working Languages: Arabic · English · Russian'] };
    const records = [
        { rule: 5, full: 'Sending request…', count: 2 },
        { rule: 5, full: 'The summary was cut…', count: 1 },
        { rule: 3, around: 'Arabic · English', selector: 'p', count: 1 },
        { rule: 3, around: 'EN · RU', selector: 'p', count: 1 },
        { rule: 9, count: 1 },
        { rule: 9, count: 3, exempt: 'illustration' },
        { rule: 3, around: 'SAMPLE · ILLUSTRATION', count: 1, exempt: 'illustration' },
    ];
    records.push({ rule: 9, count: 2, exempt: 'specimen-zoom' });
    const { kept, exempt } = applyExemptions(records, sources);
    assert.equal(exempt.specimenZoom.length, 1, 'a hit only a styleguide specimen\'s zoom causes is apart');
    assert.equal(exempt.approvedEllipsis, 2);
    assert.equal(exempt.approvedSeparators.length, 1);
    assert.deepEqual(exempt.illustration.map((r) => r.rule), [9, 3]);
    assert.deepEqual(kept.map((r) => r.full ?? r.around ?? r.rule), ['The summary was cut…', 'EN · RU', 9]);
});

test('only approved copy is exempt: proposed strings ending with an ellipsis count (rule 5)', () => {
    const sources = readSources();
    assert.ok(sources.approved.length > 1000, 'the approved strings of database/content are read');
    assert.ok(sources.approvedEllipses.has('Submitting your request...'), 'the home form submitting state (approved) is exempt');
    assert.equal(sources.approvedEllipses.has('Loading search…'), false, 'a proposed string is not');
    for (const s of sources.approvedSeparators) assert.match(s, /\s·\s/);
    assert.ok(sources.approvedSeparators.some((s) => s.startsWith('Working Languages:')));
});

test('summary: violations sorted by rule per page, illustration art tallied apart', () => {
    const results = [{
        route: 'home', locale: 'en', width: 1440, path: '/en/', status: 200, ok: true, info: {}, states: [],
        records: [
            { rule: 13, area: 'home-a', selector: 'a', value: 'v', text: 't', count: 1 },
            { rule: 3, area: 'home-a', selector: 'b', value: 'v', text: 't', count: 2 },
            { rule: 9, area: 'home-a', selector: 'c', value: 'v', text: 't', count: 5, exempt: 'illustration' },
            { rule: 10, area: 'inner', selector: 'd', value: 'v', text: 't', count: 1 },
            { rule: 10, area: 'header', selector: 'e', value: 'padding-inline-start 29.5px (32px at scale 0.921)', text: 't', count: 3, exempt: 'specimen-zoom' },
        ],
    }];
    const sources = { approvedEllipses: new Set(), approvedSeparators: [] };
    const report = summarise(results, sources, parseArgs([]));
    assert.deepEqual(report.summary.specimenZoomExempt, { 9: 0, 10: 3 }, 'not counted, tallied apart');
    assert.match(renderMarkdown(report, { ...parseArgs([]), locales: ['en'], routeFilter: null }), /zooms a component to fit its card[^\n]*rule 9: 0, rule 10: 3/);
    assert.deepEqual(report.violations.map((v) => v.rule), [3, 10, 13]);
    assert.equal(report.summary.total, 4);
    assert.equal(report.summary.byRule[3], 2);
    assert.equal(report.summary.illustrationExempt[9], 5);
    assert.deepEqual(report.pages[0].illustrationExempt, { 9: 5 });
    assert.deepEqual(report.summary.byArea, { 'home-a': 3, inner: 1 });
});

test('arguments: defaults, rule slugs, bad values', () => {
    const opt = parseArgs([]);
    assert.equal(opt.base, 'http://127.0.0.1:8042');
    assert.equal(opt.design, '2');
    assert.equal(opt.concurrency, 4);
    assert.equal(opt.states, true);
    assert.equal(opt.illustrations, 'art');
    assert.equal(parseArgs(['--illustrations=strict']).illustrations, 'strict');
    assert.throws(() => parseArgs(['--illustrations=loose']), /illustrations/);
    assert.deepEqual(opt.widths, [1440, 768, 390]);
    assert.deepEqual(opt.rules, RULES.map((r) => r.id));
    assert.deepEqual(parseArgs(['--rules=icons,3,whatsapp']).rules, [3, 13, 14]);
    assert.equal(parseArgs(['--states=off']).states, false);
    assert.equal(parseArgs(['--clock=real']).clockMs, null);
    assert.throws(() => parseArgs(['--rules=17']), /unknown rule/);
    assert.throws(() => parseArgs(['--design=3']), /design/);
    assert.throws(() => parseArgs(['--widths=100']), /widths/);
    assert.throws(() => parseArgs(['--states=maybe']), /states/);
    assert.throws(() => parseArgs(['--nope']), /unknown argument/);
});

test('WA01 comes from the approved content, one wa.me draft per locale', () => {
    const wa01 = readWa01();
    assert.deepEqual(Object.keys(wa01).sort(), ['ar', 'en', 'ru']);
    for (const href of Object.values(wa01)) {
        assert.equal(isWhatsAppHref(href), true);
        assert.match(new URL(href).searchParams.get('text') ?? '', /\S/);
    }
});

test('the Markdown report: counts by rule, public pages apart from the styleguide, routes, set by, exemptions', () => {
    const rec = (route, rule, extra = {}) => ({ rule, area: 'home-b', selector: `.x${rule}`, value: 'v', text: 't', count: 1, ...extra });
    const results = [
        { route: 'home', locale: 'en', width: 390, path: '/en/', status: 200, ok: true, states: [], info: { placeholders: 2, waLinks: 2, waMarkSources: ['placeholder'] }, records: [rec('home', 12), rec('home', 10, { source: '.x10 { gap: 10px }' })] },
        { route: 'styleguide', locale: 'ar', width: 1440, path: '/ar/styleguide/', status: 200, ok: true, states: [], info: {}, records: [rec('styleguide', 9, { count: 3 }), rec('styleguide', 9, { count: 4, exempt: 'illustration' })] },
    ];
    const opt = { ...parseArgs([]), locales: ['en', 'ar'], routeFilter: null };
    const md = renderMarkdown(summarise(results, { approvedEllipses: new Set(), approvedSeparators: [] }, opt), opt);
    assert.match(md, /\*\*5 violation\(s\)\*\* on 2 page\(s\) linted/);
    assert.match(md, /Public pages: 2; the internal styleguide: 3\./);
    assert.match(md, /\| 9 \| Font size off the scale.* \| 3 \| 1 \| 1 \| D08 \| home-b 3 \|/);
    assert.match(md, /## By route[\s\S]*\| styleguide \| 3 \| 9: 3 \| 1 \|[\s\S]*\| home \| 2 \| 10: 1, 12: 1 \| 1 \|/);
    assert.match(md, /## Set by[\s\S]*`\.x10 \{ gap: 10px \}`/);
    assert.match(md, /On the sample paper of `\[data-illustration\]`[^\n]*rule 9: 4/);
    assert.match(md, /2 WhatsApp link\(s\) on 1 page\(s\); 2 show the placeholder mark/);
    assert.doesNotMatch(md, /undefined|NaN/);
});

test('rule 10: a safe-area inset is device geometry, not a spacing step', () => {
    assert.equal(isSafeAreaSource('@layer components .tabbar--static.tabbar--safe { padding-block-end: var(--safe-bottom) }'), true);
    assert.equal(isSafeAreaSource('.bar { padding-block-end: env(safe-area-inset-bottom, 0px) }'), true);
    assert.equal(isSafeAreaSource('.bar { padding-inline: max(var(--s-4), var(--safe-x)) }'), true);
    assert.equal(isSafeAreaSource('.bar { padding-block-end: var(--s-8) }'), false);
    assert.equal(isSafeAreaSource('initial value of padding-top'), false);
    assert.equal(isSafeAreaSource(undefined), false);
});

test('rule 5: the inputs whose one line a field edge can cut', () => {
    for (const t of ['', 'text', 'search', 'email', 'tel', 'url', 'number', 'TEXT']) assert.equal(cuttableInput(t), true, t);
    for (const t of ['date', 'time', 'checkbox', 'radio', 'range', 'file', 'color', 'hidden', 'submit']) assert.equal(cuttableInput(t), false, t);
});

test('advice: zero-padded numbering, dashed sides, metadata on every item', () => {
    for (const t of ['01', '07.', '09)', ' 03 ', '٠٣', '۰۴']) assert.equal(zeroPadded(t), true, t);
    for (const t of ['1', '10', '2026', 'Step 01', '001', '1.', 'O1']) assert.equal(zeroPadded(t), false, t);
    assert.equal(dashedSides(['dashed', 'dashed', 'dashed', 'dashed'], ['1px', '1px', '1px', '1px']), 4);
    assert.equal(dashedSides(['dotted', 'solid', 'dashed', 'dashed'], ['1px', '1px', '1px', '0px']), 2, 'a side without width does not count');
    assert.equal(dashedSides(['solid', 'solid', 'solid', 'solid'], ['1px', '1px', '1px', '1px']), 0);
    const sets = [new Set(['3 min read', '3', 'Debts']), new Set(['3 min read', '3', 'Employment']), new Set(['3', '3 min read', 'Tax'])];
    assert.deepEqual(commonTexts(sets), ['3 min read'], 'a text inside a longer common text is left out');
    assert.deepEqual(commonTexts(sets.slice(0, 2)), [], 'fewer than three items is not a pattern');
    assert.deepEqual(commonTexts([new Set(['Arabic', 'English']), new Set(['English', 'Arabic', 'Russian']), new Set(['Arabic', 'English'])]), ['Arabic', 'English']);
});

test('states phase: sample values for calculators', () => {
    const clock = Date.parse('2026-11-10T10:30:00+04:00');
    assert.equal(sampleDate('startDate gr_start gr-start', clock), '2020-07-01', 'a start lies years back');
    assert.equal(sampleDate('lastWorkingDay gr_end', clock), '2026-10-01');
    assert.equal(sampleDate('expiryDate rent_expiry', clock), '2027-02-01', 'an expiry lies ahead');
    assert.equal(sampleDate('noticeReceivedDate rent_notice', clock), '2026-09-01');
    assert.equal(sampleDate('', clock), '2025-11-01');
    assert.equal(sampleNumber('10,000', null), '10000', 'the placeholder is the example value');
    assert.equal(sampleNumber('١٠٬٠٠٠', null), '10000', 'Eastern Arabic digits');
    assert.equal(sampleNumber('', '30'), '30', 'else the minimum');
    assert.equal(sampleNumber('e.g. salary', ''), '1', 'else 1');
    assert.equal(sampleNumber('0', '0'), '0');
});

test('state kinds of the report', () => {
    assert.equal(stateKind('page'), 'page');
    assert.equal(stateKind(undefined), 'page');
    assert.equal(stateKind('hover'), 'hover');
    assert.equal(stateKind('overlay #d2-megamenu'), 'overlay');
    assert.equal(stateKind('overlay #d2-megamenu preview "Criminal Cases"'), 'menu preview');
    assert.equal(stateKind('overlay modal:palette results "cheque"'), 'search results');
    assert.equal(stateKind('compact header overlay sheet:menu'), 'compact header');
    assert.equal(stateKind('details and disclosures open'), 'disclosures');
    assert.equal(stateKind('tab "Employment"'), 'tab');
    assert.equal(stateKind('choice who_audience "Businesses"'), 'choice');
    assert.equal(stateKind('wizard step 3'), 'wizard');
    assert.equal(stateKind('calculator rent with values'), 'calculator');
    assert.equal(stateKind('pager "Employment contract"'), 'pager');
    assert.equal(stateKind('search home-faq-search "cheque"'), 'search');
    assert.equal(stateKind('overlay modal:palette results "cheque"'), 'search results', 'the palette is not an in-page search');
    assert.equal(stateKind('tooltip "Search (/)"'), 'tooltip');
    assert.equal(stateKind('toast "Copy question link"'), 'toast');
    assert.equal(stateKind('select fx-select "Second option"'), 'select');
    assert.equal(new Set(STATE_KINDS).size, STATE_KINDS.length);
    for (const kind of STATE_KINDS) assert.notEqual(kind, 'other');
});

test('rules 9 and 10 measure as drawn: the linear map of rotate, scale and transform', () => {
    const near = (a, b, msg) => assert.ok(Math.abs(a - b) < 1e-6, `${msg}: ${a} vs ${b}`);
    const box = (o) => ({ display: 'block', tag: 'DIV', scale: 'none', rotate: 'none', transform: 'none', ...o });
    assert.deepEqual(ownLinear(box({})), [1, 0, 0, 1]);
    near(linearScale(ownLinear(box({ scale: '0.948387' }))).y, 0.948387, 'the scale property (the hero canvas)');
    assert.deepEqual(linearScale(ownLinear(box({ scale: '-1 1' }))), { x: 1, y: 1 }, 'a mirror keeps its size');
    const two = linearScale(ownLinear(box({ scale: '1.2 0.8' })));
    near(two.x, 1.2, 'scale x');
    near(two.y, 0.8, 'scale y');
    near(linearScale(ownLinear(box({ scale: '50%' }))).x, 0.5, 'a percentage');
    near(linearScale(ownLinear(box({ transform: 'matrix(0.492404, 0.0868241, -0.0868241, 0.492404, 0, 0)' }))).y, 0.5, 'transform: scale(.5) rotate(10deg)');
    near(linearScale(ownLinear(box({ transform: 'matrix3d(0.9, 0, 0, 0, 0, 0.9, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1)' }))).x, 0.9, 'matrix3d');
    const turned = linearScale(ownLinear(box({ rotate: '16deg', scale: '0.5' })));
    near(turned.x, 0.5, 'a rotation keeps the length');
    near(turned.y, 0.5, 'a rotation keeps the length');
    near(linearScale(ownLinear(box({ rotate: 'x 60deg' }))).y, 0.5, 'a 3D turn about x foreshortens y');
    assert.deepEqual(ownLinear(box({ display: 'inline', tag: 'SPAN', scale: '2' })), [1, 0, 0, 1], 'an inline box cannot be transformed');
    near(linearScale(ownLinear(box({ display: 'inline', tag: 'svg', scale: '2' }))).x, 2, 'a replaced element can');
    assert.deepEqual(ownLinear(box({ display: 'contents', scale: '2' })), [1, 0, 0, 1]);
    // a label sized ÷ k inside a canvas drawn at k shows its own size
    const k = 0.948387;
    const drawn = linearScale(mulLinear(ownLinear(box({ scale: String(k) })), ownLinear(box({}))));
    near((15 / k) * drawn.y, 15, 'the counter-scaled callout shows 15 px');
    near(linearScale(mulLinear(ownLinear(box({ scale: '1.1' })), ownLinear(box({ scale: String(1 / 1.1) })))).x, 1, 'a pin scaled 1/k inside a canvas at k');
    assert.equal(drawnLength(8, 1), '8px');
    assert.equal(drawnLength(8, 0.9), '7.2px (8px at scale 0.9)');
    assert.equal(drawnLength(15.8163, 0.948387), '15px (15.8px at scale 0.948)');
});

test('advice 12 and 10: neutral ink, step numbers, item kinds', () => {
    for (const c of ['rgb(11, 11, 12)', 'rgb(63, 63, 70)', 'rgb(107, 107, 115)', 'rgb(161, 161, 170)', 'rgb(255, 255, 255)', 'rgba(107, 107, 115, 0.9)', 'oklch(0.5 0.01 280)']) assert.equal(neutralColor(c), true, c);
    for (const c of ['rgb(138, 106, 46)', 'rgb(201, 169, 107)', 'rgb(180, 35, 24)', 'rgb(47, 93, 160)', 'oklch(0.6 0.12 80)', 'red', '']) assert.equal(neutralColor(c), false, c);
    for (const t of ['1.', '2', '03)', ' 4. ', '١.', '12']) assert.equal(stepNumber(t), true, t);
    for (const t of ['Step 1', '1. Review', 'one', '2026x', '']) assert.equal(stepNumber(t), false, t);
    const card = itemKind('LI', ['card', 'card--default', 'card--pad-md', 'card--interactive', 'card--link', 'svc-card']);
    assert.equal(itemKind('li', ['card', 'card--default', 'card--pad-md', 'card--interactive', 'card--link', 'svc-card', 'svc-card--wide']), card, 'a BEM modifier is the same kind');
    assert.notEqual(itemKind('LI', ['card', 'card--sunken', 'card--pad-md', 'svc-closing']), card, 'the closing tile is another kind');
    assert.equal(itemKind('LI', ['timeline__step', 'is-done', 'is-current']), itemKind('LI', ['timeline__step', 'is-done']), 'state classes do not count');
    assert.equal(itemKind('LI', []), itemKind('LI', undefined));
    assert.notEqual(itemKind('LI', []), itemKind('ARTICLE', []));
});

test('set by: stylesheet text into style rules (nesting, layers, at-rules, comments, strings)', () => {
    const rules = cssRules(`
        @layer reset, base;
        @import "./x.css";
        /* .comment { color: red; } */
        @layer components {
            .timeline--full .timeline__list {
                gap: var(--s-2);
                & .timeline__dot { margin-block-start: calc((var(--lh-small) - var(--tl-dot)) / 2); }
                @media (min-width: 768px) {
                    & .timeline__dot { margin-block-start: 0; }
                }
            }
            .a::before { content: "{ ; }"; background: url(data:image/svg+xml;utf8,x); }
        }
        @font-face { font-family: X; src: url(x.woff2); }
        @keyframes pulse { from { opacity: 0; } to { opacity: 1; } }
        body,
        h1 { margin: 0 }
    `);
    const sel = rules.map((r) => r.selector);
    assert.deepEqual(sel, ['.timeline--full .timeline__list', '& .timeline__dot', '& .timeline__dot', '.a::before', 'body,h1']);
    assert.deepEqual(rules[0].decls, ['gap:var(--s-2)'], 'a rule keeps its own declarations, not its nested rules\'');
    assert.deepEqual(rules[1].parents, ['.timeline--full .timeline__list']);
    assert.equal(rules[1].layer, 'components');
    assert.deepEqual(rules[1].decls, ['margin-block-start:calc((var(--lh-small)-var(--tl-dot))/2)']);
    assert.deepEqual(rules[2].decls, ['margin-block-start:0'], 'a nested @media keeps the parent chain');
    assert.deepEqual(rules[2].parents, ['.timeline--full .timeline__list']);
    assert.deepEqual(rules[3].decls, ['content:"{;}"', 'background:url(data:image/svg+xml;utf8,x)'], 'strings and url() keep their braces and semicolons');
    assert.equal(rules[4].layer, '', 'outside @layer');
    assert.equal(normSelector(' .a  >  .b ,\n .c '), '.a>.b,.c');
    assert.equal(normDeclaration('Padding-Block-End : var(--safe-bottom) ;'), 'padding-block-end:var(--safe-bottom)');
    assert.equal(normDeclaration('not a declaration'), '');
});

test('set by: the file of a winning rule (one bundled sheet in dev and in a build)', () => {
    const index = {
        files: new Map([
            ['resources/css/d2/components/timeline.css', cssRules('@layer components { .timeline--full .timeline__list { & .timeline__dot { margin-block-start: calc(1px + 2px); } } }')],
            ['resources/css/d2/pages/inner.css', cssRules('@layer pages { .cta-card__status { & .hours-status__dot { margin-block-start: calc(1px + 2px); } } .odd { padding: 10px; } }')],
            ['resources/css/d2/base/base.css', cssRules('@layer base { body { font-size: var(--fs-body); } }')],
            ['resources/css/d2/pages/about.css', cssRules('@layer pages { .x { padding: 10px; } }')],
            ['resources/css/d2/sections/team.css', cssRules('@layer sections { .x { padding: 10px; } }')],
            ['resources/css/d2/styleguide.css', cssRules('@layer pages { .sg-x { gap: 10px; } }')],
        ]),
    };
    const dot = (parents) => ({ selector: '& .timeline__dot', parents, layer: 'components', decls: ['margin-block-start: calc(1px + 2px)'] });
    assert.equal(findRuleFile(index, dot(['.timeline--full .timeline__list'])), 'resources/css/d2/components/timeline.css');
    assert.equal(findRuleFile(index, { selector: '& .hours-status__dot', parents: ['.cta-card__status'], layer: 'pages', decls: ['margin-block-start: calc(1px + 2px)'] }), 'resources/css/d2/pages/inner.css');
    assert.equal(findRuleFile(index, { selector: 'body', layer: 'base', decls: ['font-size: var(--fs-body)'] }), 'resources/css/d2/base/base.css', 'the inherited body font size');
    assert.equal(findRuleFile(index, { selector: '.x', layer: 'sections', decls: ['padding: 10px'] }), 'resources/css/d2/sections/team.css', 'the layer decides between two files');
    assert.equal(findRuleFile(index, { selector: '.x', layer: 'utilities', decls: ['padding: 10px'] }), null, 'two files and no layer fits: unknown');
    assert.equal(findRuleFile(index, { selector: '.sg-x', layer: 'pages', decls: ['gap: 10px'], sheetPath: 'resources/css/d2/styleguide.css' }), 'resources/css/d2/styleguide.css', 'a separate entry sheet');
    assert.equal(findRuleFile(index, { selector: '.timeline--full .timeline__list .timeline__dot', layer: 'components', decls: ['margin-block-start:calc(1px + 2px)'] }), 'resources/css/d2/components/timeline.css', 'a build that flattened the nesting');
    assert.equal(findRuleFile(index, { selector: '.inpage', layer: '', decls: ['padding: 10px'] }), null, 'equal declarations of another selector are not this rule');
    assert.equal(findRuleFile(index, { selector: '.odd', layer: 'pages', decls: [] }), null, 'no declaration text: unknown');
    assert.equal(findRuleFile(null, dot([])), null);
});

test('set by: the winner of the cascade and its authored declarations', () => {
    const rule = (selector, props, extra = {}) => ({ rule: { selectorList: { text: selector }, origin: 'regular', style: { cssProperties: props }, ...extra } });
    const bar = rule('.bar', [{ name: 'padding-block-end', value: 'var(--safe-bottom)', text: 'padding-block-end: var(--safe-bottom);' }], { layers: [{ text: 'components' }] });
    const win = cssWinner({ matchedCSSRules: [bar] }, ['padding-bottom', 'padding-block-end']);
    assert.equal(win.rule, bar.rule);
    assert.equal(win.inherited, false);
    assert.deepEqual(winnerDeclarations(win, ['padding-bottom', 'padding-block-end']), ['padding-block-end: var(--safe-bottom)']);
    assert.equal(describeWinner(win, ['padding-bottom', 'padding-block-end']), '@layer components .bar { padding-block-end: var(--safe-bottom) }');
    const body = rule('body', [{ name: 'font-size', value: 'var(--fs-body)', text: 'font-size: var(--fs-body);' }], { layers: [{ text: 'base' }] });
    const inherited = cssWinner({ matchedCSSRules: [], inherited: [{ matchedCSSRules: [body] }] }, ['font-size'], null, true);
    assert.equal(inherited.inherited, true);
    assert.equal(cssSource({ matchedCSSRules: [], inherited: [{ matchedCSSRules: [body] }] }, ['font-size'], null, true), 'inherited: @layer base body { font-size: var(--fs-body) }');
    assert.equal(cssWinner({ matchedCSSRules: [] }, ['gap']), null);
});

test('areas: every stylesheet of Design 2 has exactly one area (AREA_FILES), the shared ones none', () => {
    const files = [];
    const walk = (dir) => {
        for (const name of readdirSync(join(ROOT, dir)).sort()) {
            const rel = `${dir}/${name}`;
            if (statSync(join(ROOT, rel)).isDirectory()) walk(rel);
            else if (name.endsWith('.css')) files.push(rel);
        }
    };
    walk('resources/css/d2');
    assert.ok(files.length > 50, 'the Design 2 stylesheets are read');
    for (const file of files) {
        const areas = Object.entries(AREA_FILES).filter(([, patterns]) => patterns.some((p) => globRegExp(p).test(file))).map(([a]) => a);
        if (SHARED_CSS.includes(file)) assert.deepEqual(areas, [], `${file} is shared: the location decides`);
        else assert.equal(areas.length, 1, `${file} belongs to exactly one area (found: ${areas.join(', ') || 'none'})`);
    }
    for (const area of Object.keys(AREA_FILES)) assert.ok(AREAS.includes(area), `${area} is an area of the report`);
    for (const [area, patterns] of Object.entries(AREA_FILES)) {
        for (const p of patterns) assert.ok(files.some((f) => globRegExp(p).test(f)), `${area}: ${p} matches a file`);
    }
    assert.equal(areaForFile('resources/css/d2/base/base.css'), 'platform');
    assert.equal(areaForFile('resources/css/d2/components/wizard.css'), 'home-b');
    assert.equal(areaForFile('resources/css/d2/sections/start.css'), 'home-a');
    assert.equal(areaForFile('resources/css/d2/sections/about.css'), 'home-b');
    assert.equal(areaForFile('resources/css/d2/components/date.css'), 'components');
    assert.equal(areaForFile('resources/css/d2/pages/inner.css'), 'inner');
    assert.equal(areaForFile('resources/css/d2/components/hours.css'), 'time-digits');
    assert.equal(areaForFile('resources/css/d2/components/tabbar.css'), 'footer-shell');
    assert.equal(areaForFile('resources/css/d2/styleguide.css'), null);
    assert.equal(areaForFile(null), null);
    assert.ok(globRegExp('a/{b,c}.css').test('a/c.css') && !globRegExp('a/{b,c}.css').test('a/d.css') && !globRegExp('a/*.css').test('a/b/c.css'));
});

test('set by: the real stylesheets find the body font size in base.css (platform)', () => {
    const index = buildCssIndex();
    assert.ok(index.files.has('resources/css/d2/base/base.css'));
    const body = index.files.get('resources/css/d2/base/base.css').find((r) => r.selector === 'body' && r.decls.some((d) => d.startsWith('font-size:')));
    assert.ok(body, 'base.css sets the body font size');
    const decl = body.decls.find((d) => d.startsWith('font-size:')).replace(':', ': ');
    const file = findRuleFile(index, { selector: 'body', layer: body.layer, decls: [decl] });
    assert.equal(file, 'resources/css/d2/base/base.css');
    assert.equal(areaForFile(file), 'platform', 'rule 9 "inherited: @layer base body" goes to platform');
});

test('summary: safe-area insets exempted, advice apart, violations by state kind, file and states in the report', () => {
    const results = [{
        route: 'p04-case-studies', locale: 'en', width: 390, path: '/en/case-studies/', status: 200, ok: true, info: {},
        records: [
            { rule: 10, area: 'footer-shell', selector: 'nav.tabbar', value: 'padding-block-end 34px', text: '', count: 9, exempt: 'safe-area', source: '.tabbar--safe { padding-block-end: var(--safe-bottom) }' },
            { rule: 9, area: 'platform', selector: 'p', value: 'font-size 18px', text: 't', count: 2, source: 'inherited: @layer base body { font-size: var(--fs-body) }', file: 'resources/css/d2/base/base.css' },
        ],
        advice: [{ pattern: 4, area: 'inner', selector: 'span.casefile__no', value: 'zero-padded numbering on something that is not a step', text: '01', count: 1 }],
        states: [
            { state: 'details and disclosures open', ok: true, records: [{ rule: 10, area: 'home-b', selector: 'span.timeline__dot', value: 'margin-block-start 5.5px', text: '', count: 12, state: 'details and disclosures open', source: '.x', file: 'resources/css/d2/components/timeline.css' }], advice: [{ pattern: 10, area: 'inner', selector: 'div.cs-files__list', value: '"Agree on the Next Step" on all 6 items', text: 'Agree on the Next Step', count: 1, state: 'details and disclosures open' }] },
            { state: 'tab "Employment"', ok: false, note: 'not shown', records: [], advice: [] },
        ],
    }];
    const opt = { ...parseArgs([]), locales: ['en'], routeFilter: null };
    const report = summarise(results, { approvedEllipses: new Set(), approvedSeparators: [] }, opt);
    assert.equal(report.summary.total, 14);
    assert.equal(report.summary.safeAreaExempt, 9);
    assert.deepEqual(report.summary.byState, { page: 2, disclosures: 12 });
    assert.deepEqual(report.summary.advisory, { 4: 1, 10: 1, 11: 0, 12: 0 });
    assert.deepEqual(report.summary.statesLinted, { disclosures: 1 });
    assert.equal(report.summary.statesNotLinted, 1);
    assert.equal(report.violations.find((v) => v.rule === 10).file, 'resources/css/d2/components/timeline.css');
    assert.deepEqual(report.pages[0].advice, { 4: 1, 10: 1 });
    assert.deepEqual(report.advisories.map((a) => [a.pattern, a.state]), [[4, 'page'], [10, 'details and disclosures open']]);
    const md = renderMarkdown(report, opt);
    assert.match(md, /Found in the first render: 2; only in a state: 12 \(disclosures 12\)/);
    assert.match(md, /Rule 10: 9 value\(s\) set by a safe-area inset/);
    assert.match(md, /## Advice: §3\.6 patterns without a rule of §7\.1 \(not counted\)[\s\S]*\| 4 \| Decorative numbering[^\n]*\| 1 \| 1 \| 1 \|/);
    assert.match(md, /### Advice 10\. The same metadata repeated on every item/);
    assert.match(md, /\| File \| Set by \|[\s\S]*`components\/timeline\.css`/);
    assert.match(md, /States linted: 1 \(disclosures 1\)/);
    assert.match(md, /States not linted: 1 \(p04-case-studies en 390: tab "Employment" \(not shown\)\)/);
    assert.doesNotMatch(md, /undefined|NaN/);
});
