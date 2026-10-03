/**
 * Installable app shell. Portal pages stay on the network.
 * The field meter page and its script are kept so a field officer can open them without internet
 * after visiting the page once at the office.
 */
const FIELD_CACHE = 'passion-field-readings-v2';

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys
                    .filter((key) => key.startsWith('passion-field-readings-') && key !== FIELD_CACHE)
                    .map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

function isFieldCapture(url) {
    return url.pathname.endsWith('/property/field/readings')
        || url.pathname.endsWith('/js/field-readings.js');
}

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET' || !isFieldCapture(new URL(event.request.url))) {
        event.respondWith(fetch(event.request));
        return;
    }

    event.respondWith(
        fetch(event.request)
            .then((response) => {
                if (response && response.ok) {
                    const copy = response.clone();
                    caches.open(FIELD_CACHE).then((cache) => cache.put(event.request, copy));
                }
                return response;
            })
            .catch(() => caches.match(event.request).then((cached) => cached || Promise.reject(new Error('offline'))))
    );
});
