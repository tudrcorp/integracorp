<?php

declare(strict_types=1);

namespace App\Support\Collections;

use App\Models\Collection;
use App\Models\CollectionAdjustment;
use App\Models\CreditReconciliation;
use App\Models\Sale;
use App\Models\User;
use App\Support\SecurityAudit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Ajuste manual de cuotas de cobranza («Ajustar cuota» y «Marcar como pagadas» en
 * Gestión de Cobranza). Es la única vía para corregir a mano la fecha y el estado de
 * una cuota, en lugar de editar la tabla por SQL.
 *
 * Garantías:
 * - Solo Administración o SUPERADMIN ({@see CollectionAdjustmentAccess}).
 * - Motivo obligatorio; estados permitidos acotados.
 * - Fecha oficial validada; la copia para filtros y la expiración se derivan solas al
 *   guardar ({@see CollectionDueDate::syncColumns()}).
 * - Bloquea revertir un pago que ya tuvo consecuencias (crédito de empresa aliada o
 *   comisión pagada).
 * - Control de concurrencia: si otra persona cambió la cuota mientras se editaba, no
 *   se guarda.
 * - Todo en una transacción con la fila bloqueada, y cada ajuste queda en
 *   `collection_adjustments` (antes → después, motivo, usuario, IP) y en SecurityAudit.
 *
 * Los mensajes de las excepciones están pensados para mostrarse tal cual al usuario.
 */
final class CollectionAdjuster
{
    public const STATUS_PENDING = 'POR PAGAR';

    public const STATUS_PAID = 'PAGADO';

    public const STATUS_CANCELLED = 'ANULADO';

    public const MIN_REASON_LENGTH = 15;

    public const MAX_BULK = 50;

    /** Fechas más lejanas que esto respecto de hoy piden confirmación explícita (posible error de tipeo). */
    private const UNUSUAL_PAST_MONTHS = 24;

    private const UNUSUAL_FUTURE_MONTHS = 18;

    private const TRACKED_FIELDS = ['next_payment_date', 'expiration_date', 'status'];

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Por pagar',
            self::STATUS_PAID => 'Pagado (ajuste de data histórica)',
            self::STATUS_CANCELLED => 'Anulado',
        ];
    }

    public static function isUnusualDate(?CarbonImmutable $date, ?CarbonImmutable $today = null): bool
    {
        if ($date === null) {
            return false;
        }

        $today ??= CarbonImmutable::today();

        return $date->lessThan($today->subMonths(self::UNUSUAL_PAST_MONTHS))
            || $date->greaterThan($today->addMonths(self::UNUSUAL_FUTURE_MONTHS));
    }

    /**
     * Cambios que produciría el ajuste, sin guardar nada (para la vista previa).
     *
     * @return array<string, array{before: ?string, after: ?string}>
     */
    public static function previewChanges(Collection $collection, mixed $date, ?string $status): array
    {
        $parsed = CollectionDueDate::parse($date);
        $before = self::snapshot($collection);
        $after = $before;

        if ($parsed !== null) {
            $after['next_payment_date'] = $parsed->format(CollectionDueDate::DISPLAY_FORMAT);
            $after['expiration_date'] = $parsed->format(CollectionDueDate::DISPLAY_FORMAT);
        }

        if (filled($status)) {
            $after['status'] = Str::upper(trim((string) $status));
        }

        return self::diff($before, $after);
    }

    /**
     * Motivo por el que no se puede pasar la cuota al estado pedido, o null si se puede.
     */
    public static function blockingReason(Collection $collection, string $newStatus): ?string
    {
        $current = Str::upper(trim((string) $collection->status));
        $newStatus = Str::upper(trim($newStatus));

        if ($current !== self::STATUS_PAID || $newStatus === self::STATUS_PAID) {
            return null;
        }

        $invoice = $collection->collection_invoice_number ?: '#'.$collection->getKey();

        if (CreditReconciliation::query()->where('collection_id', $collection->getKey())->exists()) {
            return "La cuota {$invoice} ya generó un movimiento de crédito de empresa aliada; no se puede revertir su pago desde aquí.";
        }

        if (filled($collection->sale_id)
            && Sale::query()->whereKey($collection->sale_id)->where('status_payment_commission', 'COMISION PAGADA')->exists()) {
            return "La comisión de la venta de la cuota {$invoice} ya fue pagada; no se puede revertir su pago desde aquí.";
        }

        return null;
    }

    /**
     * Ajusta fecha y estado de una cuota.
     *
     * @param  array{date: mixed, status: string, reason: string, confirm_unusual_date?: bool|null, expected_updated_at?: string|null}  $data
     *
     * @throws InvalidArgumentException con un mensaje para el usuario
     */
    public static function adjust(Collection $collection, array $data, ?User $actor): CollectionAdjustment
    {
        self::assertCan($actor);
        $reason = self::validReason($data['reason'] ?? null);
        $status = self::validStatus($data['status'] ?? null);
        $date = CollectionDueDate::parse($data['date'] ?? null)
            ?? throw new InvalidArgumentException('Indique una fecha de próximo pago válida (dd/mm/aaaa).');

        if (self::isUnusualDate($date) && ! ($data['confirm_unusual_date'] ?? false)) {
            throw new InvalidArgumentException('La fecha '.$date->format('d/m/Y').' está muy lejos de hoy. Revísela y, si es correcta, marque la confirmación.');
        }

        return DB::transaction(function () use ($collection, $data, $actor, $reason, $status, $date): CollectionAdjustment {
            $locked = self::lock($collection->getKey());
            self::assertNotModifiedSince($locked, $data['expected_updated_at'] ?? null);

            return self::applyLocked($locked, $date, $status, $reason, $actor, CollectionAdjustment::MODE_SINGLE, null);
        });
    }

    /**
     * Marca varias cuotas por pagar como pagadas (data histórica), todo o nada.
     *
     * @param  iterable<Collection>  $collections
     *
     * @throws InvalidArgumentException con un mensaje para el usuario
     */
    public static function markManyAsPaid(iterable $collections, mixed $reason, ?User $actor): int
    {
        self::assertCan($actor);
        $reason = self::validReason($reason);

        $ids = [];

        foreach ($collections as $collection) {
            $ids[] = (int) $collection->getKey();
        }

        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            throw new InvalidArgumentException('Seleccione al menos una cuota.');
        }

        if (count($ids) > self::MAX_BULK) {
            throw new InvalidArgumentException('Puede marcar hasta '.self::MAX_BULK.' cuotas por vez. Seleccionó '.count($ids).'.');
        }

        $batch = (string) Str::uuid();

        return DB::transaction(function () use ($ids, $reason, $actor, $batch): int {
            $locked = Collection::query()->whereKey($ids)->lockForUpdate()->orderBy('id')->get();

            if ($locked->count() !== count($ids)) {
                throw new InvalidArgumentException('Alguna de las cuotas seleccionadas ya no existe. Recargue la tabla e intente de nuevo.');
            }

            $problems = [];

            foreach ($locked as $collection) {
                $invoice = $collection->collection_invoice_number ?: '#'.$collection->getKey();

                if (Str::upper(trim((string) $collection->status)) !== self::STATUS_PENDING) {
                    $problems[] = "{$invoice} no está por pagar";
                } elseif (CollectionDueDate::of($collection) === null) {
                    $problems[] = "{$invoice} no tiene una fecha de próximo pago válida";
                }
            }

            if ($problems !== []) {
                throw new InvalidArgumentException('No se marcó ninguna cuota: '.implode('; ', $problems).'. Quítelas de la selección.');
            }

            foreach ($locked as $collection) {
                self::applyLocked($collection, CollectionDueDate::of($collection), self::STATUS_PAID, $reason, $actor, CollectionAdjustment::MODE_BULK, $batch);
            }

            return $locked->count();
        });
    }

    private static function applyLocked(
        Collection $locked,
        CarbonImmutable $date,
        string $status,
        string $reason,
        ?User $actor,
        string $mode,
        ?string $batch,
    ): CollectionAdjustment {
        if ($blocked = self::blockingReason($locked, $status)) {
            throw new InvalidArgumentException($blocked);
        }

        $before = self::snapshot($locked);

        $locked->next_payment_date = $date->format(CollectionDueDate::DISPLAY_FORMAT);
        $locked->status = $status;
        $locked->save();

        $changes = self::diff($before, self::snapshot($locked));

        if ($changes === []) {
            throw new InvalidArgumentException('No hay cambios: la cuota ya tiene esa fecha y ese estado.');
        }

        $adjustment = CollectionAdjustment::query()->create([
            'collection_id' => $locked->getKey(),
            'affiliation_code' => $locked->affiliation_code,
            'collection_invoice_number' => $locked->collection_invoice_number,
            'batch_uuid' => $batch,
            'mode' => $mode,
            'changes' => $changes,
            'reason' => $reason,
            'performed_by_id' => $actor?->getKey(),
            'performed_by_name' => $actor?->name,
            'ip' => request()?->ip(),
            'user_agent' => Str::limit((string) request()?->userAgent(), 1000, ''),
        ]);

        SecurityAudit::log('AUDIT_ADMIN_COLLECTION_ADJUSTED', 'administration.collections.adjust', [
            'panel' => 'administration',
            'collection_id' => $locked->getKey(),
            'affiliation_code' => $locked->affiliation_code,
            'invoice' => $locked->collection_invoice_number,
            'mode' => $mode,
            'batch_uuid' => $batch,
            'changes' => $changes,
            'reason' => $reason,
            'adjustment_id' => $adjustment->getKey(),
        ], $actor);

        return $adjustment;
    }

    private static function lock(mixed $id): Collection
    {
        $locked = Collection::query()->whereKey($id)->lockForUpdate()->first();

        if (! $locked instanceof Collection) {
            throw new InvalidArgumentException('La cuota ya no existe. Recargue la tabla.');
        }

        return $locked;
    }

    private static function assertNotModifiedSince(Collection $locked, ?string $expectedUpdatedAt): void
    {
        if ($expectedUpdatedAt === null) {
            return;
        }

        if ((string) $locked->getRawOriginal('updated_at') !== $expectedUpdatedAt) {
            throw new InvalidArgumentException('Otra persona modificó esta cuota mientras usted la editaba. Cierre la ventana y vuelva a abrirla para ver los datos actuales.');
        }
    }

    private static function assertCan(?User $actor): void
    {
        if (! CollectionAdjustmentAccess::userCan($actor)) {
            throw new InvalidArgumentException('No tiene permiso para ajustar cuotas. Solo el departamento de Administración puede hacerlo.');
        }
    }

    private static function validReason(mixed $reason): string
    {
        $reason = trim((string) ($reason ?? ''));

        if (mb_strlen($reason) < self::MIN_REASON_LENGTH) {
            throw new InvalidArgumentException('Explique el motivo del ajuste (mínimo '.self::MIN_REASON_LENGTH.' caracteres).');
        }

        return $reason;
    }

    private static function validStatus(mixed $status): string
    {
        $status = Str::upper(trim((string) ($status ?? '')));

        if (! array_key_exists($status, self::statusOptions())) {
            throw new InvalidArgumentException('Elija un estado válido: por pagar, pagado o anulado.');
        }

        return $status;
    }

    /**
     * @return array<string, ?string>
     */
    private static function snapshot(Collection $collection): array
    {
        $snapshot = [];

        foreach (self::TRACKED_FIELDS as $field) {
            $value = $collection->getAttribute($field);
            $snapshot[$field] = $value === null ? null : (string) $value;
        }

        return $snapshot;
    }

    /**
     * @param  array<string, ?string>  $before
     * @param  array<string, ?string>  $after
     * @return array<string, array{before: ?string, after: ?string}>
     */
    private static function diff(array $before, array $after): array
    {
        $changes = [];

        foreach (self::TRACKED_FIELDS as $field) {
            if (($before[$field] ?? null) !== ($after[$field] ?? null)) {
                $changes[$field] = ['before' => $before[$field] ?? null, 'after' => $after[$field] ?? null];
            }
        }

        return $changes;
    }
}
