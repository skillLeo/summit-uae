/**
 * Self-test of scripts/qa/docx-conformance.mjs: the normaliser, the word model, the matcher, the order chain, the
 * diagnosis of lines that are not found and the extra-string registry. No browser, no app.
 *
 *   node --test scripts/qa/docx-conformance.test.mjs        (npm run qa:docx:selftest)
 */
import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    bestChain,
    buildRegistry,
    buildWords,
    classify,
    comparePage,
    diagnose,
    findCandidates,
    indexWords,
    normalise,
    toWords,
} from './docx-conformance.mjs';

/** A synthetic capture: blocks of [text, options] nodes. Each block is one element; inline nodes share it. */
function capture(blocks, extra = {}) {
    const nodes = [];
    const els = [];
    blocks.forEach((block, b) => {
        els.push(`#b${b}`);
        for (const item of block) {
            const [t, o = {}] = Array.isArray(item) ? item : [item];
            nodes.push({ t, b, L: b, p: b, v: o.v ?? 'v', part: o.part ?? 'main', prop: Boolean(o.prop), pend: false, ill: Boolean(o.ill), clamp: Boolean(o.clamp) });
        }
    });
    return { nodes, els, attrs: [], data: [], wa: [], title: '', description: '', ...extra };
}

const line = (text, o = {}) => ({ text, kind: 'visible', part: 'page', group: null, field: o.field ?? null, file: 'T.docx', i: 0, ...o });
const page = (lines) => ({ slug: 't', lines });
const emptyRegistry = buildRegistry({});

test('normalise folds quotes, spaces, invisible marks and digits, and keeps case, dashes and ellipses', () => {
    assert.equal(normalise('  each party’s “role”\u00A0and\u2009«this»  '), 'each party\'s "role" and "this"');
    assert.equal(normalise('\u200F+971\u00A052\u200B 466 6191\u2069'), '+971 52 466 6191');
    assert.equal(normalise('١. المادة ۲'), '1. المادة 2');
    assert.equal(normalise('non\u2011payment'), 'non-payment');
    assert.equal(normalise('© 2026 Summit'), normalise('©\u00A02031 Summit'));
    assert.notEqual(normalise('Request a Legal Consultation'), normalise('Request a legal consultation'));
    assert.notEqual(normalise('9 am – 6 pm'), normalise('9 am - 6 pm'));
    assert.notEqual(normalise('Submitting your request..'), normalise('Submitting your request…'));
    assert.deepEqual(toWords(' a\tb\n c '), ['a', 'b', 'c']);
});

test('buildWords joins inline nodes, separates blocks and line breaks, and records node cuts', () => {
    const words = buildWords(capture([['I agree with the ', 'Privacy Policy', '.'], ['Next']]).nodes);
    assert.deepEqual(words.map((w) => w.w), ['I', 'agree', 'with', 'the', 'Privacy', 'Policy.', 'Next']);
    assert.deepEqual(words[5].cuts, [6]);
    assert.equal(words[6].b, 1);
    const br = buildWords(capture([['Guidance.', '\n', 'Practical']]).nodes);
    assert.deepEqual(br.map((w) => w.w), ['Guidance.', 'Practical']);
});

test('a short label matches its own element, never inside a paragraph', () => {
    const words = buildWords(capture([['We work in Arabic, English and Russian.'], ['English']]).nodes);
    const index = indexWords(words);
    const found = findCandidates(['English'], words, index);
    assert.equal(found.length, 1);
    assert.equal(words[found[0].s].b, 1);
});

test('a line ending where a text node ends inside a word still matches (the consent label link)', () => {
    const words = buildWords(capture([['I agree in accordance with the ', 'Privacy Policy', '.']]).nodes);
    const index = indexWords(words);
    assert.equal(findCandidates(toWords('Privacy Policy'), words, index).length, 1);
    assert.equal(findCandidates(toWords('I agree in accordance with the Privacy Policy.'), words, index).length, 1);
    assert.equal(findCandidates(toWords('Privacy'), words, index).length, 0, 'not a node edge after "Privacy"');
});

test('a paragraph line may start or end at a sentence boundary inside one text node', () => {
    const words = buildWords(capture([['First sentence here. Second sentence follows.']]).nodes);
    const index = indexWords(words);
    assert.equal(findCandidates(toWords('Second sentence follows.'), words, index).length, 1);
    assert.equal(findCandidates(toWords('First sentence here.'), words, index).length, 1);
    assert.equal(findCandidates(toWords('sentence here.'), words, index).length, 0);
});

test('bestChain keeps the longest increasing chain and prefers visible matches', () => {
    // Page positions: A=0, C=1, B=2, D=3. DOCX order A, B, C, D: B or C is out of order.
    const chain = bestChain([{ cands: [{ s: 0, e: 0 }] }, { cands: [{ s: 2, e: 2 }] }, { cands: [{ s: 1, e: 1 }] }, { cands: [{ s: 3, e: 3 }] }], 4);
    assert.equal(chain.size, 3);
    assert.ok(chain.has(0) && chain.has(3));
    // Two identical lines and three occurrences: distinct, increasing, visible ones first.
    const repeat = bestChain([{ cands: [{ s: 0, e: 0, vis: false }, { s: 1, e: 1, vis: true }, { s: 2, e: 2, vis: true }] }, { cands: [{ s: 0, e: 0, vis: false }, { s: 1, e: 1, vis: true }, { s: 2, e: 2, vis: true }] }], 3);
    assert.deepEqual([repeat.get(0).s, repeat.get(1).s], [1, 2]);
});

test('diagnose: truncated (ellipsis or cut), altered, case, merged and missing', () => {
    const run = (blocks, text) => {
        const words = buildWords(capture(blocks).nodes);
        return diagnose(toWords(text), words, indexWords(words));
    };
    const long = 'We review outstanding debts, unpaid invoices, dishonoured cheques and acknowledgements of debt.';
    assert.equal(run([['We review outstanding debts, unpaid invoices, dishon…']], long).status, 'truncated');
    assert.equal(run([['We review outstanding debts, unpaid invoices,'], ['Next card']], long).status, 'truncated');
    assert.equal(run([['We review outstanding debts, unpaid invoices, bounced cheques and acknowledgements of debt.']], long).status, 'altered');
    assert.equal(run([['Other matter']], 'Other Matter').status, 'altered');
    assert.equal(run([['Notice: We review outstanding debts, unpaid invoices, dishonoured cheques and acknowledgements of debt. More']], long).status, 'merged');
    assert.equal(run([['Something else entirely']], long).status, 'missing');
    assert.equal(run([['Choose a sample document']], 'Choose a Language').status, 'missing', 'two shared words are not an alteration');
});

test('the registry: exact strings, placeholders, plural forms, lists and categories', () => {
    const reg = buildRegistry({
        approved: ['Explore This Service'],
        hosts: ['uaelegislation.gov.ae'],
        ui: ['Arabic', 'English', 'Russian'],
        lang: ['Design 2'],
        proposed: ['Step :current of :total: :name', '{1} UAE time is :n hour behind yours|[2,*] UAE time is :n hours behind yours', ':n. :text'],
    });
    assert.equal(classify('Explore This Service', reg), 'approved');
    assert.equal(classify('uaelegislation.gov.ae', reg), 'hosts');
    assert.equal(classify('Step 1 of 5: You', reg), 'proposed');
    assert.equal(classify('UAE time is 3 hours behind yours', reg), 'proposed');
    assert.equal(classify('Arabic, English, Russian', reg), 'ui');
    assert.equal(classify('23. Anything at all', reg), null, 'a template of placeholders only matches nothing');
    assert.equal(classify('Hand-written label', reg), null);
    assert.equal(classify('1 / 2', reg), 'no-letters');
});

test('comparePage: order, embedded lines, hidden matches, scope and extra strings', () => {
    const lines = [
        line('Home', { kind: 'group', group: 'nav', part: 'shared-header' }),
        line('Our Services'),
        line('First paragraph of the page.'),
        line('Second paragraph of the page.'),
        line('I agree to the terms in accordance with the Privacy Policy.'),
        line('Privacy Policy'),
        line('Hidden answer text here.'),
        line('Not on the page at all'),
        line('Open menu', { kind: 'label' }),
        line('Please enter your name.', { kind: 'state' }),
        line('Title | Brand', { kind: 'seo-title' }),
        line('Hello Summit', { kind: 'wa-draft' }),
    ];
    const cap = capture(
        [
            [['Home', { part: 'header' }]],
            ['Second paragraph of the page.'],
            ['Our Services'],
            ['First paragraph of the page.'],
            ['I agree to the terms in accordance with the ', 'Privacy Policy', '.'],
            [['Hidden answer text here.', { v: 'collapsed' }]],
            [['Step 1 of 5: You', { prop: true }]],
            ['Read more about us'],
            ['Explore This Service'],
        ],
        {
            attrs: [{ name: 'aria-label', value: 'Open menu', el: 0 }],
            data: ['{"missing_name":"Please enter your name."}', 'Please enter your name.'],
            title: 'Title | Brand',
            wa: [{ href: 'https://wa.me/1?text=Hello%20Summit', text: 'Hello Summit' }],
        },
    );
    const reg = buildRegistry({ approved: ['Explore This Service'], proposed: ['Step :current of :total: :name'] });
    const result = comparePage(page(lines), cap, reg);
    const status = Object.fromEntries(result.lines.map((l) => [l.text, l.status]));
    assert.equal(status.Home, 'ok');
    assert.equal(status['Our Services'], 'ok');
    assert.equal(status['First paragraph of the page.'], 'ok');
    assert.equal(status['Second paragraph of the page.'], 'out-of-order');
    assert.equal(status['Privacy Policy'], 'ok');
    assert.equal(result.lines.find((l) => l.text === 'Privacy Policy').embedded, true);
    assert.equal(status['Hidden answer text here.'], 'ok');
    assert.equal(result.lines.find((l) => l.text === 'Hidden answer text here.').hidden, 'collapsed');
    assert.equal(status['Not on the page at all'], 'missing');
    assert.equal(status['Open menu'], 'ok');
    assert.equal(status['Please enter your name.'], 'ok');
    assert.equal(status['Title | Brand'], 'ok');
    assert.equal(status['Hello Summit'], 'ok');
    const verdicts = Object.fromEntries(result.extras.map((x) => [x.text, x.verdict]));
    assert.equal(verdicts['Step 1 of 5: You'], 'proposed (marked)');
    assert.equal(verdicts['Read more about us'], 'unregistered');
    assert.equal(verdicts['Explore This Service'], 'approved');
    assert.equal(result.summary.missing, 1);
    assert.equal(result.summary.outOfOrder, 1);
    assert.equal(result.summary.unregistered, 1);
});

test('comparePage: a page line found only in the header is missing from the page', () => {
    const cap = capture([[['Practice Areas', { part: 'header' }]], ['Body text here.']]);
    const result = comparePage(page([line('Practice Areas'), line('Body text here.')]), cap, emptyRegistry);
    const pa = result.lines.find((l) => l.text === 'Practice Areas');
    assert.equal(pa.status, 'missing');
    assert.match(pa.detail, /outside its part/);
});

test('comparePage: a CSS-clamped summary is truncated; illustration text and single letters are exempt', () => {
    const cap = capture([[['A complete approved summary sentence.', { clamp: true }]], [['Fine print on a sample', { ill: true }]], ['S']]);
    const result = comparePage(page([line('A complete approved summary sentence.')]), cap, emptyRegistry);
    assert.equal(result.lines[0].status, 'truncated');
    assert.equal(result.extras.length, 0);
});

test('comparePage: an unregistered run is split into its blocks, then its text nodes, before it is reported', () => {
    // One link (paragraph unit 0) holding a title block and a summary block whose middle word is a <bdi> node.
    const node = (t, b, p) => ({ t, b, L: 0, p, v: 'hidden', part: 'other', prop: false, pend: false, ill: false, clamp: false });
    const cap = {
        nodes: [node('Card Title', 1, 1), node('A summary about ', 2, 2), node('USDT', 2, 3), node(' disputes.', 2, 2), node('Hand-made label', 4, 4)],
        els: ['a', 'span.title', 'span.summary', 'bdi', 'span.extra'],
        attrs: [],
        data: [],
        wa: [],
        title: '',
        description: '',
    };
    const reg = buildRegistry({ approved: ['Card Title', 'A summary about USDT disputes.'], proposed: ['USDT'] });
    const result = comparePage(page([]), cap, reg);
    const verdicts = Object.fromEntries(result.extras.map((x) => [x.text, x.verdict]));
    assert.equal(verdicts['Card Title'], 'approved');
    assert.equal(verdicts['A summary about USDT disputes.'], 'approved');
    assert.equal(verdicts['Hand-made label'], 'unregistered');
    assert.equal(result.extras.length, 3);
});
