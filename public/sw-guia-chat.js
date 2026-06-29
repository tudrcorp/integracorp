const CACHE_NAME = 'guia-chat-static-v3';

const STATIC_ASSETS = [
    '/pwa/guia-chat.webmanifest',
    '/pwa/guia-chat/icon-192.png',
    '/pwa/guia-chat/icon-512.png',
    '/pwa/guia-chat/apple-touch-icon.png',
    '/images/chat/assistant-avatar.png',
];

const isChatPublicoPath = (pathname) => pathname === '/chat/publico' || pathname === '/chat/publico/';

const isStaticAssetPath = (pathname) =>
    pathname.startsWith('/pwa/guia-chat/') || pathname.startsWith('/images/chat/');

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
                Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))),
            )
            .then(() => self.clients.claim())
            .then(() => self.clients.matchAll({ type: 'window', includeUncontrolled: true }))
            .then((clients) => {
                clients.forEach((client) => {
                    if (client.url.includes('/chat/publico')) {
                        client.navigate(client.url);
                    }
                });
            }),
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

    if (event.request.mode === 'navigate' && isChatPublicoPath(url.pathname)) {
        event.respondWith(
            fetch(event.request)
                .then((response) => response)
                .catch(() => caches.match(event.request)),
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
