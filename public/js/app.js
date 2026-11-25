/* MoneyGemen - PWA: service worker, ajakan pasang, dan toggle push */

// --- Service worker -------------------------------------------------------
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
    });
}

// --- Ajakan "Pasang aplikasi" --------------------------------------------
(function () {
    const banner = document.getElementById('install-banner');
    if (!banner) return;

    const KEY = 'mg-install-dismissed';
    const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;
    const dismissedAt = () => { try { return Number(localStorage.getItem(KEY) || 0); } catch (e) { return 0; } };
    const snoozed = () => Date.now() - dismissedAt() < 7 * 864e5;
    const hint = document.getElementById('install-hint');
    const btn = document.getElementById('install-btn');
    let deferred = null;

    if (standalone) return;

    const hide = (remember) => {
        banner.hidden = true;
        if (remember) { try { localStorage.setItem(KEY, String(Date.now())); } catch (e) {} }
    };

    document.getElementById('install-close').addEventListener('click', () => hide(true));

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferred = e;
        if (!snoozed()) banner.hidden = false;
    });

    btn.addEventListener('click', async () => {
        if (deferred) {
            deferred.prompt();
            await deferred.userChoice.catch(() => {});
            deferred = null;
            hide(false);
        } else {
            hide(true);
        }
    });

    window.addEventListener('appinstalled', () => hide(false));

    // iOS Safari tidak punya beforeinstallprompt: tampilkan petunjuk manual
    const ios = /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.MSStream;
    if (ios && !snoozed()) {
        hint.textContent = 'Ketuk tombol Bagikan, lalu pilih “Tambah ke Layar Utama”.';
        btn.textContent = 'Mengerti';
        banner.hidden = false;
    }
})();

// --- Notifikasi push per perangkat ---------------------------------------
function urlBase64ToUint8Array(base64) {
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
}

window.pushToggle = function () {
    const csrf = () => document.querySelector('meta[name=csrf-token]').content;
    const post = (url, body) => fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });

    return {
        supported: 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window,
        configured: !!(window.MG && window.MG.vapid),
        denied: false,
        on: false,
        busy: false,

        async init() {
            if (!this.supported) return;
            this.denied = Notification.permission === 'denied';
            try {
                const reg = await navigator.serviceWorker.ready;
                this.on = !!(await reg.pushManager.getSubscription()) && Notification.permission === 'granted';
            } catch (e) {}
        },

        async toggle(ev) {
            this.busy = true;
            try {
                const reg = await navigator.serviceWorker.ready;
                if (ev.target.checked) {
                    const perm = await Notification.requestPermission();
                    if (perm !== 'granted') { this.denied = perm === 'denied'; this.on = false; ev.target.checked = false; return; }
                    const sub = await reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(window.MG.vapid),
                    });
                    const res = await post('/push/subscribe', sub.toJSON());
                    if (!res.ok) throw new Error('subscribe gagal');
                    this.on = true;
                } else {
                    const sub = await reg.pushManager.getSubscription();
                    if (sub) {
                        await post('/push/unsubscribe', { endpoint: sub.endpoint });
                        await sub.unsubscribe();
                    }
                    this.on = false;
                }
            } catch (e) {
                this.on = !ev.target.checked ? this.on : false;
                ev.target.checked = this.on;
                window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Gagal mengubah notifikasi', error: true } }));
            } finally {
                this.busy = false;
            }
        },
    };
};
