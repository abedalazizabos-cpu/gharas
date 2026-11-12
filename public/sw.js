/* مركز غراس: the site as a phone app. Keeps an offline page, and shows notifications on the phone. */
const CACHE = 'gharas-v1';
const OFFLINE = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((c) => c.addAll([OFFLINE])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});

// Pages always come from the site (data must be current); only when there is no internet the offline page is shown.
self.addEventListener('fetch', (event) => {
    if (event.request.mode === 'navigate') {
        event.respondWith(fetch(event.request).catch(() => caches.match(OFFLINE)));
    }
});

self.addEventListener('push', (event) => {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) { data = { body: event.data ? event.data.text() : '' }; }
    event.waitUntil(self.registration.showNotification(data.title || 'إشعار جديد', {
        body: data.body || '',
        icon: data.icon || '/app-icon-192.png',
        badge: '/app-icon-192.png',
        dir: 'rtl',
        lang: 'ar',
        data: { url: data.url || '/dashboard' },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data && event.notification.data.url ? event.notification.data.url : '/dashboard';
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
        for (const client of list) {
            if ('focus' in client && new URL(client.url).origin === self.location.origin) {
                client.navigate(url);
                return client.focus();
            }
        }
        return self.clients.openWindow(url);
    }));
});
