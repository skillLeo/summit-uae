/**
 * Cloudflare Turnstile for the consultation wizard (the endpoint verifies its token: docs/enquiries.md).
 * The script is the page's only third-party request (CSP allows challenges.cloudflare.com), so it is
 * loaded late: when the visitor reaches the review step, never on page load. The widget runs
 * "interaction-only" (invisible unless Cloudflare asks the visitor to tick it).
 *
 * createTurnstile({ siteKey, locale, container, onChange }) → { load(), token(), reset(), waitForToken(ms) }
 * onChange(state) reports: 'loading' | 'ready' (a token) | 'interactive' (the visitor must act) |
 * 'expired' | 'error' (could not load or verify).
 */
const SCRIPT = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=';
const READY = 'd2TurnstileReady';
const LOAD_TIMEOUT = 12000;

let scriptPromise = null;

function loadScript() {
    if (window.turnstile) return Promise.resolve(window.turnstile);
    if (scriptPromise) return scriptPromise;
    scriptPromise = new Promise((resolve, reject) => {
        const timer = window.setTimeout(() => fail(new Error('Turnstile timed out')), LOAD_TIMEOUT);
        const script = document.createElement('script');
        function fail(error) {
            window.clearTimeout(timer);
            script.remove();
            scriptPromise = null;
            reject(error);
        }
        window[READY] = () => {
            window.clearTimeout(timer);
            if (window.turnstile) resolve(window.turnstile);
            else fail(new Error('Turnstile missing'));
        };
        script.src = SCRIPT + READY;
        script.async = true;
        script.defer = true;
        const nonce = document.querySelector('meta[property="csp-nonce"]')?.nonce;
        if (nonce) script.nonce = nonce;
        script.addEventListener('error', () => fail(new Error('Turnstile blocked')));
        document.head.append(script);
    });
    return scriptPromise;
}

export function createTurnstile({ siteKey, locale, container, onChange = () => {} }) {
    let widget;
    let token = '';
    let state = 'idle';
    let waiters = [];

    const set = (next) => {
        state = next;
        onChange(next);
        if (next !== 'loading') {
            const pending = waiters;
            waiters = [];
            pending.forEach((resolve) => resolve(next));
        }
    };

    function render(api) {
        if (widget !== undefined || !container?.isConnected) return;
        widget = api.render(container, {
            sitekey: siteKey,
            action: 'enquiry',
            appearance: 'interaction-only',
            language: locale,
            theme: 'light',
            callback: (value) => {
                token = value;
                set('ready');
            },
            'expired-callback': () => {
                token = '';
                set('expired');
            },
            'error-callback': () => {
                token = '';
                set('error');
                return true; // handled here: no console noise from the widget
            },
            'timeout-callback': () => {
                token = '';
                set('expired');
            },
            'before-interactive-callback': () => set('interactive'),
        });
    }

    return {
        get enabled() {
            return !!siteKey;
        },
        get state() {
            return state;
        },
        token: () => token,
        load() {
            if (!siteKey || widget !== undefined || state === 'loading') return;
            set('loading');
            loadScript().then(render, () => set('error'));
        },
        /** Single-use tokens: a new one after every response other than received or invalid. */
        reset() {
            token = '';
            if (widget !== undefined && window.turnstile) {
                try {
                    window.turnstile.reset(widget);
                    set('loading');
                } catch {
                    set('error');
                }
            }
        },
        /** Resolves with the state once a token, an interaction request or an error arrives (or after ms). */
        waitForToken(ms) {
            if (!siteKey || token) return Promise.resolve('ready');
            if (state === 'error' || state === 'interactive') return Promise.resolve(state);
            this.load();
            return new Promise((resolve) => {
                const timer = window.setTimeout(() => {
                    waiters = waiters.filter((w) => w !== done);
                    resolve(token ? 'ready' : 'timeout');
                }, ms);
                function done(result) {
                    window.clearTimeout(timer);
                    resolve(token ? 'ready' : result);
                }
                waiters.push(done);
            });
        },
        destroy() {
            if (widget !== undefined && window.turnstile) {
                try {
                    window.turnstile.remove(widget);
                } catch {
                    /* already gone */
                }
            }
            widget = undefined;
        },
    };
}
