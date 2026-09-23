<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\LivePresence\ClientLocation;
use App\Support\LivePresence\IpBlockList;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Corta las peticiones de IPs en la lista negra del monitor en vivo.
 *
 * Va primero en la pila global: rechaza antes de abrir sesión, autenticar o
 * tocar la base, con una sola lectura de caché. La respuesta no dice por qué
 * ni hasta cuándo: a un atacante no se le da información.
 */
class BlockBlacklistedIp
{
    public const BLOCKED_ATTRIBUTE = 'live_presence.ip_blocked';

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $blocked = IpBlockList::isBlocked(ClientLocation::ip($request));
        } catch (Throwable) {
            $blocked = false;
        }

        if (! $blocked) {
            return $next($request);
        }

        $request->attributes->set(self::BLOCKED_ATTRIBUTE, true);

        return response('Acceso denegado.', 403, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
