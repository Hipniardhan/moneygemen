<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Livewire/Alpine memakai skrip inline dan evaluasi ekspresi, jadi script-src butuh
     * 'unsafe-inline' dan 'unsafe-eval'. Sisanya dikunci ke asal sendiri: tidak ada skrip,
     * gambar, atau koneksi dari domain lain, dan halaman tidak boleh dibingkai situs lain.
     */
    private const CSP = "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
        . "style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; "
        . "manifest-src 'self'; worker-src 'self'; object-src 'none'; base-uri 'self'; "
        . "form-action 'self'; frame-ancestors 'none'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // jangan membocorkan versi PHP
        header_remove('X-Powered-By');

        $h = $response->headers;
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'DENY');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');
        $h->set('Content-Security-Policy', self::CSP);

        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000');
        }

        // data keuangan: jangan biarkan tombol Kembali menampilkan halaman dari cache setelah keluar
        if ($request->user() && str_contains((string) $h->get('Content-Type'), 'text/html')) {
            $h->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
