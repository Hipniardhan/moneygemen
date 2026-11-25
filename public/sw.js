/* MoneyGemen service worker */
const VERSION = 'mg-v2';
const STATIC = `${VERSION}-static`;
const PRECACHE = ['/offline', '/css/app.css', '/icons/icon-192.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => !k.startsWith(VERSION)).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    // Halaman: jaringan dulu, jatuh ke halaman offline. HTML tidak disimpan (data pribadi).
    if (req.mode === 'navigate') {
        event.respondWith(fetch(req).catch(() => caches.match('/offline')));
        return;
    }

    // Aset statis: cache-first berdasarkan URL LENGKAP. Halaman memakai ?v=<waktu-ubah-file>,
    // jadi file yang berubah punya URL baru dan otomatis diambil dari jaringan.
    // ignoreSearch hanya dipakai sebagai cadangan saat benar-benar offline.
    if (/^\/(css|js|icons)\//.test(url.pathname)) {
        event.respondWith(
            caches.open(STATIC).then(async (cache) => {
                const exact = await cache.match(req);
                if (exact) return exact;

                try {
                    const res = await fetch(req);
                    if (res.ok) cache.put(req, res.clone());
                    return res;
                } catch (e) {
                    const any = await cache.match(req, { ignoreSearch: true });
                    if (any) return any;
                    throw e;
                }
            })
        );
    }
});

self.addEventListener('push', (event) => {
    let data = { title: 'MoneyGemen', body: 'Ada pembaruan baru', url: '/' };
    try { data = { ...data, ...event.data.json() }; } catch (e) {}

    event.waitUntil(self.registration.showNotification(data.title, {
        body: data.body,
        icon: '/icons/icon-192.png',
        badge: '/icons/badge-96.png',
        data: { url: data.url },
        tag: 'moneygemen',
        renotify: true,
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
            for (const c of list) {
                if ('focus' in c) return c.focus();
            }
            return self.clients.openWindow(target);
        })
    );
});
