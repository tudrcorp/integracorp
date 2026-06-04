<?php

declare(strict_types=1);

namespace App\Support\Filament\Operations;

use Illuminate\Support\Facades\Auth;

final class OperationsSuperAdmin
{
    public static function check(): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        $departments = $user->departament ?? [];

        if (! is_array($departments)) {
            $departments = filled($departments) ? [(string) $departments] : [];
        }

        return in_array('SUPERADMIN', $departments, true);
    }
}
