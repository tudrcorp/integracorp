<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Models\User;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\UserNavigationAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quién supervisa la bandeja de un panel: SUPERADMIN o quien tenga el permiso de acción
 * «Supervisar Atención WhatsApp» en el módulo de ese panel.
 */
final class CrmInboxSupervisor
{
    public static function moduleFor(string $panel): string
    {
        return match ($panel) {
            'operations' => 'OPERACIONES',
            'administration' => 'ADMINISTRACION',
            default => 'NEGOCIOS',
        };
    }

    public static function can(?User $user, string $panel): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if (UserNavigationAccess::isSuperAdmin($user)) {
            return true;
        }

        if (! Schema::hasTable('permissions') || ! Schema::hasTable('user_permissions')) {
            return false;
        }

        return UserNavigationAccess::canPerformModuleAction(
            $user,
            self::moduleFor($panel),
            BusinessFilamentActionPermissionRegistry::SUPERVISE_WHATSAPP_INBOX,
        );
    }

    /**
     * Ids de los supervisores del panel, sin cargar usuarios. Para avisos.
     *
     * @return list<int>
     */
    public static function idsFor(string $panel): array
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('user_permissions')) {
            return [];
        }

        return DB::table('user_permissions')
            ->join('permissions', 'permissions.id', '=', 'user_permissions.permission_id')
            ->where('permissions.slug', BusinessFilamentActionPermissionRegistry::SUPERVISE_WHATSAPP_INBOX)
            ->where('permissions.module', self::moduleFor($panel))
            ->pluck('user_permissions.user_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
