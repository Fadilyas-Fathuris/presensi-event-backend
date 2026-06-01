<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(HandleCors::class);
    })

    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'is_admin' => \App\Http\Middleware\IsAdmin::class,
            'is_alumni' => \App\Http\Middleware\IsAlumni::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
