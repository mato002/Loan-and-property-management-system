/**
 * Minimal service worker so browsers can offer "Install app" (PWA) on mobile and desktop.
 * Always uses the network — no offline cache of portal pages.
 */
self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    event.respondWith(fetch(event.request));
});
