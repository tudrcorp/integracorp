<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\LivePresence\LivePresenceRecorder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Alimenta el monitor en vivo sin sumar tiempo a la respuesta: mide en
 * `handle()` y escribe en `terminate()`, que corre después de enviar la
 * respuesta al navegador. Cualquier fallo se traga: observar nunca puede
 * romper la petición observada.
 */
class TrackLivePresence
{
    /**
     * Rutas que no son actividad del usuario: recursos estáticos, el propio
     * latido (se registra en su controlador) y el chequeo de salud.
     *
     * @var list<string>
     */
    private const SKIPPED_PATHS = [
        'live-presence/ping',
        'up',
        'build/*',
        'storage/*',
        'livewire/livewire.js',
        'livewire/livewire.min.js',
        'livewire/livewire.min.js.map',
        'livewire/preview-file/*',
        'livewire/upload-file',
        'favicon.ico',
        'sw.js',
        'manifest.json',
        'site.webmanifest',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('live_presence.queries_before', LivePresenceRecorder::$queries);

        $response = $next($request);

        $started = defined('LARAVEL_START') ? (float) LARAVEL_START : (float) $request->server('REQUEST_TIME_FLOAT', microtime(true));
        $request->attributes->set('live_presence.duration_ms', (microtime(true) - $started) * 1000);

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            if (! config('live-presence.enabled', true) || $request->isMethod('OPTIONS') || $request->isMethod('HEAD')) {
                return;
            }

            if ($request->is(...self::SKIPPED_PATHS) || ! $request->hasSession()) {
                return;
            }

            $user = Auth::user();

            if ($user === null) {
                return;
            }

            LivePresenceRecorder::recordServerRequest(
                $request,
                $response,
                $user,
                (float) $request->attributes->get('live_presence.duration_ms', 0),
                max(0, LivePresenceRecorder::$queries - (int) $request->attributes->get('live_presence.queries_before', 0)),
            );
        } catch (Throwable) {
        }
    }
}
