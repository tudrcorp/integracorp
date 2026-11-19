<?php

declare(strict_types=1);

namespace App\Support\Affiliations;

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\PaidMembership;
use App\Models\PaidMembershipCorporate;
use App\Models\PaymentEditLog;
use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Anula un pago PENDIENTE de «Historial de pagos» (individual o corporativo).
 *
 * La fila no se borra: queda con estatus ANULADO, el comprobante en su sitio y
 * quién, cuándo y por qué en la propia fila, en «Ediciones» del pago y en la
 * bitácora de seguridad. Un pago anulado no se aprueba, no se edita y no ocupa
 * cupo de cuota: el agente puede cargar el pago correcto.
 *
 * Mismas condiciones que antes tenía eliminar: pendiente y sin venta, cuotas,
 * comisiones ni conciliaciones ({@see PendingPaymentDeletion::blockers()}).
 */
final class PendingPaymentAnnulment
{
    public const ANNULLED_STATUS = 'ANULADO';

    public const REASON_MIN_LENGTH = 15;

    public const REASON_MAX_LENGTH = 1000;

    /**
     * Motivos por los que el pago no se puede anular. Vacío = se puede.
     *
     * @return list<string>
     */
    public static function blockers(PaidMembership|PaidMembershipCorporate $payment): array
    {
        $status = strtoupper(trim((string) $payment->status));

        if ($status === self::ANNULLED_STATUS) {
            return ['El pago ya está ANULADO.'];
        }

        if ($status !== PendingPaymentDeletion::PENDING_STATUS) {
            return ['El pago está '.($status !== '' ? $status : 'sin estatus').': solo se anulan pagos PENDIENTES.'];
        }

        return PendingPaymentDeletion::blockers($payment);
    }

    public static function isAnnulled(?string $status): bool
    {
        return strtoupper(trim((string) $status)) === self::ANNULLED_STATUS;
    }

    /**
     * @return array{trace_id: int}
     *
     * @throws InvalidArgumentException si no se puede anular o el motivo no es válido
     */
    public static function annul(PaidMembership|PaidMembershipCorporate $payment, string $reason, User $actor, ?string $panel = null): array
    {
        $reason = trim((string) preg_replace('/\s+/u', ' ', $reason));

        if (mb_strlen($reason) < self::REASON_MIN_LENGTH) {
            throw new InvalidArgumentException('El motivo debe explicar por qué se anula, en al menos '.self::REASON_MIN_LENGTH.' caracteres.');
        }

        if (mb_strlen($reason) > self::REASON_MAX_LENGTH) {
            throw new InvalidArgumentException('El motivo no puede pasar de '.self::REASON_MAX_LENGTH.' caracteres.');
        }

        if (! self::schemaIsReady($payment)) {
            throw new InvalidArgumentException('Falta aplicar la actualización de base de datos de este módulo. Avise a soporte.');
        }

        $class = $payment::class;

        return DB::transaction(static function () use ($class, $payment, $reason, $actor, $panel): array {
            /** @var PaidMembership|PaidMembershipCorporate|null $locked */
            $locked = $class::query()->whereKey($payment->getKey())->lockForUpdate()->first();

            if ($locked === null) {
                throw new InvalidArgumentException('Este pago ya no existe. Recargue la página.');
            }

            $blockers = self::blockers($locked);
            if ($blockers !== []) {
                throw new InvalidArgumentException(implode(' ', $blockers));
            }

            $snapshot = $locked->getAttributes();
            $previousStatus = (string) $locked->status;
            $owner = self::owner($locked);
            $type = $locked instanceof PaidMembershipCorporate ? PendingPaymentDeletion::TYPE_CORPORATE : PendingPaymentDeletion::TYPE_INDIVIDUAL;

            $locked->forceFill([
                'status' => self::ANNULLED_STATUS,
                'annulled_at' => now(),
                'annulled_by_user_id' => $actor->getKey(),
                'annulled_by_name' => (string) $actor->name,
                'annulment_reason' => $reason,
            ])->save();

            $trace = PaymentEditLog::query()->create([
                'payment_type' => $type,
                'payment_id' => (int) $locked->getKey(),
                'affiliation_id' => $owner?->getKey(),
                'affiliation_code' => $owner?->getAttribute('code'),
                'changes' => ['status' => ['old' => $previousStatus, 'new' => self::ANNULLED_STATUS]],
                'snapshot' => $snapshot,
                'note' => $reason,
                'panel' => $panel,
                'actor_id' => $actor->getKey(),
                'actor_name' => (string) $actor->name,
                'ip' => request()?->ip(),
            ]);

            SecurityAudit::log('AUDIT_AFFILIATION_PENDING_PAYMENT_ANNULLED', 'affiliations.payments.annul-pending', [
                'payment_type' => $type,
                'payment_id' => (int) $locked->getKey(),
                'affiliation_code' => $owner?->getAttribute('code'),
                'trace_id' => (int) $trace->getKey(),
                'panel' => $panel,
                'reason' => $reason,
            ], $actor);

            return ['trace_id' => (int) $trace->getKey()];
        });
    }

    private static function schemaIsReady(PaidMembership|PaidMembershipCorporate $payment): bool
    {
        return Schema::hasTable('payment_edit_logs')
            && Schema::hasColumns($payment->getTable(), ['annulled_at', 'annulled_by_user_id', 'annulled_by_name', 'annulment_reason']);
    }

    private static function owner(PaidMembership|PaidMembershipCorporate $payment): Affiliation|AffiliationCorporate|null
    {
        return $payment instanceof PaidMembershipCorporate
            ? $payment->affiliation_corporate
            : $payment->affiliation;
    }
}
