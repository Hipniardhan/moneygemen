<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // versi ikon (waktu ubah file) agar logo baru langsung terpakai, tidak tertahan cache browser/service worker
        $icon = public_path('icons/icon-192.png');
        \Illuminate\Support\Facades\View::share('iconV', is_file($icon) ? filemtime($icon) : 1);

        // tautan dan aset selalu https di produksi (mis. saat berada di balik proxy)
        if ($this->app->isProduction()) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
