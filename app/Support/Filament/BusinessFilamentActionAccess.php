<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Illuminate\Support\Facades\Auth;

final class BusinessFilamentActionAccess
{
    public static function userCan(string $actionSlug): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        return UserNavigationAccess::canPerformModuleAction($user, 'NEGOCIOS', $actionSlug);
    }
}
