<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Auth;

final class PlanGeneratorPdfAccess
{
    public static function userCanAccess(): bool
    {
        $departments = (array) (Auth::user()?->departament ?? []);

        return in_array('SUPERADMIN', $departments, true);
    }
}
