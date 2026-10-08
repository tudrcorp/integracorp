self.addEventListener('install', (event) => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    let payload = {};

    try {
        payload = event.data ? event.data.json() : {};
    } catch (error) {
        payload = {};
    }

    const title = payload.title || 'Nueva conversación';
    const body = payload.body || 'Hay una persona esperando.';
    const tag = payload.tag || 'crm-handoff';
    const url = payload.url || '/';

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

        for (const client of windows) {
            client.postMessage({ type: 'crm-handoff', title, body, tag, url });
        }

        await self.registration.showNotification(title, {
            body,
            tag,
            renotify: true,
            requireInteraction: true,
            data: { url },
        });
    })());
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data && event.notification.data.url;

    if (!url) {
        return;
    }

    const handoffId = (() => {
        try {
            return new URL(url, self.location.origin).searchParams.get('caso') || '';
        } catch (error) {
            return '';
        }
    })();

    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
        for (const client of windows) {
            if (String(client.url).includes('atencion-whatsapp') && 'focus' in client) {
                client.postMessage({ type: 'crm-open', url, handoffId });

                if (typeof client.navigate === 'function') {
                    client.navigate(url);
                }

                return client.focus();
            }
        }

        if (self.clients.openWindow) {
            return self.clients.openWindow(url);
        }
    }));
});
