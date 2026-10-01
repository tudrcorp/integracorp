<?php

declare(strict_types=1);

namespace App\Support\Collections;

use App\Models\Collection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Repara cuotas cuya fecha oficial (`next_payment_date`) y su copia para filtros
 * (`filter_next_payment_date`) quedaron distintas, o cuya fecha oficial está escrita
 * con guiones.
 *
 * Clasifica antes de tocar nada:
 * - `sync`: la fecha de expiración cuadra con la oficial (se graba con ella, hasta
 *   30 días después) → la copia se recalcula desde la oficial.
 * - `format`: misma fecha, solo cambia el formato (`dd-mm-aaaa` → `dd/mm/aaaa`).
 * - `review`: no hay evidencia suficiente (oficial ilegible, o la expiración cuadra
 *   con la copia y no con la oficial). Se lista y **no se toca**.
 */
final class CollectionDueDateRepair
{
    public const ACTION_SYNC = 'sync';

    public const ACTION_FORMAT = 'format';

    public const ACTION_REVIEW = 'review';

    private const EXPIRATION_WINDOW_DAYS = 30;

    /**
     * @return array{action: string, reason: string, official: ?string, filter: ?string, expiration: ?string}|null
     *                                                                                                             null si la cuota ya está bien
     */
    public static function classify(Collection $collection): ?array
    {
        $rawOfficial = trim((string) ($collection->next_payment_date ?? ''));
        $official = CollectionDueDate::parse($rawOfficial);
        $filter = CollectionDueDate::parse($collection->filter_next_payment_date);
        $expiration = CollectionDueDate::parse($collection->expiration_date);

        $base = [
            'official' => $official?->format(CollectionDueDate::DISPLAY_FORMAT),
            'filter' => $filter?->format(CollectionDueDate::STORAGE_FORMAT),
            'expiration' => $expiration?->format(CollectionDueDate::DISPLAY_FORMAT),
        ];

        if ($official === null) {
            if ($rawOfficial === '' && $filter === null) {
                return null;
            }

            return ['action' => self::ACTION_REVIEW, 'reason' => 'La fecha de próximo pago está vacía o no se puede leer.'] + $base;
        }

        $sameDay = $filter !== null && $filter->equalTo($official);
        $canonical = $rawOfficial === $official->format(CollectionDueDate::DISPLAY_FORMAT);

        if ($sameDay && $canonical) {
            return null;
        }

        if ($sameDay) {
            return ['action' => self::ACTION_FORMAT, 'reason' => 'Misma fecha escrita con otro formato.'] + $base;
        }

        if (self::expirationMatches($expiration, $official)) {
            return ['action' => self::ACTION_SYNC, 'reason' => 'La expiración cuadra con la fecha de próximo pago.'] + $base;
        }

        if ($filter === null) {
            return ['action' => self::ACTION_SYNC, 'reason' => 'No había fecha de filtro.'] + $base;
        }

        return [
            'action' => self::ACTION_REVIEW,
            'reason' => self::expirationMatches($expiration, $filter)
                ? 'La expiración cuadra con la fecha de filtro y no con la de próximo pago.'
                : 'La expiración no cuadra con ninguna de las dos fechas.',
        ] + $base;
    }

    /**
     * @return list<array{id: int, affiliation_code: ?string, status: ?string, action: string, reason: string, official: ?string, filter: ?string, expiration: ?string, raw_official: ?string, raw_filter: ?string}>
     */
    public static function plan(): array
    {
        $rows = [];

        Collection::query()
            ->select(['id', 'affiliation_code', 'status', 'next_payment_date', 'filter_next_payment_date', 'expiration_date'])
            ->orderBy('id')
            ->chunkById(500, function ($collections) use (&$rows): void {
                foreach ($collections as $collection) {
                    $classification = self::classify($collection);

                    if ($classification === null) {
                        continue;
                    }

                    $rows[] = [
                        'id' => (int) $collection->id,
                        'affiliation_code' => $collection->affiliation_code,
                        'status' => $collection->status,
                        'raw_official' => $collection->next_payment_date,
                        'raw_filter' => $collection->getRawOriginal('filter_next_payment_date'),
                    ] + $classification;
                }
            });

        return $rows;
    }

    /**
     * Aplica solo `sync` y `format`, en una transacción. Guarda por Eloquent para que
     * el evento `saving` derive la copia desde la oficial ({@see CollectionDueDate::syncColumns()}).
     *
     * @param  list<array{id: int, action: string}>  $plan
     * @return array{sync: int, format: int, skipped: int}
     */
    public static function apply(array $plan): array
    {
        $result = ['sync' => 0, 'format' => 0, 'skipped' => 0];

        DB::transaction(function () use ($plan, &$result): void {
            foreach ($plan as $row) {
                if (! in_array($row['action'], [self::ACTION_SYNC, self::ACTION_FORMAT], true)) {
                    $result['skipped']++;

                    continue;
                }

                $collection = Collection::query()->lockForUpdate()->find($row['id']);

                /** Se reclasifica con el dato bloqueado: si cambió desde la vista previa, no se toca. */
                $current = $collection instanceof Collection ? self::classify($collection) : null;

                if ($current === null || $current['action'] !== $row['action']) {
                    $result['skipped']++;

                    continue;
                }

                $collection->timestamps = false;
                $collection->save();
                $result[$row['action']]++;
            }
        });

        return $result;
    }

    private static function expirationMatches(?CarbonImmutable $expiration, CarbonImmutable $date): bool
    {
        return $expiration !== null
            && $expiration->greaterThanOrEqualTo($date)
            && $expiration->lessThanOrEqualTo($date->addDays(self::EXPIRATION_WINDOW_DAYS));
    }
}
