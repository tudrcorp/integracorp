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
     * Ids de los permisos de la red comercial, por slug, resueltos una sola
     * vez por petición.
     *
     * El formulario de usuario pedía opciones y descripciones varias veces por
     * render, y cada llamada revalidaba la existencia de los permisos y
     * consultaba uno por uno: veinte consultas para dos permisos.
     *
     * @var array<string, int>|null
     */
    private static ?array $permissionIdsBySlug = null;

    private static bool $permissionsEnsured = false;

    /**
     * Olvida lo memoizado. Para tests y para quien cree permisos en caliente.
     */
    public static function flush(): void
    {
        self::$permissionIdsBySlug = null;
        self::$permissionsEnsured = false;
    }

    /**
     * @return array<string, int>
     */
    private static function permissionIdsBySlug(): array
    {
        if (self::$permissionIdsBySlug !== null) {
            return self::$permissionIdsBySlug;
        }

        self::ensurePermissionsExist();

        /** @var array<string, int> $ids */
        $ids = Permission::query()
            ->where('module', self::MODULE)
            ->whereIn('slug', array_keys(self::all()))
            ->pluck('id', 'slug')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return self::$permissionIdsBySlug = $ids;
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return self::mapDefinitions('name');
    }

    /**
     * @return array<int, string>
     */
    public static function optionDescriptions(): array
    {
        return self::mapDefinitions('description');
    }

    /**
     * @return array<int, string>
     */
    private static function mapDefinitions(string $field): array
    {
        $ids = self::permissionIdsBySlug();
        $mapped = [];

        foreach (self::all() as $slug => $definition) {
            if (! isset($ids[$slug])) {
                continue;
            }

            $mapped[$ids[$slug]] = (string) $definition[$field];
        }

        return $mapped;
    }

    /**
     * @return list<int>
     */
    public static function permissionIds(): array
    {
        return array_values(self::permissionIdsBySlug());
    }

    public static function ensurePermissionsExist(): void
    {
        if (self::$permissionsEnsured) {
            return;
        }

        self::$permissionsEnsured = true;

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
