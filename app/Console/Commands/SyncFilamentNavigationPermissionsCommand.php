<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Permission;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Filament\InternalPanelDepartmentMap;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use ReflectionClass;

class SyncFilamentNavigationPermissionsCommand extends Command
{
    protected $signature = 'permissions:sync-navigation {--panel= : Panel ID específico (business, administration, etc.)}';

    protected $description = 'Sincroniza permisos de ítems de menú Filament en la tabla permissions';

    public function handle(): int
    {
        $panelFilter = $this->option('panel');
        $created = 0;
        $updated = 0;

        foreach (InternalPanelDepartmentMap::internalPanelIds() as $panelId) {
            if ($panelFilter !== null && $panelFilter !== $panelId) {
                continue;
            }

            $module = InternalPanelDepartmentMap::moduleForPanel($panelId);

            if ($module === null) {
                continue;
            }

            $panel = Filament::getPanel($panelId);

            foreach ($panel->getResources() as $resourceClass) {
                if (! is_subclass_of($resourceClass, Resource::class)) {
                    continue;
                }

                [$countCreated, $countUpdated] = $this->syncClassPermission(
                    $resourceClass,
                    $module,
                    $panelId,
                    $this->resolveNavigationLabel($resourceClass),
                );

                $created += $countCreated;
                $updated += $countUpdated;
            }

            foreach ($panel->getPages() as $pageClass) {
                if (! is_subclass_of($pageClass, Page::class)) {
                    continue;
                }

                if ($pageClass === \Filament\Pages\Dashboard::class) {
                    continue;
                }

                [$countCreated, $countUpdated] = $this->syncClassPermission(
                    $pageClass,
                    $module,
                    $panelId,
                    $this->resolveNavigationLabel($pageClass),
                );

                $created += $countCreated;
                $updated += $countUpdated;
            }

            foreach ($panel->getClusters() as $clusterClass) {
                [$countCreated, $countUpdated] = $this->syncClassPermission(
                    $clusterClass,
                    $module,
                    $panelId,
                    $this->resolveNavigationLabel($clusterClass),
                );

                $created += $countCreated;
                $updated += $countUpdated;
            }
        }

        $this->info("Permisos sincronizados: {$created} creados, {$updated} actualizados.");

        return self::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function syncClassPermission(string $class, string $module, string $panelId, string $label): array
    {
        if (DepartmentNavigationPermissionRegistry::isSuperAdminOnly($class)) {
            return [0, 0];
        }

        $registrySlugs = DepartmentNavigationPermissionRegistry::slugsFor($class);
        $slugs = $registrySlugs !== [] ? $registrySlugs : [$this->defaultSlugForClass($class, $panelId)];

        $created = 0;
        $updated = 0;

        foreach ($slugs as $slug) {
            if (str_starts_with($slug, 'nav.')) {
                continue;
            }

            $permission = Permission::query()->firstOrNew([
                'slug' => $slug,
                'module' => $module,
            ]);

            $isNew = ! $permission->exists;

            $permission->name = $label;
            $permission->created_by ??= 'system';
            $permission->updated_by = 'system';
            $permission->save();

            if ($isNew) {
                $created++;
            } else {
                $updated++;
            }

            $navSlug = "nav.{$panelId}.".Str::lower(class_basename($class));

            $navPermission = Permission::query()->firstOrNew([
                'slug' => $navSlug,
                'module' => $module,
            ]);

            $navIsNew = ! $navPermission->exists;
            $navPermission->name = $label;
            $navPermission->created_by ??= 'system';
            $navPermission->updated_by = 'system';
            $navPermission->save();

            if ($navIsNew) {
                $created++;
            } else {
                $updated++;
            }
        }

        return [$created, $updated];
    }

    private function defaultSlugForClass(string $class, string $panelId): string
    {
        $registrySlugs = DepartmentNavigationPermissionRegistry::slugsFor($class);

        if ($registrySlugs !== []) {
            return $registrySlugs[0];
        }

        return Str::slug(Str::headline(class_basename($class)));
    }

    private function resolveNavigationLabel(string $class): string
    {
        if (method_exists($class, 'getNavigationLabel')) {
            $label = $class::getNavigationLabel();

            if (is_string($label) && $label !== '') {
                return $label;
            }
        }

        $reflection = new ReflectionClass($class);

        if ($reflection->hasProperty('navigationLabel')) {
            $property = $reflection->getProperty('navigationLabel');
            $property->setAccessible(true);
            $value = $property->getDefaultValue();

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return Str::headline(class_basename($class));
    }
}
