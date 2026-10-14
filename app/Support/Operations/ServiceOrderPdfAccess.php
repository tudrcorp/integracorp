<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationServiceOrder;
use App\Models\User;
use App\Support\Filament\Operations\OperationsSupplierScope;
use Filament\Facades\Filament;
use Throwable;

/**
 * Quién puede ver o descargar el PDF de una orden de servicio.
 *
 * Antes bastaba con iniciar sesión: cualquier usuario (un agente, o el analista
 * de otro proveedor) abría cualquier orden cambiando el id en la URL. Ahora
 * hace falta entrar a Operaciones o a Telemedicina, que son los paneles que
 * enlazan estos PDF, y que la orden esté dentro del alcance que ya aplica la
 * lista de órdenes ({@see OperationsSupplierScope::applyServiceOrderListScope()}):
 * un proveedor solo ve las suyas y ATENMEDI solo las que gestiona.
 */
final class ServiceOrderPdfAccess
{
    /**
     * @var list<string>
     */
    private const PANELS = ['operations', 'telemedicina'];

    public static function canView(?User $user, OperationServiceOrder $order): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if (! self::canEnterAnyPanel($user)) {
            return false;
        }

        return OperationsSupplierScope::applyServiceOrderListScope(OperationServiceOrder::query())
            ->whereKey($order->getKey())
            ->exists();
    }

    /**
     * Se consulta sobre una copia: varias ramas de `User::canAccessPanel()`
     * asignan `status` en lugar de compararlo, y no debe tocar al usuario de
     * la sesión.
     */
    private static function canEnterAnyPanel(User $user): bool
    {
        foreach (self::PANELS as $panelId) {
            try {
                if ((clone $user)->canAccessPanel(Filament::getPanel($panelId))) {
                    return true;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return false;
    }
}
