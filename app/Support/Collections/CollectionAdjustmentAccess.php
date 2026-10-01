<?php

declare(strict_types=1);

namespace App\Support\Collections;

use App\Models\User;
use App\Support\Filament\UserNavigationAccess;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Quién puede ajustar cuotas a mano: el departamento de Administración y SUPERADMIN.
 * Se revisa en la UI (visibilidad de las acciones) y otra vez en el servicio, para
 * que una llamada directa a la acción tampoco pase sin permiso.
 */
final class CollectionAdjustmentAccess
{
    public const DEPARTMENT = 'ADMINISTRACION';

    public static function userCan(?Authenticatable $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return UserNavigationAccess::isSuperAdmin($user)
            || UserNavigationAccess::userHasModule($user, self::DEPARTMENT);
    }
}
