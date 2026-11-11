/**
 * Design 2 PWA (upgrade spec §5.5 and §12 E). The manifest is static (public/d2/manifest-{locale}.webmanifest,
 * scripts/d2/pwa-icons.mjs) and linked by the Design 2 layout; this module only registers the service worker.
 *
 * The site keeps ONE service worker: Design 1's frozen /sw.js, on its default scope "/". Design 2 registers
 * that same script with the same (default) options, exactly like Design 1's resources/js/app.js, so the
 * browser keeps one registration whichever design the visitor sees first: a different script on the same
 * scope would replace Design 1's worker and take over Design 1's pages. What /sw.js does (read it, never edit
 * it): navigations go to the network first and fall back to the cached offline page of the visitor's
 * language when the network fails; Design 1's hashed /build/assets and the hashed fonts under /fonts/ (so
 * Design 2's /fonts/d2/ too) are cached first; POST requests (the consultation form) are never touched,
 * nothing else is cached, and there are no push notifications.
 *
 * Registered only on <html data-pwa> (config('summit.features.pwa'): on in production, off locally so no
 * worker lingers on 127.0.0.1), in a secure context, after the load event (never competing with the page).
 */
export const WORKER = '/sw.js';

/** True when the page opts in and the browser can run a service worker here. */
export function shouldRegister(win) {
    try {
        return (
            !!win &&
            !!win.isSecureContext &&
            !!win.navigator &&
            'serviceWorker' in win.navigator &&
            !!win.document?.documentElement?.hasAttribute('data-pwa')
        );
    } catch {
        return false;
    }
}

export default function pwa() {
    if (!shouldRegister(window)) return;
    // No options: the default scope ("/") and settings are Design 1's, so both designs share one registration.
    const register = () => window.navigator.serviceWorker.register(WORKER).catch(() => {});
    if (document.readyState === 'complete') register();
    else window.addEventListener('load', register, { once: true });
}
