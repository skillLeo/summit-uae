/* Summit Legal Consultancy service worker (Prototype 1).
 * Navigations: network first; if the network fails, the offline page of the same language
 * (approved phone, WhatsApp and email). Hashed build assets and fonts: cache first.
 * Nothing else is cached; no form data or personal data ever touches the cache. */
const VERSION = 'summit-p1-v1';
const OFFLINE = ['/en/offline/', '/ar/offline/', '/ru/offline/'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(VERSION)
            .then((cache) => cache.addAll(OFFLINE))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req).catch(async () => {
                const lang = (url.pathname.match(/^\/(en|ar|ru)\//) || [null, 'en'])[1];
                return (await caches.match(`/${lang}/offline/`)) || Response.error();
            }),
        );
        return;
    }

    if (url.pathname.startsWith('/build/assets/') || url.pathname.startsWith('/fonts/')) {
        event.respondWith(
            caches.match(req).then((hit) => hit || fetch(req).then((res) => {
                if (res.ok) {
                    const copy = res.clone();
                    caches.open(VERSION).then((cache) => cache.put(req, copy));
                }
                return res;
            })),
        );
    }
});
