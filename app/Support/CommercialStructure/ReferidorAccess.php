<?php

declare(strict_types=1);

namespace App\Support\CommercialStructure;

use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;

final class ReferidorAccess
{
    public static function userCanManage(): bool
    {
        return BusinessFilamentActionAccess::userCan(
            BusinessFilamentActionPermissionRegistry::MANAGE_REFERIDOR,
        );
    }
}
