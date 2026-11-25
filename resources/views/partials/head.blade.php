<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#14213d" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0e1527" media="(prefers-color-scheme: dark)">
<link rel="preload" href="/fonts/plus-jakarta-sans-latin.woff2" as="font" type="font/woff2" crossorigin>
<meta name="description" content="Catat pemasukan dan pengeluaran harian, sendiri atau bersama pasangan.">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/favicon.ico?v={{ $iconV }}" sizes="any">
<link rel="icon" type="image/png" href="/icons/icon-192.png?v={{ $iconV }}">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png?v={{ $iconV }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="MoneyGemen">
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
