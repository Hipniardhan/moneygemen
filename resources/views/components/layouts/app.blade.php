<!doctype html>
<html lang="id">
<head>
    @include('partials.head')
    <title>{{ $title ?? 'MoneyGemen' }} · MoneyGemen</title>
    <script>
        // preferensi sembunyikan saldo: diterapkan sebelum halaman tampil agar tidak berkedip
        (function () {
            var k = 'mg-hide-balance';
            try { if (localStorage.getItem(k) === '1') document.documentElement.classList.add('hide-balance'); } catch (e) {}
            window.toggleBalance = function () {
                var on = document.documentElement.classList.toggle('hide-balance');
                try { localStorage.setItem(k, on ? '1' : '0'); } catch (e) {}
            };
        })();
    </script>
    @livewireStyles
</head>
<body>
    <div class="shell">
        <header class="topbar">
            <a href="{{ route('dashboard') }}" class="brand">
                <img src="/icons/icon-192.png" alt="" width="28" height="28">
                <span>MoneyGemen</span>
            </a>
            <div class="topbar-actions">
                <livewire:notification-bell />
            </div>
        </header>

        @if (auth()->user()->is_demo)
            <div class="demo-note">Mode demo: hanya untuk melihat-lihat, data tidak bisa diubah. <a href="{{ route('register') }}" onclick="event.preventDefault(); document.getElementById('demo-logout').submit()">Daftar gratis</a> untuk mencatat keuanganmu sendiri.</div>
            <form id="demo-logout" method="POST" action="{{ route('logout') }}" hidden>@csrf</form>
        @endif

        <main class="content">
            {{ $slot }}
        </main>
    </div>

    <nav class="bottom-nav" aria-label="Navigasi utama">
        <a href="{{ route('dashboard') }}" class="side-brand">
            <img src="/icons/icon-192.png" alt="" width="34" height="34">
            <span>MoneyGemen</span>
        </a>
        <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/></svg>
            <span>Beranda</span>
        </a>
        <a href="{{ route('transactions') }}" class="{{ request()->routeIs('transactions') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
            <span>Transaksi</span>
        </a>
        <button type="button" class="fab" aria-label="Tambah transaksi" onclick="Livewire.dispatch('open-transaction')">
            <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            <span class="fab-label">Catat transaksi</span>
        </button>
        <a href="{{ route('categories') }}" class="{{ request()->routeIs('categories') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><path d="M7.5 7.5h.01"/></svg>
            <span>Kategori</span>
        </a>
        <a href="{{ route('profile') }}" class="{{ request()->routeIs('profile') ? 'active' : '' }}">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
            <span>Profil</span>
        </a>
    </nav>

    <livewire:transaction-form />

    <div class="toast-zone" x-data="{ msg: @js(session('toast')), err: false, t: null,
        show(m, e = false) { this.msg = m; this.err = e; clearTimeout(this.t); this.t = setTimeout(() => this.msg = null, 3200); } }"
        x-init="if (msg) show(msg)"
        @toast.window="show($event.detail.message, !!$event.detail.error)">
        <div class="toast" :class="{ 'toast-err': err }" x-show="msg" x-transition.opacity x-text="msg" role="status" style="display:none"></div>
    </div>

    @include('partials.install-banner')
    @livewireScripts
    <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
    <script>window.MG = { vapid: @js(config('webpush.public_key')) };</script>
</body>
</html>
