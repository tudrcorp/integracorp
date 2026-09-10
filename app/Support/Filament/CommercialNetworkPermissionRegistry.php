<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Models\Permission;

final class CommercialNetworkPermissionRegistry
{
    public const MODULE = 'RED_COMERCIAL';

    public const PATIENTS = 'pacientes-telemedicina';

    public const CASES = 'gestion-casos';

    public const FIELD_KEY = 'commercial_network_permission_ids';

    /**
     * @return array<string, array{name: string, description: string}>
     */
    public static function all(): array
    {
        return [
            self::PATIENTS => [
                'name' => 'Ver pacientes de telemedicina',
                'description' => 'Consulta la ficha de pacientes de telemedicina de sus afiliados. No puede crear ni editar.',
            ],
            self::CASES => [
                'name' => 'Ver gestión de casos',
                'description' => 'Consulta casos abiertos y sus consultas. No incluye historia clínica ni altas médicas.',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        self::ensurePermissionsExist();

        $options = [];

        foreach (self::all() as $slug => $definition) {
            $permission = Permission::query()
                ->where('module', self::MODULE)
                ->where('slug', $slug)
                ->first();

            if ($permission === null) {
                continue;
            }

            $options[(int) $permission->id] = $definition['name'];
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public static function optionDescriptions(): array
    {
        self::ensurePermissionsExist();

        $descriptions = [];

        foreach (self::all() as $slug => $definition) {
            $permission = Permission::query()
                ->where('module', self::MODULE)
                ->where('slug', $slug)
                ->first();

            if ($permission === null) {
                continue;
            }

            $descriptions[(int) $permission->id] = $definition['description'];
        }

        return $descriptions;
    }

    /**
     * @return list<int>
     */
    public static function permissionIds(): array
    {
        self::ensurePermissionsExist();

        return Permission::query()
            ->where('module', self::MODULE)
            ->whereIn('slug', array_keys(self::all()))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    public static function ensurePermissionsExist(): void
    {
        foreach (self::all() as $slug => $definition) {
            $permission = Permission::query()->firstOrNew([
                'slug' => $slug,
                'module' => self::MODULE,
            ]);

            $permission->name = $definition['name'];
            $permission->created_by ??= 'system';
            $permission->updated_by = 'system';
            $permission->save();
        }
    }
}
