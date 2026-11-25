<!doctype html>
<html lang="id">
<head>
    @include('partials.head')
    <title>{{ $title ?? 'MoneyGemen' }} · MoneyGemen</title>
    @livewireStyles
</head>
<body class="guest">
    <main class="auth-shell">
        {{ $slot }}
    </main>

    @include('partials.install-banner')
    @livewireScripts
    <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
</body>
</html>
