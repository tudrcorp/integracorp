<?php

declare(strict_types=1);

namespace App\Support\QrCenter;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Quién administra el centro de QR. Lo que se cambia aquí sale impreso en las
 * tarjetas de toda la cartera, así que solo SUPERADMIN. La misma regla se aplica
 * en la página, en cada acción de Livewire y en las rutas: no basta con el menú.
 */
final class QrCenterAccess
{
    public static function allows(?User $user = null): bool
    {
        $user ??= Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        return in_array('SUPERADMIN', (array) ($user->departament ?? []), true);
    }
}
