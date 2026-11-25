<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo('/masuk');
        $middleware->redirectUsersTo('/');

        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        // ganti kata sandi / reset admin otomatis mengeluarkan perangkat lain
        $middleware->web(append: [\Illuminate\Session\Middleware\AuthenticateSession::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
