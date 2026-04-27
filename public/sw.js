self.addEventListener('install', (event) => {
  event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

// Minimal SW: passthrough fetch (required for installability checks in some browsers)
self.addEventListener('fetch', () => {});
