import { describe, expect, it } from 'vitest';
import { usedNames, wantedFiles } from './lazy-components.js';

/** A tiny stand-in for a document: elements with an x-data value, and templates with their own content. */
const el = (xData) => ({ getAttribute: (name) => (name === 'x-data' ? xData : null) });
const scope = (xDatas, templates = []) => ({
    querySelectorAll: (selector) => (selector === '[x-data]' ? xDatas.map(el) : templates.map((content) => ({ content }))),
});

const MAP = {
    './components/faq.js': { names: ['d2Faq'], always: false },
    './components/anatomy.js': { names: ['d2Anatomy', 'd2DocPins'], always: false },
    './components/tabs.js': { names: ['d2Tabs'], always: false },
    './components/upload-checks.js': { names: [], always: false },
    './components/wiring.js': { names: [], always: true },
    './components/megamenu.js': { names: ['d2Megamenu'], always: false },
};
const LAZY = ['./components/anatomy.js', './components/faq.js', './components/tabs.js', './components/upload-checks.js', './components/wiring.js'];

describe('lazy components', () => {
    it('reads the x-data names of a page, templates included, ignoring empty scopes', () => {
        const page = scope(['d2Faq', ' d2Tabs ', '', 'd2Megamenu'], [scope(['d2DocPins'], [scope(['d2Inner'])])]);
        expect([...usedNames(page)].sort()).toEqual(['d2DocPins', 'd2Faq', 'd2Inner', 'd2Megamenu', 'd2Tabs']);
        expect(usedNames(null).size).toBe(0);
    });

    it('loads only the files whose names the page uses, plus page wiring; the shell needs nothing', () => {
        expect(wantedFiles(LAZY, MAP, new Set(['d2Faq', 'd2Megamenu']))).toEqual(['./components/faq.js', './components/wiring.js']);
        expect(wantedFiles(LAZY, MAP, new Set(['d2DocPins']))).toEqual(['./components/anatomy.js', './components/wiring.js']);
        expect(wantedFiles(LAZY, MAP, new Set())).toEqual(['./components/wiring.js']);
        // A library file (no name, no default export) is never fetched on its own.
        expect(wantedFiles(LAZY, MAP, new Set(['d2Tabs']))).not.toContain('./components/upload-checks.js');
    });

    it('loads every component when the page uses a name the map does not know (never a dead component)', () => {
        expect(wantedFiles(LAZY, MAP, new Set(['d2Faq', 'd2Brandnew']))).toEqual(LAZY);
        expect(wantedFiles(LAZY, {}, new Set(['d2Faq']))).toEqual(LAZY);
    });

    it('survives missing input', () => {
        expect(wantedFiles(undefined, undefined, undefined)).toEqual([]);
        expect(wantedFiles(LAZY, null, new Set())).toEqual([]);
    });
});
