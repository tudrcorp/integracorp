<?php

declare(strict_types=1);

namespace App\Support\OrgStructure;

use App\Models\User;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\InternalPanelDepartmentMap;
use App\Support\Filament\UserNavigationAccess;
use Filament\Facades\Filament;
use Throwable;

/**
 * Quién puede hacer qué con la estructura organizacional.
 *
 * - Ver: todo colaborador interno en Negocios. En Administración, Operaciones,
 *   Marketing, Proyectos y Métricas solo si hay una versión publicada.
 *   Telemedicina no lo muestra. Se excluye a los usuarios de proveedores
 *   externos, que entran a Operaciones pero no son colaboradores de la empresa.
 * - Editar borradores: permiso `editar-estructura-organizacional` (Negocios).
 * - Publicar sin aprobación y aprobar: SUPERADMIN.
 * - Exportar: permiso `exportar-organigrama` en Negocios.
 */
final class OrgStructureAccess
{
    public const EDIT_MODULE = 'NEGOCIOS';

    public static function canView(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->supplier_id === null && ! $user->isProveedorAmd();
    }

    public static function canEdit(?User $user): bool
    {
        if (! self::canView($user)) {
            return false;
        }

        return UserNavigationAccess::canPerformModuleAction(
            $user,
            self::EDIT_MODULE,
            BusinessFilamentActionPermissionRegistry::EDIT_ORG_STRUCTURE,
        );
    }

    /**
     * Un SUPERADMIN publica sus borradores directo y aprueba los de los demás.
     */
    public static function canPublishDirectly(?User $user): bool
    {
        return $user !== null && self::canView($user) && UserNavigationAccess::isSuperAdmin($user);
    }

    public static function canReview(?User $user): bool
    {
        return self::canPublishDirectly($user);
    }

    public static function canOpenStudio(?User $user): bool
    {
        return self::canEdit($user) || self::canReview($user);
    }

    public static function canExport(?User $user): bool
    {
        if (! self::canView($user)) {
            return false;
        }

        if (UserNavigationAccess::isSuperAdmin($user)) {
            return true;
        }

        $slug = BusinessFilamentActionPermissionRegistry::EXPORT_ORG_CHART;
        $module = self::currentPanelModule();

        if ($module !== null && BusinessFilamentActionPermissionRegistry::slugIsAvailableInModule($slug, $module)) {
            return UserNavigationAccess::canPerformModuleAction($user, $module, $slug);
        }

        /** Fuera de un panel interno (rutas, jobs, panel admin): basta tenerlo en cualquier módulo. */
        foreach (BusinessFilamentActionPermissionRegistry::modulesForSlug($slug) as $candidate) {
            if (UserNavigationAccess::canPerformModuleAction($user, $candidate, $slug)) {
                return true;
            }
        }

        return false;
    }

    private static function currentPanelModule(): ?string
    {
        try {
            $panelId = Filament::getCurrentPanel()?->getId();
        } catch (Throwable) {
            return null;
        }

        return $panelId === null ? null : InternalPanelDepartmentMap::moduleForPanel($panelId);
    }
}
