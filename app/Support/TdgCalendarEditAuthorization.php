<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\UserNavigationAccess;
use Illuminate\Support\Str;

final class TdgCalendarEditAuthorization
{
    /**
     * Correo de la única persona que puede editar Guardias Operaciones.
     *
     * @var list<string>
     */
    private const OPERATIONS_EMAILS = [
        'bacosta@tudrencasa.com',
    ];

    /**
     * Crear, actualizar y eliminar Guardia Oficinas exige el permiso del módulo
     * del panel actual. Un superadministrador puede hacerlo siempre.
     */
    public static function allows(?User $user, ?string $module = null): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if (UserNavigationAccess::isSuperAdmin($user)) {
            return true;
        }

        if ($module === null || trim($module) === '') {
            return false;
        }

        return UserNavigationAccess::canPerformModuleAction(
            $user,
            $module,
            BusinessFilamentActionPermissionRegistry::EDIT_OFFICE_GUARDS,
        );
    }

    public static function allowsOperationsGuards(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if (UserNavigationAccess::isSuperAdmin($user)) {
            return true;
        }

        $email = self::normalizedEmail($user);

        if ($email !== '' && in_array($email, self::OPERATIONS_EMAILS, true)) {
            return true;
        }

        return self::nameMatchesBeckyAcosta((string) $user->name);
    }

    private static function normalizedEmail(User $user): string
    {
        return Str::lower(trim((string) $user->email));
    }

    private static function nameMatchesBeckyAcosta(string $name): bool
    {
        $tokens = self::nameTokens($name);

        return in_array('becky', $tokens, true) && in_array('acosta', $tokens, true);
    }

    /**
     * @return list<string>
     */
    private static function nameTokens(string $name): array
    {
        $normalized = Str::lower(Str::ascii($name));
        $parts = preg_split('/[^a-z]+/', $normalized) ?: [];

        return array_values(array_filter(
            $parts,
            fn (string $part): bool => $part !== '',
        ));
    }
}
