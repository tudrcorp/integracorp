<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use Filament\Pages\Page as FilamentPage;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Throwable;

/**
 * Traduce una ruta a lo que el usuario ve en el menú.
 *
 * "/business/affiliation-corporates/15/edit" → "Afiliaciones › Corporativas · editar #15".
 * Sale de la propia página de Filament (etiqueta y grupo del menú), así que
 * cualquier recurso nuevo queda cubierto sin mantener listas a mano.
 */
final class PageLabelResolver
{
    /**
     * @var array<string, array{label: string|null, route: string|null, controller: string|null}>
     */
    private static array $memo = [];

    public static function label(string $path): ?string
    {
        return self::resolve($path)['label'];
    }

    public static function routeName(string $path): ?string
    {
        return self::resolve($path)['route'];
    }

    /**
     * @return array{label: string|null, route: string|null, controller: string|null}
     */
    private static function resolve(string $path): array
    {
        $path = '/'.ltrim((string) (parse_url($path, PHP_URL_PATH) ?? ''), '/');

        if (isset(self::$memo[$path])) {
            return self::$memo[$path];
        }

        if (count(self::$memo) > 300) {
            self::$memo = [];
        }

        $result = ['label' => null, 'route' => null, 'controller' => null];

        try {
            /** @var Route $route */
            $route = app('router')->getRoutes()->match(Request::create($path, 'GET'));
            $uses = $route->getAction('uses');
            $class = is_string($uses) ? explode('@', $uses)[0] : null;

            $result['route'] = $route->getName();
            $result['controller'] = $class;
            $result['label'] = $class !== null && class_exists($class)
                ? self::labelForClass($class, $route->parameters())
                : null;
        } catch (Throwable) {
        }

        return self::$memo[$path] = $result;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private static function labelForClass(string $class, array $parameters): ?string
    {
        if (is_subclass_of($class, ResourcePage::class)) {
            $resource = $class::getResource();
            $label = self::withGroup((string) $resource::getNavigationLabel(), $resource::getNavigationGroup());
            $record = $parameters['record'] ?? null;
            $recordSuffix = is_scalar($record) && (string) $record !== '' ? ' #'.$record : '';

            return match (true) {
                is_subclass_of($class, ListRecords::class) => $label,
                is_subclass_of($class, CreateRecord::class) => $label.' · crear',
                is_subclass_of($class, EditRecord::class) => $label.' · editar'.$recordSuffix,
                is_subclass_of($class, ViewRecord::class) => $label.' · ver'.$recordSuffix,
                default => $label.($recordSuffix !== '' ? ' ·'.$recordSuffix : ''),
            };
        }

        if (is_subclass_of($class, FilamentPage::class)) {
            return self::withGroup((string) $class::getNavigationLabel(), $class::getNavigationGroup());
        }

        return null;
    }

    private static function withGroup(string $label, mixed $group): string
    {
        $group = is_string($group) ? trim($group) : ($group instanceof \UnitEnum ? (string) ($group->value ?? $group->name) : '');

        if ($group === '' || $label === '') {
            return $label;
        }

        $groupLabel = Str::ucfirst(mb_strtolower($group));

        /** "Proveedores Jurídicos" ya dice el grupo: no se repite. */
        if (str_contains(mb_strtolower($label), mb_strtolower($groupLabel))) {
            return $label;
        }

        return $groupLabel.' › '.$label;
    }
}
