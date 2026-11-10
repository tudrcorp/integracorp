<?php

declare(strict_types=1);

namespace App\Support\Sales;

use App\Enums\DocumentEmailType;
use App\Filament\Administration\Resources\Sales\SaleResource;
use App\Models\User;
use App\Support\DocumentEmails\DocumentEmailTemplateAccess;
use Filament\Facades\Filament;
use Throwable;

/**
 * Quién usa y quién gestiona los copys del recibo de pago.
 *
 * - Elegir un copy y enviar el recibo: quien entra a Ventas en Administración.
 * - Crear, editar, activar o desactivar copys: el permiso de acción
 *   `gestionar-copys-recibo-de-pago`, asignable en el editor de permisos.
 */
final class SaleReceiptEmailAccess
{
    public static function canSend(?User $user): bool
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
        return (clone $user)->canAccessPanel($panel) && SaleResource::canAccess();
    }

    public static function canManageTemplates(): bool
    {
        return DocumentEmailTemplateAccess::canManage(DocumentEmailType::ReceiptPayment);
    }
}
