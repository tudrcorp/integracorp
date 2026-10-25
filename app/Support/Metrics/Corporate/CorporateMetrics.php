<?php

declare(strict_types=1);

namespace App\Support\Metrics\Corporate;

use App\Support\Operations\Dashboard\OperationsDashboardPeriod as Period;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Piezas comunes del panel corporativo de Métricas: caché, rangos, series en el
 * tiempo y formatos.
 *
 * El panel es solo de SUPERADMIN + METRICAS y ve la empresa completa, así que la
 * caché es compartida (no depende del usuario). Cada cifra se calcula agregada
 * en la base y se guarda unos minutos: la información de toda la empresa carga
 * rápido aunque crezca.
 */
final class CorporateMetrics
{
    public const CACHE_SECONDS = 300;

    public const VERSION_KEY = 'corporate-metrics:version';

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function remember(string $metric, ?Period $period, Closure $callback): mixed
    {
        $version = (int) Cache::get(self::VERSION_KEY, 0);

        return Cache::remember(
            'corporate-metrics:'.$version.':'.$metric.':'.($period?->cacheKey() ?? 'snapshot'),
            self::CACHE_SECONDS,
            $callback,
        );
    }

    /**
     * Invalida toda la caché del panel (tests, o tras una corrección de datos).
     */
    public static function flush(): void
    {
        /** Con la caché `database`, increment() sobre una clave que no existe no la crea. */
        Cache::forever(self::VERSION_KEY, (int) Cache::get(self::VERSION_KEY, 0) + 1);
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function range(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [$from->startOfDay()->toDateTimeString(), $to->endOfDay()->toDateTimeString()];
    }

    /**
     * Serie diaria del rango a partir de una consulta: `$dateSql` es la fecha de
     * cada fila y `$valueSql` lo que se suma por día (COUNT(*) por defecto).
     *
     * @return list<float>
     */
    public static function daily(Builder $query, string $dateSql, CarbonImmutable $from, CarbonImmutable $to, string $valueSql = 'COUNT(*)'): array
    {
        $rows = (clone $query)
            ->selectRaw('DATE('.$dateSql.') as day, '.$valueSql.' as total')
            ->groupByRaw('DATE('.$dateSql.')')
            ->pluck('total', 'day');

        $series = [];

        for ($day = $from->startOfDay(); $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            $series[] = round((float) ($rows[$day->toDateString()] ?? 0), 2);
        }

        return $series;
    }

    /**
     * Agrupa una serie diaria en días, semanas o meses según el largo del período.
     *
     * @param  list<float>  $daily
     * @return array{labels: list<string>, values: list<float>}
     */
    public static function bucket(array $daily, CarbonImmutable $from, string $granularity): array
    {
        $labels = [];
        $values = [];

        foreach ($daily as $index => $value) {
            $date = $from->addDays($index);
            [$key, $label] = match ($granularity) {
                Period::GRANULARITY_WEEK => [$date->startOfWeek()->toDateString(), 'Sem. '.$date->startOfWeek()->day.' '.Period::monthShort($date->startOfWeek()->month)],
                Period::GRANULARITY_MONTH => [$date->format('Y-m'), ucfirst(Period::monthShort($date->month)).' '.$date->format('y')],
                default => [$date->toDateString(), $date->day.' '.Period::monthShort($date->month)],
            };

            if (! array_key_exists($key, $labels)) {
                $labels[$key] = $label;
                $values[$key] = 0.0;
            }

            $values[$key] = round($values[$key] + $value, 2);
        }

        return ['labels' => array_values($labels), 'values' => array_values($values)];
    }

    /**
     * Tendencia del período con el anterior alineado por posición.
     *
     * @param  Closure(CarbonImmutable, CarbonImmutable): list<float>  $dailyFor
     * @return array{labels: list<string>, current: list<float>, previous: list<float>, previous_labels: list<string>}
     */
    public static function trend(Period $period, Closure $dailyFor): array
    {
        $granularity = $period->granularity();
        $current = self::bucket($dailyFor($period->from, $period->to), $period->from, $granularity);
        $previous = self::bucket($dailyFor($period->previousFrom, $period->previousTo), $period->previousFrom, $granularity);
        $length = count($current['labels']);

        return [
            'labels' => $current['labels'],
            'current' => $current['values'],
            'previous' => array_slice(array_pad($previous['values'], $length, 0.0), 0, $length),
            'previous_labels' => array_slice(array_pad($previous['labels'], $length, ''), 0, $length),
        ];
    }

    /**
     * Variación porcentual; null si no hay base para comparar.
     */
    public static function change(float $current, float $previous): ?float
    {
        if ($previous == 0.0) {
            return null;
        }

        return round(($current - $previous) * 100 / abs($previous), 1);
    }

    public static function money(float $amount, int $decimals = 0): string
    {
        return 'US$ '.number_format($amount, $decimals, ',', '.');
    }

    public static function number(int|float $value, int $decimals = 0): string
    {
        return number_format((float) $value, $decimals, ',', '.');
    }

    /**
     * Ordena de mayor a menor y deja las primeras `$limit` filas más «Otros».
     *
     * @param  array<string, float|int>  $values
     * @return array{rows: list<array{label: string, value: float|int, key: string}>, others: float|int, total: float|int}
     */
    public static function ranked(array $values, int $limit): array
    {
        arsort($values);
        $rows = [];

        foreach (array_slice($values, 0, $limit, true) as $label => $value) {
            $rows[] = ['label' => (string) $label, 'value' => $value, 'key' => (string) $label];
        }

        return [
            'rows' => $rows,
            'others' => array_sum(array_slice($values, $limit, null, true)),
            'total' => array_sum($values),
        ];
    }
}
