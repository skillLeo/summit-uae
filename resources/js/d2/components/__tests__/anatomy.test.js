import { describe, expect, it } from 'vitest';
import { hotspotBox, parseCallouts, stepIndex } from '../anatomy.js';

describe('stepIndex (toolbar keys of the anatomy chips)', () => {
    it('moves forward and back in reading order, wrapping around', () => {
        expect(stepIndex('ArrowRight', 0, 5, 1)).toBe(1);
        expect(stepIndex('ArrowRight', 4, 5, 1)).toBe(0);
        expect(stepIndex('ArrowLeft', 0, 5, 1)).toBe(4);
        expect(stepIndex('ArrowDown', 2, 5, 1)).toBe(3);
        expect(stepIndex('ArrowUp', 2, 5, 1)).toBe(1);
    });

    it('mirrors the horizontal arrows in right-to-left pages', () => {
        expect(stepIndex('ArrowLeft', 0, 5, -1)).toBe(1);
        expect(stepIndex('ArrowRight', 0, 5, -1)).toBe(4);
        expect(stepIndex('ArrowDown', 0, 5, -1)).toBe(1);
    });

    it('jumps to the ends with Home and End', () => {
        expect(stepIndex('Home', 3, 6, 1)).toBe(0);
        expect(stepIndex('End', 1, 6, -1)).toBe(5);
    });

    it('ignores every other key and empty groups', () => {
        expect(stepIndex('Enter', 1, 5, 1)).toBe(-1);
        expect(stepIndex(' ', 1, 5, 1)).toBe(-1);
        expect(stepIndex('Tab', 1, 5, 1)).toBe(-1);
        expect(stepIndex('ArrowRight', 0, 0, 1)).toBe(-1);
    });
});

describe('parseCallouts (data-callouts on a document root)', () => {
    const valid = { id: 'figures', x: 83.7, y: 62, label: 'Amount in words and figures', side: 'end' };

    it('reads the shared contract', () => {
        expect(parseCallouts(JSON.stringify([valid]))).toEqual([valid]);
    });

    it('never throws on malformed input', () => {
        expect(parseCallouts('')).toEqual([]);
        expect(parseCallouts(undefined)).toEqual([]);
        expect(parseCallouts('{not json')).toEqual([]);
        expect(parseCallouts('{"id":"a"}')).toEqual([]);
    });

    it('drops entries outside the contract', () => {
        const list = [
            valid,
            { ...valid, x: 120 },
            { ...valid, y: -1 },
            { ...valid, side: 'left' },
            { ...valid, label: 3 },
            { ...valid, x: '50' },
            null,
        ];
        expect(parseCallouts(JSON.stringify(list))).toEqual([valid]);
    });
});

describe('hotspotBox (the hotspot button of a field over the document, D28)', () => {
    const doc = { left: 100, top: 50, width: 400, height: 200 };
    const field = { left: 200, top: 100, width: 100, height: 20 };

    it('pads the field like its outline (5 x 3 document px, times the scale) and measures in % of the document', () => {
        expect(hotspotBox(field, doc, { scale: 1 })).toEqual({ x: 23.75, y: 23.5, w: 27.5, h: 13 });
        expect(hotspotBox(field, doc, { scale: 2 })).toEqual({ x: 22.5, y: 22, w: 30, h: 16 });
    });

    it('measures x from the inline-start edge, so right-to-left pages mirror it', () => {
        // the field ends 200 px before the document's right edge, plus the 5 px pad
        expect(hotspotBox(field, doc, { scale: 1, rtl: true })).toEqual({ x: 48.75, y: 23.5, w: 27.5, h: 13 });
        const ltr = hotspotBox(field, doc, { scale: 1 });
        const rtl = hotspotBox(field, doc, { scale: 1, rtl: true });
        expect(ltr.x + ltr.w + rtl.x).toBeCloseTo(100, 5);
    });

    it('returns null for a document or a field that is not drawn', () => {
        expect(hotspotBox(field, { ...doc, width: 0 })).toBeNull();
        expect(hotspotBox({ ...field, height: 0 }, doc)).toBeNull();
        expect(hotspotBox(null, doc)).toBeNull();
        expect(hotspotBox(field, undefined)).toBeNull();
    });
});
