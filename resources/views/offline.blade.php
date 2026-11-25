<!doctype html>
<html lang="id">
<head>
    @include('partials.head')
    <title>Tidak ada internet · MoneyGemen</title>
</head>
<body class="guest">
    <main class="auth-shell" style="text-align:center">
        <div class="offline-art" aria-hidden="true">📡</div>
        <h1>Kamu sedang offline</h1>
        <p class="muted">MoneyGemen butuh internet untuk menyimpan dan menampilkan catatanmu. Periksa sambungan, lalu coba lagi.</p>
        <button class="btn btn-primary btn-block" onclick="location.reload()">Coba lagi</button>
    </main>
</body>
</html>
