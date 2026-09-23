<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Quién puede abrir el monitor en vivo: solo los correos de la lista blanca
 * (`live-presence.allowed_emails`). No depende de departamentos ni permisos
 * asignables: nadie puede concederlo desde la interfaz.
 */
final class LivePresenceAccess
{
    public static function allows(?Authenticatable $user): bool
    {
        if ($user === null) {
            return false;
        }

        $email = mb_strtolower(trim((string) ($user->email ?? '')));
        $status = (string) ($user->status ?? 'ACTIVO');

        return $email !== ''
            && $status === 'ACTIVO'
            && in_array($email, (array) config('live-presence.allowed_emails', []), true);
    }
}
