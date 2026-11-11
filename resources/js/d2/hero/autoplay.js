/**
 * The hero's autoplay (the owner's decision, which amends brief D17; WCAG 2.2.2 Pause, Stop, Hide). No DOM: the stage
 * passes the timer and the callbacks (Vitest: autoplay.test.js).
 *
 *   playing   the visitor's intent: on at first on the home hero (never under reduced motion, never on the
 *             styleguide's specimens, never without JavaScript), off for good after any manual choice (stop()), back
 *             on with the toggle (play()).
 *   holds     reasons to wait without changing that intent: the pointer over the stage or the pager ("hover"), focus
 *             inside the hero ("focus"), the hero off screen ("offscreen"), the tab hidden ("hidden"), a switch still
 *             running ("busy"). While any hold is on, the dwell timer is paused where it is (the pager's fill stops
 *             with it) and resumes from there.
 *   dwell     once the document on top has settled and its callouts are drawn, settle() starts a dwell of `dwell`
 *             ms (the pager fills the shown segment over it: startTimer(ms) returns that animation's handle); when it
 *             ends, onAdvance() brings up the next document, which settles again, and so on.
 * Timer handle: { pause(), play(), cancel(), finished: Promise } (a WAAPI Animation fits).
 */
export const DWELL_MS = 2800;

/**
 * @param {{
 *   dwell?: number,
 *   playing?: boolean,
 *   startTimer: (ms: number) => { pause(): void, play(): void, cancel(): void, finished: Promise<unknown> } | null,
 *   onAdvance: () => void,
 *   onChange?: (state: { playing: boolean, running: boolean }) => void,
 * }} options
 */
export function createAutoplay({ dwell = DWELL_MS, playing = false, startTimer, onAdvance, onChange = () => {} }) {
    const holds = new Set();
    let intent = Boolean(playing);
    let timer = null;
    let due = false; // the document on top has settled: a dwell may run
    let destroyed = false;

    const running = () => intent && due && holds.size === 0;
    const report = () => onChange({ playing: intent, running: running() });

    function clearTimer() {
        if (!timer) return;
        const t = timer;
        timer = null;
        try {
            t.cancel();
        } catch {
            /* already gone */
        }
    }

    function sync() {
        if (destroyed) return;
        if (!intent || !due) {
            clearTimer();
        } else if (holds.size) {
            timer?.pause();
        } else if (timer) {
            timer.play();
        } else {
            const t = startTimer(dwell);
            if (t) {
                timer = t;
                t.finished.then(
                    () => {
                        if (timer !== t || destroyed) return;
                        timer = null;
                        due = false;
                        report();
                        if (intent) onAdvance();
                    },
                    () => {},
                );
            }
        }
        report();
    }

    return {
        /** The document on top has settled (its callouts are drawn): start (or keep) the dwell. */
        settle() {
            due = true;
            sync();
        },
        /** A switch starts: no dwell until the next settle(). */
        unsettle() {
            due = false;
            clearTimer();
            report();
        },
        hold(reason) {
            if (holds.has(reason)) return;
            holds.add(reason);
            sync();
        },
        release(reason) {
            if (!holds.delete(reason)) return;
            sync();
        },
        /** The toggle's play: the samples play again (from a fresh dwell when the document has settled). */
        play() {
            if (intent) return;
            intent = true;
            sync();
        },
        /** A manual choice or the toggle's pause: stopped until play(). */
        stop() {
            if (!intent && !timer) return;
            intent = false;
            sync();
        },
        toggle() {
            if (intent) this.stop();
            else this.play();
        },
        get playing() {
            return intent;
        },
        get running() {
            return running();
        },
        get holds() {
            return [...holds];
        },
        destroy() {
            destroyed = true;
            clearTimer();
        },
    };
}
