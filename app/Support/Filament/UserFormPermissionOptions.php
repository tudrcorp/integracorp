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
     * @return array<string, list<string>>
     */
    public static function navToLegacySlugAliases(): array
    {
        return self::NAV_TO_LEGACY_SLUG_ALIASES;
    }

    /**
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
        'affiliateresource' => ['afiliados-individuales'],
        'affiliatecorporateresource' => ['afiliados-corporativos'],
        'operationinventoryresource' => ['inventario-general'],
        'operationinventoryentryresource' => ['entradas-inventario'],
        'operationinventoryoutflowresource' => ['salidas-inventario'],
        'operationinventorymovementresource' => ['movimientos-inventario'],
        'telemedicinedoctorresource' => ['doctores'],
        'telemedicinepatientresource' => ['pacientes'],
        'telemedicinecaseresource' => ['gestion-casos'],
        'telemedicinehistorypatientresource' => ['historia-clinica'],
        'operationcoordinationserviceresource' => ['servicios-medicos'],
        'operationserviceorderresource' => ['ordenes-servicios'],
        'accountsreceivableresource' => ['cuentas-por-cobrar'],
        'accountspayableresource' => ['cuentas-por-pagar'],
        'operationtypeserviceresource' => ['tipos-servicios'],
        'operationtypenegotiationresource' => ['tipos-negociacion'],
        'operationstatusserviceresource' => ['estados-servicio'],
        'operationoncalluserresource' => ['roles-de-guardia'],
        'supplierresource' => ['proveedores-juridicos'],
        'doctornurseresource' => ['proveedores-naturales'],
        'indicadoresdedesempenoresource' => ['indicadores-desempeno'],
        'corporateallyresource' => ['aliados-corporativos'],
        'dashboardoperaciones' => ['dashboard-operaciones'],
        'projectresource' => ['proyectos'],
        'subprojectresource' => ['subproyectos'],
        'groupresource' => ['equipos'],
        'departmentresource' => ['departamentos-pm'],
        'activityresource' => ['actividades'],
        'kanban' => ['kanban'],
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

    /**
     * @return array<string, array<int|string, string>>
     */
    public static function groupedOptionsForModule(string $module): array
    {
        $grouped = [];

        foreach (self::forModule($module) as $permission) {
            $group = PermissionNavigationGroupResolver::groupForPermission($permission);
            $grouped[$group][$permission->id] = $permission->name;
        }

        uksort($grouped, fn (string $left, string $right): int => self::sortNavigationGroups($left, $right));

        foreach ($grouped as $group => $options) {
            asort($options);
            $grouped[$group] = $options;
        }

        return $grouped;
    }

    /**
     * @return array<string, Collection<int, Permission>>
     */
    public static function groupedPermissionsForModule(string $module): array
    {
        $grouped = [];

        foreach (self::forModule($module) as $permission) {
            $group = PermissionNavigationGroupResolver::groupForPermission($permission);
            $grouped[$group] ??= new Collection;
            $grouped[$group]->push($permission);
        }

        uksort($grouped, fn (string $left, string $right): int => self::sortNavigationGroups($left, $right));

        return $grouped;
    }

    private static function sortNavigationGroups(string $left, string $right): int
    {
        if ($left === 'Otros' && $right !== 'Otros') {
            return 1;
        }

        if ($right === 'Otros' && $left !== 'Otros') {
            return -1;
        }

        return strnatcasecmp($left, $right);
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
