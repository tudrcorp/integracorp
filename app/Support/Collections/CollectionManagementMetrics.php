<?php

declare(strict_types=1);

namespace App\Support\Collections;

use App\Models\Collection;
use App\Models\Sale;
use Carbon\CarbonImmutable;

/**
 * Indicadores del Dashboard de Gestión de Cobranza.
 *
 * - **Cobrado**: pagos registrados (`sales.total_amount`) por su fecha de registro, la misma
 *   fuente que «Ingresos» del Escritorio. Individual = ventas de afiliación individual;
 *   corporativo = afiliación corporativa y Nuevos Negocios.
 * - **Cartera** (por cobrar, vencido, plazos): todas las cuotas por pagar de las afiliaciones
 *   que se pueden cobrar, el mismo universo de la tabla de Gestión de Cobranza
 *   ({@see CollectionReceivableReport::summary()}).
 * - **Morosidad**: lo vencido ÷ el total por cobrar, en %, general y por tipo.
 */
final class CollectionManagementMetrics
{
    private const INDIVIDUAL_SALE_SQL = "UPPER(COALESCE(sales.type, '')) LIKE '%INDIVIDUAL%'";

    /**
     * Lo cobrado en el mes y en el año de `$today`, separado en individual y corporativo, y
     * mes a mes (enero a diciembre). Una sola consulta acotada al año, sin GROUP BY.
     *
     * @return array{month: array{individual: array{affiliations: int, count: int, amount: float}, corporate: array{affiliations: int, count: int, amount: float}}, year: array{individual: array{affiliations: int, count: int, amount: float}, corporate: array{affiliations: int, count: int, amount: float}}, months: list<array{month: int, label: string, count: int, amount: float}>}
     */
    public static function collected(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $yearStart = $today->startOfYear();
        $individual = self::INDIVIDUAL_SALE_SQL;
        $periods = [
            'year' => ['1 = 1', []],
            'month' => ['sales.created_at >= ? AND sales.created_at <= ?', [$today->startOfMonth()->toDateTimeString(), $today->endOfMonth()->toDateTimeString()]],
        ];

        $query = Sale::query()->whereBetween('sales.created_at', [$yearStart->toDateTimeString(), $today->endOfYear()->toDateTimeString()]);

        foreach ($periods as $period => [$condition, $bindings]) {
            foreach (['individual' => $individual, 'corporate' => "NOT ({$individual})"] as $type => $typeCondition) {
                $query
                    ->selectRaw("SUM(CASE WHEN {$condition} AND {$typeCondition} THEN 1 ELSE 0 END) as {$period}_{$type}_count", $bindings)
                    ->selectRaw("COALESCE(SUM(CASE WHEN {$condition} AND {$typeCondition} THEN sales.total_amount ELSE 0 END), 0) as {$period}_{$type}_amount", $bindings)
                    ->selectRaw("COUNT(DISTINCT CASE WHEN {$condition} AND {$typeCondition} THEN sales.affiliation_code END) as {$period}_{$type}_affiliations", $bindings);
            }
        }

        for ($month = 1; $month <= 12; $month++) {
            $start = $yearStart->month($month)->startOfMonth();
            $range = [$start->toDateTimeString(), $start->endOfMonth()->toDateTimeString()];

            $query
                ->selectRaw("SUM(CASE WHEN sales.created_at >= ? AND sales.created_at <= ? THEN 1 ELSE 0 END) as m{$month}_count", $range)
                ->selectRaw("COALESCE(SUM(CASE WHEN sales.created_at >= ? AND sales.created_at <= ? THEN sales.total_amount ELSE 0 END), 0) as m{$month}_amount", $range);
        }

        $totals = $query->toBase()->first();
        $result = [];

        foreach (array_keys($periods) as $period) {
            foreach (['individual', 'corporate'] as $type) {
                $result[$period][$type] = [
                    'affiliations' => (int) ($totals->{"{$period}_{$type}_affiliations"} ?? 0),
                    'count' => (int) ($totals->{"{$period}_{$type}_count"} ?? 0),
                    'amount' => round((float) ($totals->{"{$period}_{$type}_amount"} ?? 0), 2),
                ];
            }
        }

        $months = [];

        for ($month = 1; $month <= 12; $month++) {
            $months[] = [
                'month' => $month,
                'label' => ucfirst($yearStart->month($month)->locale('es')->translatedFormat('M')),
                'count' => (int) ($totals->{"m{$month}_count"} ?? 0),
                'amount' => round((float) ($totals->{"m{$month}_amount"} ?? 0), 2),
            ];
        }

        return ['month' => $result['month'], 'year' => $result['year'], 'months' => $months];
    }

    /**
     * Cartera por cobrar de Gestión de Cobranza: todas las cuotas por pagar de las
     * afiliaciones que se pueden cobrar.
     *
     * @return array<string, mixed>
     */
    public static function portfolio(?CarbonImmutable $today = null): array
    {
        return CollectionReceivableReport::summary(
            CollectionReceivableReport::excludeNonCollectableAffiliations(
                Collection::query()->where('collections.status', CollectionReceivableReport::PENDING_STATUS),
            ),
            $today,
        );
    }

    /**
     * Morosidad: vencido ÷ por cobrar × 100, con dos decimales. Sin cartera, 0.
     */
    public static function delinquencyRate(float $overdue, float $pending): float
    {
        return $pending > 0 ? round($overdue * 100 / $pending, 2) : 0.0;
    }
}
