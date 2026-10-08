<?php

declare(strict_types=1);

namespace App\Support\Renovations;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cuántas renovaciones caen en cada tramo de fecha de renovación (`date_renewal`):
 * vencidas, de hoy a 15 días, de 16 a 30 y de 31 a 45. Los tramos **no se
 * solapan**: una renovación cuenta en un solo tramo. Solo cantidades, sin montos.
 *
 * Se cuenta por `date_renewal` y no por `remaining_days` porque esa columna la
 * recalcula un job diario y puede venir desfasada; la fecha no.
 *
 * El conteo de las tarjetas y el filtro «Renueva en» de la tabla salen de los
 * mismos rangos (`range()`), así que al hacer clic en una tarjeta la tabla muestra
 * exactamente las filas que la tarjeta contó.
 */
final class UpcomingRenovationCounts
{
    /** Nombre del filtro de la tabla que acota por tramo (campo `tramo`). */
    public const FILTER = 'renueva_en';

    public const OVERDUE = 'vencidas';

    public const DAYS_15 = 'd15';

    public const DAYS_30 = 'd30';

    public const DAYS_45 = 'd45';

    /** @var list<string> */
    public const BUCKETS = [self::OVERDUE, self::DAYS_15, self::DAYS_30, self::DAYS_45];

    /**
     * Rango de días desde hoy de cada tramo. `null` = sin límite por ese lado.
     *
     * @return array{0: int|null, 1: int|null}
     */
    public static function range(string $bucket): array
    {
        return match ($bucket) {
            self::OVERDUE => [null, -1],
            self::DAYS_15 => [0, 15],
            self::DAYS_30 => [16, 30],
            self::DAYS_45 => [31, 45],
            default => throw new \InvalidArgumentException("Tramo de renovación desconocido: {$bucket}"),
        };
    }

    public static function label(string $bucket): string
    {
        return match ($bucket) {
            self::OVERDUE => 'Vencidas',
            self::DAYS_15 => 'Próximos 15 días',
            self::DAYS_30 => 'De 16 a 30 días',
            self::DAYS_45 => 'De 31 a 45 días',
            default => $bucket,
        };
    }

    /**
     * @return array<string, string> Tramo => etiqueta, para el filtro de la tabla.
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::BUCKETS as $bucket) {
            $options[$bucket] = self::label($bucket);
        }

        return $options;
    }

    /**
     * Fechas de inicio y fin del tramo (`null` = abierto). Sirve para mostrar en la
     * tarjeta qué días abarca.
     *
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    public static function dates(string $bucket, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        [$from, $to] = self::range($bucket);

        return [
            $from === null ? null : $today->addDays($from),
            $to === null ? null : $today->addDays($to),
        ];
    }

    /**
     * Acota la consulta al tramo. Lo usa el filtro «Renueva en».
     */
    public static function applyBucket(Builder $query, string $bucket, ?CarbonImmutable $today = null): Builder
    {
        [$from, $to] = self::dates($bucket, $today);
        $column = $query->getModel()->getTable().'.date_renewal';

        return $query
            ->when($from !== null, fn (Builder $q): Builder => $q->whereDate($column, '>=', $from->toDateString()))
            ->when($to !== null, fn (Builder $q): Builder => $q->whereDate($column, '<=', $to->toDateString()));
    }

    /**
     * Recibe la consulta filtrada de la tabla (pestaña, filtros y búsqueda) y la
     * resuelve en una sola consulta agregada, sin cargar filas.
     *
     * @return array<string, int> Tramo => cantidad.
     */
    public static function forQuery(Builder $query, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $column = $query->getModel()->getTable().'.date_renewal';

        $query = (clone $query)->reorder();
        $query->setEagerLoads([]);
        $query->getQuery()->columns = null;
        $query->getQuery()->limit = null;
        $query->getQuery()->offset = null;

        foreach (self::BUCKETS as $bucket) {
            [$from, $to] = self::dates($bucket, $today);

            if ($from === null) {
                $query->selectRaw("SUM(CASE WHEN {$column} <= ? THEN 1 ELSE 0 END) as bucket_{$bucket}", [$to->toDateString()]);

                continue;
            }

            $query->selectRaw(
                "SUM(CASE WHEN {$column} BETWEEN ? AND ? THEN 1 ELSE 0 END) as bucket_{$bucket}",
                [$from->toDateString(), $to->toDateString()],
            );
        }

        $row = $query->toBase()->first();

        $counts = [];

        foreach (self::BUCKETS as $bucket) {
            $counts[$bucket] = (int) ($row->{"bucket_{$bucket}"} ?? 0);
        }

        return $counts;
    }
}
