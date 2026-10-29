<?php

declare(strict_types=1);

namespace App\Support\Operations;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Facturas de proveedores cargadas (por fecha de registro) en un mes, y su comparación con
 * el mes anterior. Si el mes es el actual se compara contra el mismo tramo del anterior
 * (del 1 al mismo día): comparar medio mes contra un mes completo siempre daría «bajó».
 *
 * @phpstan-type Totals array{count: int, usd: float, ves: float}
 */
final class AccountsPayableMonthlyLoad
{
    /** Meses que ofrece el selector hacia atrás, contando el actual. */
    public const MAX_MONTHS = 24;

    /**
     * Meses con sentido para el selector: desde la primera factura cargada (o como mucho
     * {@see self::MAX_MONTHS} atrás) hasta el mes en curso, del más reciente al más antiguo.
     *
     * @return array<string, string> «2026-10» => «Octubre 2026»
     */
    public static function monthOptions(?CarbonImmutable $today = null): array
    {
        $current = ($today ?? CarbonImmutable::today())->startOfMonth();
        $oldest = $current->subMonths(self::MAX_MONTHS - 1);
        $first = DB::table('operation_accounts_payables')->min('invoice_registration_date');

        if (is_string($first) && preg_match('/^\d{4}-\d{2}-\d{2}/', $first) === 1) {
            $firstMonth = CarbonImmutable::parse(substr($first, 0, 10))->startOfMonth();
            $oldest = $firstMonth->greaterThan($oldest) ? $firstMonth : $oldest;
        }

        if ($oldest->greaterThan($current)) {
            $oldest = $current;
        }

        $options = [];

        for ($month = $current; $month->greaterThanOrEqualTo($oldest); $month = $month->subMonth()) {
            $options[$month->format('Y-m')] = self::monthLabel($month);
        }

        return $options;
    }

    /**
     * Mes pedido si es válido y no es futuro; si no, el mes en curso.
     */
    public static function resolveMonth(mixed $value, ?CarbonImmutable $today = null): CarbonImmutable
    {
        $current = ($today ?? CarbonImmutable::today())->startOfMonth();

        if (! is_string($value) || preg_match('/^(\d{4})-(\d{2})$/', $value, $matches) !== 1) {
            return $current;
        }

        [$year, $month] = [(int) $matches[1], (int) $matches[2]];

        if ($month < 1 || $month > 12 || $year < 2000) {
            return $current;
        }

        $resolved = CarbonImmutable::create($year, $month, 1)->startOfDay();

        return $resolved->greaterThan($current) ? $current : $resolved;
    }

    /**
     * Rango de fechas de registro que cubre el mes: completo, o hasta hoy si es el actual.
     *
     * @return array{from: CarbonImmutable, until: CarbonImmutable}
     */
    public static function period(CarbonImmutable $month, ?CarbonImmutable $today = null): array
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $from = $month->startOfMonth();
        $until = $from->endOfMonth()->startOfDay();

        return ['from' => $from, 'until' => $until->greaterThan($today) ? $today : $until];
    }

    /**
     * Todo en una consulta: el mes, el mismo tramo del anterior y el anterior completo.
     *
     * @return array{
     *     month: string,
     *     label: string,
     *     is_current: bool,
     *     from: CarbonImmutable,
     *     until: CarbonImmutable,
     *     totals: Totals,
     *     previous: array{label: string, from: CarbonImmutable, until: CarbonImmutable, is_partial: bool, totals: Totals, full_totals: Totals},
     *     delta: array{count: int, usd: float, usd_percent: ?float}
     * }
     */
    public static function summary(CarbonImmutable $month, ?CarbonImmutable $today = null): array
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        ['from' => $from, 'until' => $until] = self::period($month, $today);
        $isCurrent = $from->isSameMonth($today);

        $previousFrom = $from->subMonthNoOverflow()->startOfMonth();
        $previousFullUntil = $previousFrom->endOfMonth()->startOfDay();
        $previousUntil = $isCurrent
            ? $previousFrom->addDays(min($today->day, $previousFrom->daysInMonth) - 1)
            : $previousFullUntil;

        $bucket = static fn (string $alias, CarbonImmutable $start, CarbonImmutable $end): array => [
            "SUM(CASE WHEN invoice_registration_date BETWEEN ? AND ? THEN 1 ELSE 0 END) as {$alias}_count, "
            ."SUM(CASE WHEN invoice_registration_date BETWEEN ? AND ? AND invoice_currency <> 'VES' THEN invoice_amount ELSE 0 END) as {$alias}_usd, "
            ."SUM(CASE WHEN invoice_registration_date BETWEEN ? AND ? AND invoice_currency = 'VES' THEN invoice_amount ELSE 0 END) as {$alias}_ves",
            array_merge(...array_fill(0, 3, [$start->toDateString(), $end->toDateString()])),
        ];

        $parts = [
            $bucket('current', $from, $until),
            $bucket('previous', $previousFrom, $previousUntil),
            $bucket('previous_full', $previousFrom, $previousFullUntil),
        ];

        $row = DB::table('operation_accounts_payables')
            ->whereBetween('invoice_registration_date', [$previousFrom->toDateString(), $until->toDateString()])
            ->selectRaw(implode(', ', array_column($parts, 0)), array_merge(...array_column($parts, 1)))
            ->first();

        $totals = static fn (string $alias): array => [
            'count' => (int) ($row->{$alias.'_count'} ?? 0),
            'usd' => round((float) ($row->{$alias.'_usd'} ?? 0), 2),
            'ves' => round((float) ($row->{$alias.'_ves'} ?? 0), 2),
        ];

        $current = $totals('current');
        $previous = $totals('previous');

        return [
            'month' => $from->format('Y-m'),
            'label' => self::monthLabel($from),
            'is_current' => $isCurrent,
            'from' => $from,
            'until' => $until,
            'totals' => $current,
            'previous' => [
                'label' => self::monthLabel($previousFrom),
                'from' => $previousFrom,
                'until' => $previousUntil,
                'is_partial' => $previousUntil->lessThan($previousFullUntil),
                'totals' => $previous,
                'full_totals' => $totals('previous_full'),
            ],
            'delta' => [
                'count' => $current['count'] - $previous['count'],
                'usd' => round($current['usd'] - $previous['usd'], 2),
                'usd_percent' => $previous['usd'] > 0
                    ? round((($current['usd'] - $previous['usd']) / $previous['usd']) * 100, 1)
                    : null,
            ],
        ];
    }

    public static function monthLabel(CarbonImmutable $month): string
    {
        return Str::ucfirst($month->locale('es')->translatedFormat('F Y'));
    }
}
