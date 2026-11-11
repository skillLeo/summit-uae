// Legal Knowledge Centre phone guide cards (components/resources.js + resources-kit.js): the disclosure
// labels ("Read the full guide" / "Hide the full guide") are interface text, never guide text, so the
// search must not find every guide for "full" or "guide". searchableText() is checked on a minimal node
// tree (Vitest runs in Node, without a DOM).
import { describe, expect, it } from 'vitest';
import { guideText, searchableText, visibleGuides } from '../resources-kit.js';

const text = (value) => ({ nodeType: 3, nodeValue: value, childNodes: [] });
function el(attrs, ...children) {
    const node = {
        nodeType: 1,
        attrs,
        childNodes: children.map((c) => (typeof c === 'string' ? text(c) : c)),
        matches(selector) {
            const m = /^\[([\w-]+)\]$/.exec(selector);
            return !!m && Object.hasOwn(this.attrs, m[1]);
        },
    };
    Object.defineProperty(node, 'textContent', {
        get() {
            return this.childNodes.map((c) => (c.nodeType === 3 ? c.nodeValue : c.textContent)).join('');
        },
    });
    return node;
}

const article = (title, summary, body) =>
    el(
        {},
        el({ class: 'kc-guide__card' }, el({}, title), el({}, summary), el({ 'data-kc-toggle': '' }, el({}, 'Read the full guide'), el({}, 'Hide the full guide'))),
        el({ 'data-kc-body': '' }, body),
    );

describe('searchableText', () => {
    it('is the text content without the excluded subtrees', () => {
        const node = article('Unpaid Salary', 'Identify the months.', 'Keep payslips.');
        expect(searchableText(node, '[data-kc-toggle]')).toBe('Unpaid SalaryIdentify the months.Keep payslips.');
        expect(searchableText(node, '[data-kc-toggle]')).not.toContain('full guide');
    });

    it('is the plain text content without an exclusion, and empty without a node', () => {
        const node = article('A', 'B', 'C');
        expect(searchableText(node)).toBe(node.textContent);
        expect(searchableText(null, '[data-kc-toggle]')).toBe('');
    });

    it('keeps the disclosure out of the filter: "full" no longer matches every guide', () => {
        const guides = [
            { code: 'G01', topic: 'gc-01', text: guideText(searchableText(article('Unpaid Salary', 'Salary records.', 'Payslips.'), '[data-kc-toggle]')) },
            { code: 'G08', topic: 'gc-03', text: guideText(searchableText(article('Legal Translation', 'Check the full names.', 'Attestation.'), '[data-kc-toggle]')) },
        ];
        expect(visibleGuides(guides, { query: 'full' })).toEqual(['G08']);
        expect(visibleGuides(guides, { query: 'hide' })).toEqual([]);
        expect(visibleGuides(guides, { query: 'payslips' })).toEqual(['G01']);
    });
});
