<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            require base_path('routes/storefront.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        /** Lista negra de IPs del monitor en vivo: primero de todo, antes de sesión y autenticación. */
        $middleware->prepend(\App\Http\Middleware\BlockBlacklistedIp::class);

        /** Monitor en vivo: escribe después de responder, no suma tiempo a la petición. */
        $middleware->append(\App\Http\Middleware\TrackLivePresence::class);

        $middleware->alias([
            'storefront.auth' => \App\Http\Middleware\EnsureStorefrontAuthenticated::class,
            'storefront.guest' => \App\Http\Middleware\RedirectIfStorefrontAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        /** Registro de errores del monitor (Negocios → Colas y errores). No detiene el reporte normal al log. */
        $exceptions->report(static function (Throwable $exception): void {
            \App\Support\LivePresence\ErrorTracker::capture($exception);
        });
    })->create();
