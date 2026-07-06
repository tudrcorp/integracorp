<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Models\Permission;
use App\Models\User;

final class UserNavigationAccess
{
    /**
     * Ítems de menú incluidos por defecto para analistas del módulo NEGOCIOS.
     *
     * @var array<string, list<string>>
     */
    private const ANALYST_DEFAULT_PERMISSION_SLUGS_BY_MODULE = [
        'NEGOCIOS' => [
            'agenda-corporativa',
            'calendarios-tdg',
        ],
    ];

    /**
     * @return list<string>
     */
    public static function defaultPermissionSlugsForModule(string $module): array
    {
        return self::ANALYST_DEFAULT_PERMISSION_SLUGS_BY_MODULE[strtoupper($module)] ?? [];
    }

    /**
     * @return list<int>
     */
    public static function defaultPermissionIdsForModule(string $module): array
    {
        $slugs = self::defaultPermissionSlugsForModule($module);

        if ($slugs === []) {
            return [];
        }

        return Permission::query()
            ->where('module', strtoupper($module))
            ->whereIn('slug', $slugs)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $permissionIds
     * @param  list<string>|array<int, string>  $departments
     * @return list<int>
     */
    public static function mergeAnalystDefaultPermissionIds(array $permissionIds, array $departments): array
    {
        $merged = $permissionIds;

        foreach ($departments as $department) {
            if (! is_string($department) || trim($department) === '') {
                continue;
            }

            $merged = array_merge($merged, self::defaultPermissionIdsForModule($department));
        }

        return array_values(array_unique(array_map(intval(...), $merged)));
    }

    /**
     * @param  list<string>  $permissionSlugs
     */
    public static function isAnalystDefaultMenuItem(string $module, array $permissionSlugs): bool
    {
        $defaults = self::defaultPermissionSlugsForModule($module);

        if ($defaults === []) {
            return false;
        }

        $checking = self::expandPermissionSlugs($permissionSlugs);

        foreach ($defaults as $defaultSlug) {
            if (in_array($defaultSlug, $checking, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function normalizedDepartments(User $user): array
    {
        $raw = $user->departament;

        if (! is_array($raw)) {
            return [];
        }

        $departments = [];

        foreach ($raw as $item) {
            if (is_string($item) && trim($item) !== '') {
                $departments[] = strtoupper(trim($item));
            }
        }

        return $departments;
    }

    public static function isSuperAdmin(User $user): bool
    {
        return in_array('SUPERADMIN', self::normalizedDepartments($user), true);
    }

    public static function userHasModule(User $user, string $module): bool
    {
        return in_array(strtoupper($module), self::normalizedDepartments($user), true);
    }

    /**
     * @param  list<string>  $permissionSlugs
     */
    public static function canAccessMenuItem(User $user, string $module, array $permissionSlugs): bool
    {
        if (self::isSuperAdmin($user)) {
            return true;
        }

        $module = strtoupper($module);

        if (! self::userHasModule($user, $module)) {
            return false;
        }

        $permissionSlugs = array_values(array_filter($permissionSlugs));

        if ($permissionSlugs === []) {
            return true;
        }

        if (self::isAnalystDefaultMenuItem($module, $permissionSlugs)) {
            return true;
        }

        if (! self::userHasAnyModulePermission($user, $module)) {
            return false;
        }

        return self::userHasAnyPermissionSlug($user, $module, $permissionSlugs);
    }

    public static function userHasAnyModulePermission(User $user, string $module): bool
    {
        $module = strtoupper($module);

        if ($user->relationLoaded('permissions')) {
            return $user->permissions->contains(
                fn (Permission $permission): bool => strtoupper((string) $permission->module) === $module
            );
        }

        return $user->permissions()
            ->where('permissions.module', $module)
            ->exists();
    }

    /**
     * @param  list<string>  $permissionSlugs
     */
    public static function canPerformModuleAction(User $user, string $module, string $permissionSlug): bool
    {
        if (self::isSuperAdmin($user)) {
            return true;
        }

        $module = strtoupper($module);

        if (! self::userHasModule($user, $module)) {
            return false;
        }

        return self::userHasAnyPermissionSlug($user, $module, [$permissionSlug]);
    }

    public static function userHasAnyPermissionSlug(User $user, string $module, array $permissionSlugs): bool
    {
        $module = strtoupper($module);
        $expandedSlugs = self::expandPermissionSlugs($permissionSlugs);

        if ($user->relationLoaded('permissions')) {
            return $user->permissions->contains(
                fn (Permission $permission): bool => strtoupper((string) $permission->module) === $module
                    && in_array($permission->slug, $expandedSlugs, true)
            );
        }

        return $user->permissions()
            ->where('permissions.module', $module)
            ->whereIn('permissions.slug', $expandedSlugs)
            ->exists();
    }

    /**
     * @param  list<string>  $permissionSlugs
     * @return list<string>
     */
    public static function expandPermissionSlugs(array $permissionSlugs): array
    {
        $expanded = [];

        foreach ($permissionSlugs as $slug) {
            $expanded[] = $slug;
            $expanded = array_merge($expanded, self::legacyAliasesForSlug($slug));
            $expanded = array_merge($expanded, self::navAliasesForSlug($slug));
        }

        return array_values(array_unique($expanded));
    }

    /**
     * @return list<string>
     */
    private static function legacyAliasesForSlug(string $slug): array
    {
        foreach (UserFormPermissionOptions::navToLegacySlugAliases() as $navKey => $legacySlugs) {
            if (in_array($slug, $legacySlugs, true)) {
                return $legacySlugs;
            }
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private static function navAliasesForSlug(string $slug): array
    {
        if (str_starts_with($slug, 'nav.')) {
            $legacySlugs = [];

            foreach (UserFormPermissionOptions::navToLegacySlugAliases() as $navKey => $aliases) {
                if (str_ends_with($slug, '.'.$navKey)) {
                    $legacySlugs = array_merge($legacySlugs, $aliases);
                }
            }

            return $legacySlugs;
        }

        return self::navSlugsFromLegacySlug($slug);
    }

    /**
     * @return list<string>
     */
    private static function navSlugsFromLegacySlug(string $legacySlug): array
    {
        $navSlugs = [];

        foreach (UserFormPermissionOptions::navToLegacySlugAliases() as $navKey => $legacySlugs) {
            if (! in_array($legacySlug, $legacySlugs, true)) {
                continue;
            }

            foreach (InternalPanelDepartmentMap::internalPanelIds() as $panelId) {
                $navSlugs[] = "nav.{$panelId}.{$navKey}";
            }
        }

        return $navSlugs;
    }
}
