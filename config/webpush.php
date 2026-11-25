<?php

return [
    'public_key' => env('VAPID_PUBLIC_KEY'),
    'private_key' => env('VAPID_PRIVATE_KEY'),
    // kosong → pakai APP_URL (VAPID mewajibkan subject berupa URL https atau mailto:)
    'subject' => env('VAPID_SUBJECT') ?: env('APP_URL', 'https://example.com'),
];
