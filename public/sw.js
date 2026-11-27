/* مركز غراس: the site as a phone app.
 *  - Shows notifications on the phone.
 *  - Works without internet for the teacher's daily forms: the teacher pages he opened are kept
 *    on the phone, and a form sent while the internet is down is kept in an "outbox" (IndexedDB)
 *    that public/js/offline.js sends later.
 * Change VERSION when this file changes in a way that must clear what phones keep. */
const VERSION = 'v2';
const STATIC = 'gharas-static-' + VERSION;   // CSS, JS, fonts, icons, logo
const PAGES = 'gharas-pages-' + VERSION;     // teacher pages, for opening them without internet
const OFFLINE = '/offline.html'; // page revision 2: lists the kept teacher pages

// Teacher forms that may be filled in without internet. Keep the same list in public/js/offline.js.
const OFFLINE_FORMS = [
    /^\/teacher\/circles\/\d+\/attendance$/,          // حضور الحلقة
    /^\/teacher\/memorization$/,                      // تسجيل تسميع
    /^\/teacher\/circles\/\d+\/day-off$/,             // لا تحفيظ اليوم
    /^\/teacher\/assessments$/,                       // تقييم طالب
    /^\/teacher\/sunnah\/\d+\/attendance$/,           // حضور لقاء قسم السنة
    /^\/teacher\/sunnah\/\d+\/posts\/\d+\/grades$/,   // درجات الواجب
    /^\/teacher\/sunnah\/\d+\/grade$/,                // رصد درجات الدورة
    /^\/teacher\/quality\/\d+\/grade$/,               // رصد نتيجة اختبار الجودة
];
// Pages that are the same form whatever the ?student=… : one kept copy serves them all.
const ANY_QUERY = [/^\/teacher\/memorization\/create$/, /^\/teacher\/assessments\/create$/];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC).then((c) => c.addAll([new Request(OFFLINE, { cache: 'reload' })])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys()
        .then((keys) => Promise.all(keys.filter((k) => k !== STATIC && k !== PAGES).map((k) => caches.delete(k))))
        .then(() => self.clients.claim()));
});

self.addEventListener('message', (event) => {
    // Sent on logout: the next person on this phone must not see this teacher's pages.
    if (event.data === 'clear-pages') {
        event.waitUntil(caches.delete(PAGES));
    }
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    const url = new URL(req.url);

    if (req.method === 'POST') {
        if (req.mode === 'navigate' && url.origin === self.location.origin && OFFLINE_FORMS.some((r) => r.test(url.pathname))) {
            event.respondWith(sendOrKeep(req));
        }
        return;
    }
    if (req.method !== 'GET') return;

    if (req.mode === 'navigate') {
        if (url.origin === self.location.origin && url.pathname.startsWith('/teacher')) {
            event.respondWith(teacherPage(req, url));
        } else {
            event.respondWith(fetch(req).catch(() => otherPage(url)));
        }
        return;
    }

    if (isStatic(url)) {
        event.respondWith(staticFile(req));
    }
});

// ---------------------------------------------------------------- pages

/** Network first: always the newest page from the site, and a copy is kept; without internet the copy is shown. */
async function teacherPage(req, url) {
    try {
        const res = await fetch(req);
        // Not kept: errors, and redirects (e.g. to the login page when the session ended).
        if (res.ok && !res.redirected && (res.headers.get('Content-Type') || '').includes('text/html')) {
            const copy = await withoutFlash(res.clone());
            caches.open(PAGES).then((c) => c.put(req.url, copy));
        }
        return res;
    } catch (e) {
        const cache = await caches.open(PAGES);
        let hit = await cache.match(req.url);
        if (!hit && ANY_QUERY.some((r) => r.test(url.pathname))) {
            hit = await cache.match(req.url, { ignoreSearch: true });
        }
        return hit || (await caches.match(OFFLINE));
    }
}

/** Without internet, the app icon (/dashboard) opens the teacher's kept home page if there is one. */
async function otherPage(url) {
    if (url.origin === self.location.origin && (url.pathname === '/dashboard' || url.pathname === '/')) {
        const home = await (await caches.open(PAGES)).match(self.location.origin + '/teacher');
        if (home) return home;
    }
    return caches.match(OFFLINE);
}

/** The kept copy must not show "saved" again every time it is opened offline. */
async function withoutFlash(res) {
    const html = (await res.text()).replace(/<div id="flash-data"[^>]*><\/div>/, '');
    return new Response(html, { status: 200, headers: { 'Content-Type': res.headers.get('Content-Type') } });
}

// ---------------------------------------------------------------- static files

function isStatic(url) {
    if (url.origin === self.location.origin) {
        return /^\/(css|js|vendor|images|uploads)\//.test(url.pathname) || url.pathname === '/favicon.ico' || /^\/app-icon-\d+\.png$/.test(url.pathname);
    }
    return url.hostname === 'fonts.googleapis.com' || url.hostname === 'fonts.gstatic.com';
}

/** From the phone at once (fast), and refreshed from the site in the background for next time.
 *  gharas.js and gharas.css carry ?v=<time of change>, so a new version is always fetched fresh. */
async function staticFile(req) {
    const cache = await caches.open(STATIC);
    const hit = await cache.match(req);
    const fresh = fetch(req).then((res) => {
        if (res.ok || res.type === 'opaque') cache.put(req, res.clone());
        return res;
    }).catch(() => hit);
    return hit || fresh;
}

// ---------------------------------------------------------------- forms sent without internet

/** Sends the form; if the internet is down at that moment, keeps it in the outbox and shows the page again. */
async function sendOrKeep(req) {
    const copy = req.clone();
    try {
        return await fetch(req);
    } catch (e) {
        const form = await copy.formData();
        const fields = [];
        let user = null, label = '';
        for (const [name, value] of form.entries()) {
            if (typeof value !== 'string') continue; // files are not kept
            if (name === '_offline_user') user = value;
            else if (name === '_offline_label') label = value;
            else if (name !== '_token') fields.push([name, value]);
        }
        if (!user) {
            return caches.match(OFFLINE);
        }
        await outboxAdd({
            id: self.crypto.randomUUID(),
            user: user,
            url: new URL(req.url).pathname,
            fields: fields,
            label: label,
            savedAt: new Date().toISOString(),
            status: 'pending',
            shown: false,
        });
        const back = req.referrer && new URL(req.referrer).origin === self.location.origin ? req.referrer : '/teacher';
        return Response.redirect(back, 303);
    }
}

// The same small IndexedDB store as offline.js (database gharas-offline, store outbox).
function outboxAdd(item) {
    return new Promise((resolve, reject) => {
        const open = indexedDB.open('gharas-offline', 1);
        open.onupgradeneeded = () => open.result.createObjectStore('outbox', { keyPath: 'id' });
        open.onerror = () => reject(open.error);
        open.onsuccess = () => {
            const tx = open.result.transaction('outbox', 'readwrite');
            tx.objectStore('outbox').put(item);
            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
        };
    });
}

// ---------------------------------------------------------------- notifications

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
