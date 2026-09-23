<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Traduce una petición a "dónde está y qué hace" el usuario.
 *
 * Las acciones de Filament y Livewire llegan todas a `/livewire/update`: el
 * panel y la página salen del Referer y la acción de los componentes y métodos
 * llamados en el cuerpo de la petición.
 */
final class ActivityContext
{
    public const PWA = 'pwa';

    /**
     * @var array<string, string>
     */
    public const PANELS = [
        'admin' => 'Admin',
        'business' => 'Negocios',
        'operations' => 'Operaciones',
        'administration' => 'Administración',
        'marketing' => 'Marketing',
        'agents' => 'Agentes',
        'master' => 'Agencia Master',
        'general' => 'Agencia General',
        'telemedicina' => 'Telemedicina',
        'projects' => 'Proyectos',
        'metrics' => 'Métricas',
        self::PWA => 'PWA',
    ];

    /**
     * Métodos internos de Livewire: refrescos y sondeos, no acciones del usuario.
     *
     * @var list<string>
     */
    private const IGNORED_METHODS = ['$refresh', '$set', '$sync', '$commit', '__dispatch', '__lazyLoad', 'render'];

    public static function isLivewireUpdate(Request $request): bool
    {
        return $request->isMethod('POST') && Str::endsWith($request->path(), 'livewire/update');
    }

    /**
     * Ruta de la página que el usuario tiene abierta, sin parámetros de consulta.
     */
    public static function pagePath(Request $request): string
    {
        if (self::isLivewireUpdate($request)) {
            $referer = (string) $request->headers->get('referer', '');
            $path = (string) (parse_url($referer, PHP_URL_PATH) ?? '');

            return '/'.ltrim($path, '/');
        }

        return '/'.ltrim($request->path(), '/');
    }

    public static function panelFor(string $pagePath): string
    {
        $segment = strtolower((string) Str::of($pagePath)->ltrim('/')->before('/'));

        if ($segment === 'app') {
            return self::PWA;
        }

        return array_key_exists($segment, self::PANELS) ? $segment : 'web';
    }

    public static function panelLabel(string $panel): string
    {
        return self::PANELS[$panel] ?? 'Sitio web';
    }

    /**
     * Nombre legible de la página a partir de su ruta: "/business/affiliation-corporates/15" → "Affiliation corporates · 15".
     */
    public static function pageLabel(string $pagePath): string
    {
        $segments = array_values(array_filter(explode('/', trim($pagePath, '/'))));

        if ($segments === []) {
            return 'Inicio';
        }

        if (array_key_exists(strtolower($segments[0]), self::PANELS) || strtolower($segments[0]) === 'app') {
            array_shift($segments);
        }

        if ($segments === []) {
            return 'Escritorio';
        }

        $words = array_map(
            static fn (string $segment): string => ctype_digit($segment) ? '#'.$segment : Str::of($segment)->replace(['-', '_'], ' ')->ucfirst()->toString(),
            array_slice($segments, 0, 3),
        );

        return Str::limit(implode(' · ', $words), 90);
    }

    /**
     * Acción de Livewire que el usuario ejecutó: "EditAffiliationCorporate › save".
     * null si solo fue un refresco o sondeo.
     */
    public static function livewireAction(Request $request): ?string
    {
        if (! self::isLivewireUpdate($request)) {
            return null;
        }

        $components = $request->input('components');

        if (! is_array($components)) {
            return null;
        }

        $actions = [];

        foreach (array_slice($components, 0, 5) as $component) {
            $calls = is_array($component['calls'] ?? null) ? $component['calls'] : [];
            $methods = [];

            foreach ($calls as $call) {
                $method = (string) ($call['method'] ?? '');

                if ($method === '' || in_array($method, self::IGNORED_METHODS, true) || str_starts_with($method, '$')) {
                    continue;
                }

                $methods[] = self::describeCall($method, is_array($call['params'] ?? null) ? $call['params'] : []);
            }

            if ($methods === []) {
                continue;
            }

            $actions[] = self::componentName($component).' › '.implode(', ', array_unique($methods));
        }

        return $actions === [] ? null : Str::limit(implode(' | ', $actions), 160);
    }

    /**
     * Filament enruta las acciones por `mountAction('nombre')` / `callMountedAction`:
     * se muestra el nombre de la acción, que es lo que el usuario reconoce.
     *
     * @param  array<int, mixed>  $params
     */
    private static function describeCall(string $method, array $params): string
    {
        $first = $params[0] ?? null;

        if (in_array($method, ['mountAction', 'mountTableAction', 'mountTableBulkAction', 'mountFormComponentAction', 'mountInfolistAction'], true)
            && is_string($first) && $first !== '') {
            return 'abre «'.$first.'»';
        }

        if ($method === 'callMountedAction' || $method === 'callMountedTableAction' || $method === 'callMountedTableBulkAction') {
            return 'confirma la acción';
        }

        return $method;
    }

    /**
     * @param  array<string, mixed>  $component
     */
    private static function componentName(array $component): string
    {
        $snapshot = $component['snapshot'] ?? null;
        $decoded = is_string($snapshot) ? json_decode($snapshot, true) : null;
        $name = is_array($decoded) ? (string) ($decoded['memo']['name'] ?? '') : '';

        if ($name === '') {
            return 'Componente';
        }

        return (string) Str::of($name)->afterLast('.')->replace(['-', '_'], ' ')->title();
    }
}
