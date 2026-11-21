<?php

declare(strict_types=1);

namespace App\Support\NotificationCenter;

use App\Filament\Business\Pages\ManageCompanyAssociateNotifications;
use App\Models\User;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Filament\UserNavigationAccess;
use Illuminate\Support\Facades\Auth;

/**
 * Quién entra al Centro de notificaciones y qué puede hacer.
 *
 * - SUPERADMIN: todo.
 * - Analista de Negocios con el permiso «centro-notificaciones»: gestiona tipos,
 *   canales por tipo y destinatarios, y ve la bitácora.
 * - Solo SUPERADMIN: interruptores generales de canal, pausar un tipo crítico y leer
 *   el contenido de mensajes externos (pueden traer datos clínicos).
 */
final class NotificationCenterAccess
{
    public const PERMISSION_SLUG = 'centro-notificaciones';

    public static function canAccess(?User $user = null): bool
    {
        $user ??= Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        if (UserNavigationAccess::isSuperAdmin($user)) {
            return true;
        }

        return UserNavigationAccess::canAccessMenuItem(
            $user,
            'NEGOCIOS',
            DepartmentNavigationPermissionRegistry::slugsFor(ManageCompanyAssociateNotifications::class) ?: [self::PERMISSION_SLUG],
        );
    }

    public static function isSuperAdmin(?User $user = null): bool
    {
        $user ??= Auth::user();

        return $user instanceof User && UserNavigationAccess::isSuperAdmin($user);
    }

    public static function canManageCritical(?User $user = null): bool
    {
        return self::isSuperAdmin($user);
    }

    public static function canManageChannels(?User $user = null): bool
    {
        return self::isSuperAdmin($user);
    }

    public static function canReadExternalContent(?User $user = null): bool
    {
        return self::isSuperAdmin($user);
    }

    public static function actorName(): string
    {
        return (string) (Auth::user()?->name ?? 'sistema');
    }
}
