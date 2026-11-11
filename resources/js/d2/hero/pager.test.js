import { describe, expect, it } from 'vitest';
import { keyTarget, toggleState } from './pager.js';

// Hero pager (Design 2.1 D17, amended by the owner's autoplay): ← / → step through the pile and wrap around (mirrored
// in RTL), Home / End jump to the ends; previous / next are always enabled.
describe('hero pager keys', () => {
    const desktop = ['cheque', 'contract', 'tenancy', 'transfer', 'messages', 'notice'];
    const phone = ['cheque', 'contract', 'tenancy'];

    it('steps with the arrows in reading direction and wraps around at both ends', () => {
        expect(keyTarget('ArrowRight', 'cheque', desktop, 1)).toBe('contract');
        expect(keyTarget('ArrowLeft', 'contract', desktop, 1)).toBe('cheque');
        expect(keyTarget('ArrowLeft', 'cheque', desktop, 1)).toBe('notice');
        expect(keyTarget('ArrowRight', 'notice', desktop, 1)).toBe('cheque');
    });

    it('mirrors the arrows in RTL (← is next)', () => {
        expect(keyTarget('ArrowLeft', 'cheque', desktop, -1)).toBe('contract');
        expect(keyTarget('ArrowRight', 'contract', desktop, -1)).toBe('cheque');
        expect(keyTarget('ArrowRight', 'cheque', desktop, -1)).toBe('notice');
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
});

describe('hero pager toggle', () => {
    const labels = { pause: 'Pause the samples', play: 'Play the samples' };

    it('names what a press does: pause while playing, play while stopped', () => {
        expect(toggleState(true, labels)).toEqual({ state: 'playing', label: 'Pause the samples' });
        expect(toggleState(false, labels)).toEqual({ state: 'stopped', label: 'Play the samples' });
    });
});
