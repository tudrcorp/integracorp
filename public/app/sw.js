const CACHE_NAME = 'storefront-static-v2';

const OFFLINE_URL = '/app/offline.html';

const STATIC_ASSETS = [
    '/pwa/storefront.webmanifest',
    '/pwa/icon-192.png',
    '/pwa/icon-512.png',
    '/pwa/apple-touch-icon.png',
    OFFLINE_URL,
];

const isStorefrontPath = (pathname) => pathname === '/app' || pathname === '/app/' || pathname.startsWith('/app/');

const isStaticAssetPath = (pathname) => pathname.startsWith('/pwa/') && ! pathname.includes('guia-chat');

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE_NAME)
            .then((cache) => cache.addAll(STATIC_ASSETS))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(keys.filter((key) => key !== CACHE_NAME && key.startsWith('storefront-')).map((key) => caches.delete(key))),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    const url = new URL(event.request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (event.request.mode === 'navigate' && isStorefrontPath(url.pathname)) {
        event.respondWith(
            fetch(event.request).catch(() => caches.match(OFFLINE_URL).then((cached) => cached || caches.match(event.request))),
        );

        return;
    }

    if (url.pathname === OFFLINE_URL) {
        event.respondWith(
            caches.match(event.request).then((cached) => cached || fetch(event.request)),
        );

        return;
    }

    if (! isStaticAssetPath(url.pathname)) {
        return;
    }

    event.respondWith(
        caches.match(event.request).then((cached) => {
            if (cached) {
                return cached;
            }

            return fetch(event.request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy));
                }

                return response;
            });
        }),
    );
});
