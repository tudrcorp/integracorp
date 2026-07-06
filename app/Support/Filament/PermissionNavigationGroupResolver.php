<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Models\Permission;
use ReflectionClass;

final class PermissionNavigationGroupResolver
{
    /** @var array<string, class-string|null> */
    private static array $slugModuleToClass = [];

    public static function groupForPermission(Permission $permission): string
    {
        $class = self::resolveClassForPermission($permission);

        if ($class === null) {
            return 'Otros';
        }

        $group = self::navigationGroupForClass($class);

        if ($group === null || trim($group) === '') {
            return 'Otros';
        }

        return $group;
    }

    /**
     * @return class-string|null
     */
    public static function resolveClassForPermission(Permission $permission): ?string
    {
        $mapKey = $permission->slug.'|'.($permission->module ?? '');

        if (array_key_exists($mapKey, self::$slugModuleToClass)) {
            return self::$slugModuleToClass[$mapKey];
        }

        $resolved = self::lookupClass($permission);
        self::$slugModuleToClass[$mapKey] = $resolved;

        return $resolved;
    }

    /**
     * @return class-string|null
     */
    private static function lookupClass(Permission $permission): ?string
    {
        $module = (string) ($permission->module ?? '');

        foreach (DepartmentNavigationPermissionRegistry::allMappings() as $class => $slugs) {
            if (! in_array($permission->slug, $slugs, true)) {
                continue;
            }

            if (InternalPanelDepartmentMap::moduleForClass($class) === $module) {
                return $class;
            }
        }

        if (str_starts_with($permission->slug, 'nav.')) {
            return self::resolveClassFromNavSlug($permission->slug, $module);
        }

        return null;
    }

    /**
     * @return class-string|null
     */
    private static function resolveClassFromNavSlug(string $slug, string $module): ?string
    {
        $parts = explode('.', $slug);
        $resourceBase = strtolower($parts[2] ?? '');

        if ($resourceBase === '') {
            return null;
        }

        foreach (DepartmentNavigationPermissionRegistry::allMappings() as $class => $slugs) {
            if (InternalPanelDepartmentMap::moduleForClass($class) !== $module) {
                continue;
            }

            if (strtolower(class_basename($class)) === $resourceBase) {
                return $class;
            }
        }

        return null;
    }

    /**
     * @param  class-string  $class
     */
    private static function navigationGroupForClass(string $class): ?string
    {
        if (method_exists($class, 'getNavigationGroup')) {
            $group = $class::getNavigationGroup();

            if (is_string($group) && $group !== '') {
                return $group;
            }
        }

        $reflection = new ReflectionClass($class);

        if ($reflection->hasProperty('navigationGroup')) {
            $property = $reflection->getProperty('navigationGroup');
            $property->setAccessible(true);
            $value = $property->getDefaultValue();

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
