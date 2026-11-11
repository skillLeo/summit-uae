import { describe, expect, it } from 'vitest';
import { edges, keyTarget } from './pager.js';

// Hero pager (Design 2.1 D17): ← / → step without wrap-around (mirrored in RTL), Home / End jump to the ends; the
// step that leads nowhere is disabled on the first and the last document.
describe('hero pager keys', () => {
    const desktop = ['cheque', 'contract', 'tenancy', 'transfer', 'messages', 'notice'];
    const phone = ['cheque', 'contract', 'tenancy'];

    it('steps with the arrows in reading direction, never wrapping', () => {
        expect(keyTarget('ArrowRight', 'cheque', desktop, 1)).toBe('contract');
        expect(keyTarget('ArrowLeft', 'contract', desktop, 1)).toBe('cheque');
        expect(keyTarget('ArrowLeft', 'cheque', desktop, 1)).toBe('cheque');
        expect(keyTarget('ArrowRight', 'notice', desktop, 1)).toBe('notice');
    });

    it('mirrors the arrows in RTL (← is next)', () => {
        expect(keyTarget('ArrowLeft', 'cheque', desktop, -1)).toBe('contract');
        expect(keyTarget('ArrowRight', 'contract', desktop, -1)).toBe('cheque');
        expect(keyTarget('ArrowRight', 'cheque', desktop, -1)).toBe('cheque');
    });

    it('jumps to the ends with Home and End, on the list of the current stage', () => {
        expect(keyTarget('End', 'cheque', desktop)).toBe('notice');
        expect(keyTarget('Home', 'transfer', desktop)).toBe('cheque');
        expect(keyTarget('End', 'cheque', phone)).toBe('tenancy');
    });

    it('ignores other keys and starts from the first document when the active one is not listed', () => {
        expect(keyTarget('Enter', 'cheque', desktop)).toBeNull();
        expect(keyTarget('Tab', 'cheque', desktop)).toBeNull();
        expect(keyTarget('ArrowRight', 'notice', phone)).toBe('contract');
        expect(keyTarget('End', 'cheque', [])).toBeNull();
    });

    it('disables previous on the first document and next on the last one', () => {
        expect(edges(0, 6)).toEqual({ prev: true, next: false });
        expect(edges(2, 6)).toEqual({ prev: false, next: false });
        expect(edges(5, 6)).toEqual({ prev: false, next: true });
        expect(edges(-1, 6)).toEqual({ prev: true, next: true });
    });
});
