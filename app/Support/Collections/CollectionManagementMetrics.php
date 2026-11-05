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
     * Morosidad de un mes: de las cuotas que vencían ese mes (sin anuladas ni canceladas), qué
     * parte del monto sigue sin pagar. En el mes en curso solo cuentan las que ya vencieron
     * (del día 1 a ayer). El estado es el de hoy: una cuota de agosto pagada en octubre ya no
     * cuenta como morosa. Mismo universo de Gestión de Cobranza (sin afiliaciones dadas de baja).
     *
     * @return array{month: string, label: string, from: string, to: ?string, rate: float, due_amount: float, unpaid_amount: float, due_count: int, unpaid_count: int, by_type: array{individual: array{rate: float, due_amount: float, unpaid_amount: float}, corporate: array{rate: float, due_amount: float, unpaid_amount: float}}}
     */
    public static function monthlyDelinquency(CarbonImmutable $month, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $from = $month->startOfMonth();
        $lastDueDay = $today->startOfDay()->subDay();
        $to = $month->endOfMonth()->startOfDay()->lessThan($lastDueDay) ? $month->endOfMonth()->startOfDay() : $lastDueDay;
        $empty = ['rate' => 0.0, 'due_amount' => 0.0, 'unpaid_amount' => 0.0];
        $result = [
            'month' => $from->format('Y-m'),
            'label' => ucfirst($from->locale('es')->translatedFormat('F Y')),
            'from' => $from->toDateString(),
            'to' => $to->greaterThanOrEqualTo($from) ? $to->toDateString() : null,
            'rate' => 0.0,
            'due_amount' => 0.0,
            'unpaid_amount' => 0.0,
            'due_count' => 0,
            'unpaid_count' => 0,
            'by_type' => ['individual' => $empty, 'corporate' => $empty],
        ];

        /** Mes que todavía no tiene cuotas vencidas (futuro o día 1 del mes en curso). */
        if ($result['to'] === null) {
            return $result;
        }

        $pending = CollectionReceivableReport::PENDING_STATUS;
        $corporate = CollectionReceivableReport::CORPORATE_TYPE_SQL;
        $query = CollectionReceivableReport::excludeNonCollectableAffiliations(Collection::query())
            ->whereNotIn('collections.status', CollectionReceivableReport::VOIDED_NOTICE_STATUSES)
            ->whereBetween('collections.filter_next_payment_date', [$result['from'], $result['to']])
            ->selectRaw('COUNT(*) as due_count, COALESCE(SUM(collections.total_amount), 0) as due_amount')
            ->selectRaw('SUM(CASE WHEN collections.status = ? THEN 1 ELSE 0 END) as unpaid_count', [$pending])
            ->selectRaw('COALESCE(SUM(CASE WHEN collections.status = ? THEN collections.total_amount ELSE 0 END), 0) as unpaid_amount', [$pending]);

        foreach (['corporate' => $corporate, 'individual' => "NOT ({$corporate})"] as $type => $condition) {
            $query
                ->selectRaw("COALESCE(SUM(CASE WHEN {$condition} THEN collections.total_amount ELSE 0 END), 0) as {$type}_due")
                ->selectRaw("COALESCE(SUM(CASE WHEN {$condition} AND collections.status = ? THEN collections.total_amount ELSE 0 END), 0) as {$type}_unpaid", [$pending]);
        }

        $row = $query->toBase()->first();

        $result['due_count'] = (int) ($row->due_count ?? 0);
        $result['unpaid_count'] = (int) ($row->unpaid_count ?? 0);
        $result['due_amount'] = round((float) ($row->due_amount ?? 0), 2);
        $result['unpaid_amount'] = round((float) ($row->unpaid_amount ?? 0), 2);
        $result['rate'] = self::delinquencyRate($result['unpaid_amount'], $result['due_amount']);

        foreach (['individual', 'corporate'] as $type) {
            $due = round((float) ($row->{"{$type}_due"} ?? 0), 2);
            $unpaid = round((float) ($row->{"{$type}_unpaid"} ?? 0), 2);
            $result['by_type'][$type] = ['rate' => self::delinquencyRate($unpaid, $due), 'due_amount' => $due, 'unpaid_amount' => $unpaid];
        }

        return $result;
    }

    /**
     * Meses que se pueden consultar en la morosidad: el actual y los 17 anteriores.
     *
     * @return array<string, string> `Y-m` => «Noviembre 2026»
     */
    public static function delinquencyMonthOptions(?CarbonImmutable $today = null): array
    {
        $month = ($today ?? CarbonImmutable::today())->startOfMonth();
        $options = [];

        for ($i = 0; $i < 18; $i++) {
            $options[$month->format('Y-m')] = ucfirst($month->locale('es')->translatedFormat('F Y'));
            $month = $month->subMonthNoOverflow();
        }

        return $options;
    }

    /**
     * Morosidad: vencido ÷ por cobrar × 100, con dos decimales. Sin cartera, 0.
     */
    public static function delinquencyRate(float $overdue, float $pending): float
    {
        return $pending > 0 ? round($overdue * 100 / $pending, 2) : 0.0;
    }
}
