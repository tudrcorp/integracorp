<?php

declare(strict_types=1);

namespace App\Support\Collections;

use App\Filament\Administration\Resources\Collections\CollectionResource;
use App\Models\User;
use Filament\Facades\Filament;
use Throwable;

/**
 * Quién puede regenerar y enviar avisos de cobro por las rutas HTTP: la misma regla que abre
 * «Gestión de Cobranza» en Administración (acceso al panel y al módulo). Antes bastaba con tener sesión.
 */
final class CollectionNoticeAccess
{
    public static function allows(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        try {
            $panel = Filament::getPanel('administration');
        } catch (Throwable) {
            return false;
        }

        // canAccessPanel() asigna `status` en memoria (comportamiento histórico): se evalúa sobre una copia.
        return (clone $user)->canAccessPanel($panel) && CollectionResource::canAccess();
    }
}
