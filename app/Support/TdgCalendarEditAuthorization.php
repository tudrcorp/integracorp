<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use App\Support\Filament\UserNavigationAccess;
use Illuminate\Support\Str;

final class TdgCalendarEditAuthorization
{
    /**
     * Correos de las únicas personas que pueden editar Guardia Oficinas.
     *
     * @var list<string>
     */
    private const OFFICE_EMAILS = [
        'areyes@tudrencasa.com',
        'kdominguez@tudrencasa.com',
        'kdomingues@tudrencasa.com',
    ];

    /**
     * Correo de la única persona que puede editar Guardias Operaciones.
     *
     * @var list<string>
     */
    private const OPERATIONS_EMAILS = [
        'bacosta@tudrencasa.com',
    ];

    public static function allows(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if (UserNavigationAccess::isSuperAdmin($user)) {
            return true;
        }

        $email = self::normalizedEmail($user);

        if ($email !== '' && in_array($email, self::OFFICE_EMAILS, true)) {
            return true;
        }

        return self::nameMatchesAndreinaReyes((string) $user->name)
            || self::nameMatchesKattyDomingues((string) $user->name);
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

    private static function nameMatchesAndreinaReyes(string $name): bool
    {
        $tokens = self::nameTokens($name);

        return in_array('andreina', $tokens, true) && in_array('reyes', $tokens, true);
    }

    private static function nameMatchesKattyDomingues(string $name): bool
    {
        $tokens = self::nameTokens($name);
        $givenNames = array_intersect($tokens, ['katy', 'katty', 'katia']);
        $familyNames = array_intersect($tokens, ['dominguez', 'domingues']);

        return $givenNames !== [] && $familyNames !== [];
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
