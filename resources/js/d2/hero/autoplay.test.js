import { describe, expect, it, vi } from 'vitest';
import { createAutoplay, DWELL_MS } from './autoplay.js';

// The hero autoplay (the owner's decision; WCAG 2.2.2): a dwell after each settle, paused by holds, stopped for good by
// a manual choice, played again by the toggle.
function fakeTimer() {
    const timers = [];
    const start = (ms) => {
        let resolve;
        const t = {
            ms,
            state: 'running',
            finished: new Promise((r) => { resolve = r; }),
            pause() { if (t.state === 'running') t.state = 'paused'; },
            play() { if (t.state === 'paused') t.state = 'running'; },
            cancel() { t.state = 'cancelled'; },
            end() { t.state = 'done'; resolve(); },
        };
        timers.push(t);
        return t;
    };
    return { timers, start };
}
const flush = () => new Promise((r) => setTimeout(r, 0));

describe('hero autoplay', () => {
    it('waits for the document to settle, then dwells and advances', async () => {
        const { timers, start } = fakeTimer();
        const onAdvance = vi.fn();
        const auto = createAutoplay({ playing: true, startTimer: start, onAdvance });
        expect(timers).toHaveLength(0);
        auto.settle();
        expect(timers).toHaveLength(1);
        expect(timers[0].ms).toBe(DWELL_MS);
        timers[0].end();
        await flush();
        expect(onAdvance).toHaveBeenCalledTimes(1);
        // The next document settles: a fresh dwell.
        auto.unsettle();
        auto.settle();
        expect(timers).toHaveLength(2);
    });

    it('pauses the dwell where it is while any hold is on, and resumes it', () => {
        const { timers, start } = fakeTimer();
        const auto = createAutoplay({ playing: true, startTimer: start, onAdvance: () => {} });
        auto.settle();
        auto.hold('hover');
        expect(timers[0].state).toBe('paused');
        auto.hold('focus');
        auto.release('hover');
        expect(timers[0].state).toBe('paused');
        expect(auto.running).toBe(false);
        auto.release('focus');
        expect(timers[0].state).toBe('running');
        expect(auto.running).toBe(true);
        expect(timers).toHaveLength(1);
    });

    it('does not start a dwell while held, and starts it once the holds are gone', () => {
        const { timers, start } = fakeTimer();
        const auto = createAutoplay({ playing: true, startTimer: start, onAdvance: () => {} });
        auto.hold('offscreen');
        auto.settle();
        expect(timers).toHaveLength(0);
        auto.release('offscreen');
        expect(timers).toHaveLength(1);
    });

    it('stops for good on a manual choice; the toggle plays again', async () => {
        const { timers, start } = fakeTimer();
        const onAdvance = vi.fn();
        const onChange = vi.fn();
        const auto = createAutoplay({ playing: true, startTimer: start, onAdvance, onChange });
        auto.settle();
        auto.stop();
        expect(timers[0].state).toBe('cancelled');
        expect(auto.playing).toBe(false);
        expect(onChange).toHaveBeenLastCalledWith({ playing: false, running: false });
        // Settling again (the manual choice's document) never starts a dwell.
        auto.unsettle();
        auto.settle();
        expect(timers).toHaveLength(1);
        auto.toggle();
        expect(auto.playing).toBe(true);
        expect(timers).toHaveLength(2);
        timers[1].end();
        await flush();
        expect(onAdvance).toHaveBeenCalledTimes(1);
    });

    it('never plays when it starts stopped (reduced motion, the styleguide) until the toggle', () => {
        const { timers, start } = fakeTimer();
        const auto = createAutoplay({ playing: false, startTimer: start, onAdvance: () => {} });
        auto.settle();
        expect(timers).toHaveLength(0);
        auto.play();
        expect(timers).toHaveLength(1);
    });

    it('ignores a dwell that ended after it was cancelled', async () => {
        const { timers, start } = fakeTimer();
        const onAdvance = vi.fn();
        const auto = createAutoplay({ playing: true, startTimer: start, onAdvance });
        auto.settle();
        auto.unsettle();
        timers[0].end();
        await flush();
        expect(onAdvance).not.toHaveBeenCalled();
        auto.destroy();
    });
});
