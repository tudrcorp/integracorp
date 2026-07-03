<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Filament\UserNavigationAccess;
use Illuminate\Support\Facades\Auth;

trait AuthorizesDepartmentNavigation
{
    public static function canAccess(): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        if (DepartmentNavigationPermissionRegistry::isSuperAdminOnly(static::class)) {
            return UserNavigationAccess::isSuperAdmin($user);
        }

        $module = DepartmentNavigationPermissionRegistry::moduleFor(static::class);

        if ($module === null) {
            return true;
        }

        return UserNavigationAccess::canAccessMenuItem(
            $user,
            $module,
            DepartmentNavigationPermissionRegistry::slugsFor(static::class),
        );
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (DepartmentNavigationPermissionRegistry::isSuperAdminOnly(static::class)) {
            return UserNavigationAccess::isSuperAdmin(Auth::user());
        }

        return static::canAccess();
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }
}
