<?php

declare(strict_types=1);

namespace App\Support\Operations\MedicalGuards;

use App\Models\User;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\UserNavigationAccess;

/**
 * Las guardias médicas las ve todo analista de Operaciones; los usuarios de un
 * proveedor no, aunque entren a ese panel. Cargarlas pide un permiso propio.
 */
final class MedicalGuardAccess
{
    public static function canView(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if (UserNavigationAccess::isSuperAdmin($user)) {
            return true;
        }

        return UserNavigationAccess::userHasModule($user, 'OPERACIONES')
            && $user->supplier_id === null
            && ! $user->isProveedorAmd();
    }

    public static function canManage(?User $user): bool
    {
        return $user instanceof User
            && self::canView($user)
            && UserNavigationAccess::canPerformModuleAction($user, 'OPERACIONES', BusinessFilamentActionPermissionRegistry::MANAGE_MEDICAL_GUARDS);
    }
}
