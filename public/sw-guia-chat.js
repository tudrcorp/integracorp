/**
 * Service worker legado en raíz: se desregistra para migrar a /chat/publico/sw.js
 * (el scope anterior fallaba porque el script no estaba dentro de /chat/publico/).
 */
self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        self.registration
            .unregister()
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
