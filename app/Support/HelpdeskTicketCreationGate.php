<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

final class HelpdeskTicketCreationGate
{
    /**
     * Cualquier usuario autenticado de Integracorp puede crear tickets.
     */
    public static function allowsCreation(?Authenticatable $user = null): HelpdeskBusinessTicketCreationVerdict
    {
        $user ??= auth()->user();

        if (! $user instanceof User) {
            return HelpdeskBusinessTicketCreationVerdict::denied(
                'Debe iniciar sesión para crear un ticket.',
                denialReason: HelpdeskBusinessTicketCreationDenialReason::UNAUTHENTICATED,
            );
        }

        return HelpdeskBusinessTicketCreationVerdict::allowed(
            message: 'Usuario autenticado (sin restricción de creación).',
        );
    }
}
