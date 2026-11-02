/*
 * Budgeting App service worker (docs/15 §11).
 *
 * - Built assets (/build/assets/*, hashed and immutable) and icons: cache first.
 * - Pages and data: always from the network, so numbers are never stale.
 *   When offline, page navigations show /offline.html.
 * - Offline input is intentionally not supported.
 */
const VERSION = 'v1';
const STATIC_CACHE = `budgeting-static-${VERSION}`;
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(STATIC_CACHE)
            .then((cache) => cache.addAll([OFFLINE_URL, '/icons/icon-192.png']))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter(
                            (key) =>
                                key.startsWith('budgeting-') &&
                                key !== STATIC_CACHE,
                        )
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (
        url.pathname.startsWith('/build/assets/') ||
        url.pathname.startsWith('/icons/')
    ) {
        event.respondWith(
            caches.match(request).then(
                (cached) =>
                    cached ??
                    fetch(request).then((response) => {
                        if (response.ok) {
                            const copy = response.clone();
                            caches
                                .open(STATIC_CACHE)
                                .then((cache) => cache.put(request, copy));
                        }

                        return response;
                    }),
            ),
        );

        return;
    }

    // Full page loads only; Inertia visits (X-Inertia) handle errors themselves.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL)),
        );
    }
});
