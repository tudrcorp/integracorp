<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Models\Permission;
use Illuminate\Support\Collection;

class UserFormPermissionOptions
{
    /**
     * Relaciona permisos auto-generados (nav.*) con slugs legacy ya usados en canAccess().
     *
     * @var array<string, list<string>>
     */
    private const NAV_TO_LEGACY_SLUG_ALIASES = [
        'accountmanagerresource' => ['account-manager'],
        'affiliationcorporateresource' => ['afiliaciones-corporativas', 'afiliados-corporativos'],
        'affiliationresource' => ['afiliaciones-individuales', 'afiliados-individuales'],
        'agencyresource' => ['agencias-de-corretaje'],
        'agentresource' => ['agentes-de-corretaje'],
        'benefitcoverageresource' => ['beneficios-coberturas'],
        'benefitresource' => ['beneficios'],
        'birthdaynotificationresource' => ['notificaciones-cumpleaños'],
        'businesslineresource' => ['lineas-de-servicio'],
        'capemiacresource' => ['capemiac'],
        'cityresource' => ['ciudades'],
        'collaboratoranniversaryresource' => ['colaborador-aniversario'],
        'commissionpayrollresource' => ['reporte-de-comisiones'],
        'commissionresource' => ['detallado-de-comisiones'],
        'contactlistresource' => ['contactos'],
        'datanotificationresource' => ['destinatarios'],
        'downloadzoneresource' => ['documentos'],
        'eventresource' => ['eventos'],
        'feeresource' => ['tarifas-costos'],
        'helpdeskresource' => ['helpdesks', 'helpdesk'],
        'infofreeresource' => ['data-externa'],
        'massnotificationresource' => ['notificaciones-masivas'],
        'planresource' => ['planes'],
        'regionresource' => ['regiones'],
        'rrhhasignacionresource' => ['asignaciones'],
        'rrhhcolaboradorresource' => ['colaboradores'],
        'rrhhdeduccionresource' => ['deducciones'],
        'rrhhdepartamentoresource' => ['departamentos'],
        'saleresource' => ['ventas'],
        'stateresource' => ['estados'],
        'travelagencyresource' => ['agencias-de-viaje'],
        'travelagentresource' => ['agentes-de-viaje'],
        'whitecompanyresource' => ['empresas-aliadas'],
        'zoneresource' => ['gestion-de-carpetas'],
    ];

    /**
     * @return Collection<int, Permission>
     */
    public static function forModule(string $module): Collection
    {
        $permissions = Permission::query()
            ->where('module', $module)
            ->orderBy('name')
            ->get();

        return self::filterAssignable($permissions);
    }

    /**
     * @return array<int|string, string>
     */
    public static function optionsForModule(string $module): array
    {
        return self::forModule($module)
            ->pluck('name', 'id')
            ->all();
    }

    public static function countForModule(string $module): int
    {
        return self::forModule($module)->count();
    }

    /**
     * @param  Collection<int, Permission>  $permissions
     * @return Collection<int, Permission>
     */
    public static function filterAssignable(Collection $permissions): Collection
    {
        $legacyPermissions = $permissions->filter(
            fn (Permission $permission): bool => ! str_starts_with($permission->slug, 'nav.')
        );

        $excludedNavIds = $permissions
            ->filter(fn (Permission $permission): bool => str_starts_with($permission->slug, 'nav.'))
            ->filter(function (Permission $navPermission) use ($legacyPermissions): bool {
                foreach ($legacyPermissions as $legacyPermission) {
                    if (self::isNavShadowedByLegacy($navPermission, $legacyPermission)) {
                        return true;
                    }
                }

                return false;
            })
            ->pluck('id');

        return $permissions
            ->reject(fn (Permission $permission): bool => $excludedNavIds->contains($permission->id))
            ->values();
    }

    private static function isNavShadowedByLegacy(Permission $navPermission, Permission $legacyPermission): bool
    {
        if (self::normalizeName($navPermission->name) === self::normalizeName($legacyPermission->name)) {
            return true;
        }

        if (! str_starts_with($navPermission->slug, 'nav.')) {
            return false;
        }

        $navResourceKey = self::navResourceKey($navPermission->slug);
        $legacyAliases = self::NAV_TO_LEGACY_SLUG_ALIASES[$navResourceKey] ?? [];

        return in_array($legacyPermission->slug, $legacyAliases, true);
    }

    private static function navResourceKey(string $slug): string
    {
        $parts = explode('.', $slug);

        return $parts[2] ?? '';
    }

    private static function normalizeName(string $name): string
    {
        $normalized = mb_strtolower($name);
        $normalized = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'u'],
            $normalized
        );

        return preg_replace('/[^a-z0-9]/', '', $normalized) ?? $normalized;
    }
}
