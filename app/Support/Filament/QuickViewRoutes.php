<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Illuminate\Support\Facades\Route;

/**
 * Pantallas de un panel que se pueden abrir en vista rápida (Alt / ⌥ Option + clic).
 *
 * Es una lista blanca generada de las rutas de Filament del panel: páginas, recursos (listar,
 * ver, crear, editar y subpáginas) y el perfil. Lo que no es una pantalla de Filament —descargas,
 * PDF, exportaciones, endpoints sueltos de `routes/web.php`— nunca entra, aunque viva bajo el
 * mismo prefijo; tampoco el login ni el restablecimiento de contraseña.
 */
final class QuickViewRoutes
{
    /** @var list<string> */
    private const EXCLUDED_ROUTE_SUFFIXES = ['auth.login', 'auth.logout', 'auth.password-reset.request', 'auth.password-reset.reset'];

    /**
     * @return list<string> Expresiones regulares (sintaxis JavaScript) sobre el path de la URL.
     */
    public static function patterns(string $panelId): array
    {
        $prefix = 'filament.'.$panelId.'.';
        $excluded = array_map(static fn (string $suffix): string => $prefix.$suffix, self::EXCLUDED_ROUTE_SUFFIXES);
        $patterns = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();

            if (! str_starts_with($name, $prefix) || in_array($name, $excluded, true) || ! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $segments = array_map(
                static fn (string $segment): string => preg_match('/^\{[^}]+\}$/', $segment) === 1 ? '[^/]+' : preg_quote($segment, '/'),
                array_filter(explode('/', trim($route->uri(), '/')), static fn (string $segment): bool => $segment !== ''),
            );

            $patterns[] = '^/'.implode('/', $segments).'/?$';
        }

        $patterns = array_values(array_unique($patterns));
        sort($patterns);

        return $patterns;
    }
}
