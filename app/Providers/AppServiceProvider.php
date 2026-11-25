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
        // tautan dan aset selalu https di produksi (mis. saat berada di balik proxy)
        if ($this->app->isProduction()) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
