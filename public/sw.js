/**
 * Installable app shell.
 * Portal and public pages stay on the network.
 * The field meter page and its script are kept so a field officer can open them without internet
 * after visiting the page once at the office.
 */
const SHELL_CACHE = 'pwa-shell-v2';
const FIELD_CACHE = 'passion-field-readings-v2';
const SHELL_URLS = [
    './offline.html',
    './pwa/icon-192.png',
    './pwa/icon-512.png',
    './pwa/icon-maskable-192.png',
    './pwa/icon-maskable-512.png',
    './pwa/apple-touch-icon.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL_CACHE)
            .then((cache) => cache.addAll(SHELL_URLS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys
                    .filter((key) =>
                        (key.startsWith('passion-field-readings-') && key !== FIELD_CACHE)
                        || (key.startsWith('pwa-shell-') && key !== SHELL_CACHE)
                    )
                    .map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

function isFieldCapture(url) {
    return url.pathname.endsWith('/property/field/readings')
        || url.pathname.endsWith('/js/field-readings.js');
}

function networkThenCache(request, cacheName) {
    return fetch(request)
        .then((response) => {
            if (response && response.ok) {
                const copy = response.clone();
                caches.open(cacheName).then((cache) => cache.put(request, copy));
            }
            return response;
        })
        .catch(() => caches.match(request).then((cached) => cached || Promise.reject(new Error('offline'))));
}

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    const url = new URL(event.request.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    if (isFieldCapture(url)) {
        event.respondWith(networkThenCache(event.request, FIELD_CACHE));
        return;
    }

    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(async () => {
                const cached = await caches.match('./offline.html');
                return cached || new Response('You are offline.', {
                    status: 503,
                    headers: { 'Content-Type': 'text/plain; charset=UTF-8' },
                });
            })
        );
    }
});
