<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;

final class HelpdeskUserAccess
{
    public static function hasSystemsDepartment(?Authenticatable $user = null): bool
    {
        $user ??= auth()->user();

        if ($user === null) {
            return false;
        }

        $departments = $user->departament ?? [];

        if (! is_array($departments)) {
            $departments = [(string) $departments];
        }

        foreach ($departments as $department) {
            if (! is_string($department)) {
                continue;
            }

            if (str_contains(mb_strtoupper($department), 'SISTEMAS')) {
                return true;
            }
        }

        return false;
    }
}
