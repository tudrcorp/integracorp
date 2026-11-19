<?php

declare(strict_types=1);

namespace App\Support\Affiliations;

use App\Models\PaidMembership;
use App\Models\PaidMembershipCorporate;
use RuntimeException;

/**
 * Última barrera antes de aprobar un pago: solo se aprueba lo que está
 * PENDIENTE en la base en este instante. Bloquea la fila, así que debe correr
 * dentro de la transacción de la aprobación; corta pagos anulados, ya
 * aprobados (doble clic, dos analistas a la vez) o eliminados.
 */
final class PaymentApprovalGuard
{
    /**
     * Si un botón «Aprobar» debe mostrarse: solo para pagos PENDIENTES.
     */
    public static function isApprovableStatus(?string $status): bool
    {
        return strtoupper(trim((string) $status)) === PendingPaymentDeletion::PENDING_STATUS;
    }

    /**
     * @throws RuntimeException con un mensaje para el analista
     */
    public static function assertApprovable(PaidMembership|PaidMembershipCorporate $payment): void
    {
        $status = $payment::query()->whereKey($payment->getKey())->lockForUpdate()->value('status');

        if ($status === null && ! $payment::query()->whereKey($payment->getKey())->exists()) {
            throw new RuntimeException('Este pago ya no existe. Recargue la página.');
        }

        $status = strtoupper(trim((string) $status));

        if ($status === PendingPaymentDeletion::PENDING_STATUS) {
            return;
        }

        throw new RuntimeException(match ($status) {
            PendingPaymentAnnulment::ANNULLED_STATUS => 'El pago está ANULADO: no se puede aprobar. El agente debe cargar el pago correcto.',
            'APROBADO', 'PAGADO' => 'Este pago ya fue aprobado (quizá por otro analista hace un momento). Recargue la página.',
            default => 'El pago está '.($status !== '' ? $status : 'sin estatus').': solo se aprueban pagos PENDIENTES.',
        });
    }
}
