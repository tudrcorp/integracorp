<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;

final class HelpdeskUserAccess
{
    public static function hasSuperAdminDepartment(?Authenticatable $user = null): bool
    {
        return self::departmentContains($user, 'SUPERADMIN');
    }

    public static function hasSystemsDepartment(?Authenticatable $user = null): bool
    {
        return self::departmentContains($user, 'SISTEMAS');
    }

    public static function departmentContains(?Authenticatable $user, string $needle): bool
    {
        $user ??= auth()->user();

        if ($user === null) {
            return false;
        }

        $departments = $user->departament ?? [];

        if (! is_array($departments)) {
            $departments = [(string) $departments];
        }

        $normalizedNeedle = self::normalizeDepartmentToken($needle);

        if ($normalizedNeedle === '') {
            return false;
        }

        foreach ($departments as $department) {
            if (! is_string($department) && ! is_numeric($department)) {
                continue;
            }

            $normalized = self::normalizeDepartmentToken((string) $department);

            if ($normalized !== '' && str_contains($normalized, $normalizedNeedle)) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeDepartmentToken(string $value): string
    {
        return strtoupper(str_replace([' ', '-', '_'], '', $value));
    }
}
