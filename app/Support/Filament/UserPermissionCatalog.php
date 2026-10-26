<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Filament\Business\Resources\Users\Schemas\UserForm;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo de permisos asignables a un usuario, en la forma que usa el editor
 * de permisos: módulo → grupo de menú → permiso.
 *
 * Es también la **frontera de confianza** del editor: el navegador manda una
 * lista de ids y aquí se queda solo lo que es asignable y pertenece a un
 * módulo que el usuario tiene. Cualquier otro id (otro módulo, un permiso
 * solo de SUPERADMIN, un id inventado) se descarta.
 */
final class UserPermissionCatalog
{
    /** @var array<int, array{module: string, group: string, name: string}>|null */
    private static ?array $index = null;

    /**
     * @return list<array{key: string, label: string, subtitle: string, accent: string, total: int, groups: list<array{name: string, items: list<array{id: int, name: string, action: bool}>}>}>
     */
    public static function modules(): array
    {
        $actionSlugs = array_flip(array_keys(BusinessFilamentActionPermissionRegistry::all()));
        $modules = [];

        foreach (UserForm::getPermissionAssignableModules() as $module) {
            $groups = [];

            foreach (UserFormPermissionOptions::groupedPermissionsForModule($module) as $group => $permissions) {
                $groups[] = [
                    'name' => (string) $group,
                    'items' => $permissions
                        ->sortBy(fn (Permission $permission): string => mb_strtolower((string) $permission->name))
                        ->map(fn (Permission $permission): array => [
                            'id' => (int) $permission->id,
                            'name' => (string) $permission->name,
                            'action' => isset($actionSlugs[(string) $permission->slug]),
                        ])
                        ->values()
                        ->all(),
                ];
            }

            if ($groups === []) {
                continue;
            }

            $modules[] = [
                'key' => $module,
                'label' => UserPermissionFormUi::moduleDisplayLabel($module),
                'subtitle' => UserPermissionFormUi::moduleMenuSubtitle($module),
                'accent' => UserPermissionFormUi::moduleAccent($module),
                'total' => array_sum(array_map(static fn (array $group): int => count($group['items']), $groups)),
                'groups' => $groups,
            ];
        }

        return $modules;
    }

    /**
     * Permiso asignable => su módulo, grupo y nombre.
     *
     * @return array<int, array{module: string, group: string, name: string}>
     */
    public static function index(): array
    {
        if (self::$index !== null) {
            return self::$index;
        }

        $index = [];

        foreach (self::modules() as $module) {
            foreach ($module['groups'] as $group) {
                foreach ($group['items'] as $item) {
                    $index[$item['id']] = ['module' => $module['key'], 'group' => $group['name'], 'name' => $item['name']];
                }
            }
        }

        return self::$index = $index;
    }

    /**
     * Deja solo los permisos asignables de los módulos que el usuario tiene.
     *
     * @param  array<mixed>  $ids
     * @param  array<mixed>  $departments
     * @return list<int>
     */
    public static function sanitize(array $ids, array $departments): array
    {
        $index = self::index();
        $modules = array_flip(array_filter($departments, static fn (mixed $department): bool => is_string($department) && trim($department) !== ''));
        $out = [];

        foreach ($ids as $id) {
            if (! is_numeric($id)) {
                continue;
            }

            $id = (int) $id;
            $module = $index[$id]['module'] ?? null;

            if ($module !== null && isset($modules[$module])) {
                $out[$id] = $id;
            }
        }

        return array_values($out);
    }

    /**
     * Permisos asignables (de cualquier módulo) de un usuario.
     *
     * @return list<int>
     */
    public static function assignableIdsOf(int $userId): array
    {
        $index = self::index();

        return DB::table('user_permissions')
            ->where('user_id', $userId)
            ->pluck('permission_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => isset($index[$id]))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $before
     * @param  list<int>  $after
     * @return array{added: list<int>, removed: list<int>}
     */
    public static function diff(array $before, array $after): array
    {
        return [
            'added' => array_values(array_diff($after, $before)),
            'removed' => array_values(array_diff($before, $after)),
        ];
    }

    /**
     * Nombres legibles («Negocios · Afiliaciones › Individuales») para auditoría.
     *
     * @param  list<int>  $ids
     * @return list<string>
     */
    public static function describe(array $ids): array
    {
        $index = self::index();

        return array_values(array_map(static fn (int $id): string => isset($index[$id])
            ? UserPermissionFormUi::moduleDisplayLabel($index[$id]['module']).' · '.$index[$id]['group'].' › '.$index[$id]['name']
            : '#'.$id, $ids));
    }

    /**
     * Olvida lo memoizado (tests y tras sincronizar permisos).
     */
    public static function flush(): void
    {
        self::$index = null;
    }
}
