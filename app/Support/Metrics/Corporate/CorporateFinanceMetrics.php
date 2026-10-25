<?php

declare(strict_types=1);

namespace App\Support\Metrics\Corporate;

use App\Models\Collection;
use App\Support\Collections\CollectionAdjuster;
use App\Support\Collections\CollectionReceivableReport;
use App\Support\Operations\Dashboard\OperationsDashboardPeriod as Period;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Finanzas de la empresa en el período (todo en USD).
 *
 * - Ingresos cobrados: pagos registrados (`sales.total_amount`) por su fecha.
 * - Por cobrar / vencido: cuotas pendientes cobrables, con la misma regla que
 *   Cuentas por cobrar (foto a hoy, no depende del período).
 * - Comisiones: lo generado para agentes, agencias, subagentes y referidores.
 * - Costo médico estimado: lo facturado por proveedores en órdenes de servicio
 *   finalizadas (o su total, si aún no tiene factura).
 * - Margen estimado = ingresos − comisiones − costo médico.
 * - Siniestralidad estimada = costo médico ÷ ingresos.
 *
 * El costo médico solo existe desde que Operaciones registra órdenes en el
 * sistema: {@see self::costCoverageNotice()} avisa cuando el período empieza antes.
 */
final class CorporateFinanceMetrics
{
    public const CORPORATE_PLAN_LABEL = 'CORPORATIVAS (VARIOS PLANES)';

    /** Columnas de comisión en USD por beneficiario. */
    public const COMMISSION_COLUMNS = [
        'Agentes' => 'commission_agent_usd',
        'Agencias master' => 'commission_agency_master_usd',
        'Agencias generales' => 'commission_agency_general_usd',
        'Subagentes' => 'commission_sub_agent_usd',
        'Referidores' => 'commission_referidor_usd',
    ];

    /** Tramos de antigüedad de la deuda vencida (días). */
    public const AGING = [
        '1–30 días' => [1, 30],
        '31–60 días' => [31, 60],
        '61–90 días' => [61, 90],
        'Más de 90 días' => [91, null],
    ];

    public const COST_STATUS = 'FINALIZADO';

    public const COST_SQL = 'COALESCE(NULLIF(operation_service_orders.invoice_amount_usd, 0), operation_service_orders.total_amount_usd, 0)';

    /**
     * @return array{income: float, payments: int, average_ticket: float, commissions: float, medical_cost: float, margin: float, margin_rate: float|null, loss_ratio: float|null}
     */
    public static function summary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $sales = self::salesBetween($from, $to)
            ->selectRaw('COUNT(*) as payments, COALESCE(SUM(sales.total_amount), 0) as income')
            ->first();

        $income = round((float) ($sales->income ?? 0), 2);
        $payments = (int) ($sales->payments ?? 0);
        $commissions = self::commissionsTotal($from, $to);
        $cost = self::medicalCost($from, $to);
        $margin = round($income - $commissions - $cost, 2);

        return [
            'income' => $income,
            'payments' => $payments,
            'average_ticket' => $payments > 0 ? round($income / $payments, 2) : 0.0,
            'commissions' => $commissions,
            'medical_cost' => $cost,
            'margin' => $margin,
            'margin_rate' => $income > 0 ? round($margin * 100 / $income, 1) : null,
            'loss_ratio' => $income > 0 ? round($cost * 100 / $income, 1) : null,
        ];
    }

    /**
     * Resumen del período y del anterior, con las series diarias de los KPI.
     *
     * @return array{current: array<string, float|int|null>, previous: array<string, float|int|null>, sparklines: array<string, list<float>>}
     */
    public static function kpis(Period $period): array
    {
        return CorporateMetrics::remember('finance_kpis', $period, static fn (): array => [
            'current' => self::summary($period->from, $period->to),
            'previous' => self::summary($period->previousFrom, $period->previousTo),
            'sparklines' => [
                'income' => self::incomeDaily($period->from, $period->to),
                'cost' => self::costDaily($period->from, $period->to),
            ],
        ]);
    }

    /**
     * @return array{labels: list<string>, current: list<float>, previous: list<float>, previous_labels: list<string>}
     */
    public static function incomeTrend(Period $period): array
    {
        return CorporateMetrics::remember('finance_income_trend', $period, static fn (): array => CorporateMetrics::trend(
            $period,
            static fn (CarbonImmutable $from, CarbonImmutable $to): array => self::incomeDaily($from, $to),
        ));
    }

    /**
     * Ingresos, costos (comisiones + médico) y margen por día/semana/mes del período.
     *
     * @return array{labels: list<string>, income: list<float>, costs: list<float>}
     */
    public static function incomeVsCosts(Period $period): array
    {
        return CorporateMetrics::remember('finance_income_vs_costs', $period, static function () use ($period): array {
            $granularity = $period->granularity();
            $income = CorporateMetrics::bucket(self::incomeDaily($period->from, $period->to), $period->from, $granularity);
            $commissions = CorporateMetrics::daily(self::commissionsBetween($period->from, $period->to), 'commissions.created_at', $period->from, $period->to, 'COALESCE(SUM('.self::commissionSumSql().'), 0)');
            $costDaily = self::costDaily($period->from, $period->to);
            $costs = CorporateMetrics::bucket(array_map(static fn (float $a, float $b): float => round($a + $b, 2), $commissions, $costDaily), $period->from, $granularity);

            return ['labels' => $income['labels'], 'income' => $income['values'], 'costs' => $costs['values']];
        });
    }

    /**
     * @return array<string, float>
     */
    public static function incomeByType(Period $period): array
    {
        return CorporateMetrics::remember('finance_income_by_type', $period, static function () use ($period): array {
            $label = self::saleTypeSql();

            return self::salesBetween($period->from, $period->to)
                ->selectRaw($label.' as label, COALESCE(SUM(sales.total_amount), 0) as amount')
                ->groupByRaw($label)
                ->orderByDesc('amount')
                ->pluck('amount', 'label')
                ->map(static fn (mixed $amount): float => round((float) $amount, 2))
                ->all();
        });
    }

    /**
     * @return array<string, float>
     */
    public static function incomeByPlan(Period $period): array
    {
        return CorporateMetrics::remember('finance_income_by_plan', $period, static function () use ($period): array {
            $label = "CASE WHEN UPPER(sales.type) LIKE '%CORPORATIV%' THEN '".self::CORPORATE_PLAN_LABEL."' ELSE COALESCE(NULLIF(TRIM(plans.description), ''), 'SIN PLAN') END";

            $rows = self::salesBetween($period->from, $period->to)
                ->leftJoin('plans', 'plans.id', '=', 'sales.plan_id')
                ->selectRaw('UPPER('.$label.') as label, COALESCE(SUM(sales.total_amount), 0) as amount')
                ->groupByRaw('UPPER('.$label.')')
                ->pluck('amount', 'label')
                ->map(static fn (mixed $amount): float => round((float) $amount, 2))
                ->all();

            arsort($rows);

            return $rows;
        });
    }

    /**
     * Ingresos por agencia (código · nombre).
     *
     * @return array{rows: list<array{label: string, value: float, key: string}>, others: float, total: float}
     */
    public static function incomeByAgency(Period $period, int $limit = 10): array
    {
        return CorporateMetrics::remember('finance_income_by_agency_'.$limit, $period, static function () use ($period, $limit): array {
            $rows = self::salesBetween($period->from, $period->to)
                ->leftJoin('agencies', 'agencies.code', '=', 'sales.code_agency')
                ->selectRaw("COALESCE(NULLIF(TRIM(sales.code_agency), ''), 'SIN AGENCIA') as code")
                ->selectRaw('MAX(agencies.name_corporative) as name')
                ->selectRaw('COALESCE(SUM(sales.total_amount), 0) as amount')
                ->groupByRaw("COALESCE(NULLIF(TRIM(sales.code_agency), ''), 'SIN AGENCIA')")
                ->orderByDesc('amount')
                ->get();

            $mapped = $rows->map(static fn (object $row): array => [
                'label' => trim((string) $row->code.(filled($row->name) ? ' · '.$row->name : '')),
                'value' => round((float) $row->amount, 2),
                'key' => (string) $row->code,
            ]);

            return [
                'rows' => $mapped->take($limit)->values()->all(),
                'others' => round((float) $mapped->slice($limit)->sum('value'), 2),
                'total' => round((float) $mapped->sum('value'), 2),
            ];
        });
    }

    /**
     * Ingresos de ventas hechas con empresas aliadas (marca blanca).
     *
     * @return array{rows: list<array{label: string, value: float, key: string}>, others: float, total: float}
     */
    public static function incomeByWhiteCompany(Period $period, int $limit = 8): array
    {
        return CorporateMetrics::remember('finance_white_companies_'.$limit, $period, static function () use ($period, $limit): array {
            $rows = self::salesBetween($period->from, $period->to)
                ->join('white_companies', 'white_companies.id', '=', 'sales.white_company_id')
                ->selectRaw('white_companies.id as id, MAX(white_companies.name) as name, COALESCE(SUM(sales.total_amount), 0) as amount')
                ->groupBy('white_companies.id')
                ->orderByDesc('amount')
                ->get();

            $mapped = $rows->map(static fn (object $row): array => [
                'label' => (string) $row->name,
                'value' => round((float) $row->amount, 2),
                'key' => (string) $row->id,
            ]);

            return [
                'rows' => $mapped->take($limit)->values()->all(),
                'others' => round((float) $mapped->slice($limit)->sum('value'), 2),
                'total' => round((float) $mapped->sum('value'), 2),
            ];
        });
    }

    /**
     * Cuentas por cobrar a hoy: pendiente, vencido y antigüedad del vencido.
     *
     * @return array{pending_amount: float, pending_count: int, overdue_amount: float, overdue_count: int, affiliations: int, aging: array<string, float>}
     */
    public static function receivables(): array
    {
        return CorporateMetrics::remember('finance_receivables_'.CarbonImmutable::today()->toDateString(), null, static function (): array {
            $today = CarbonImmutable::today();
            $query = self::pendingCollectableQuery()
                ->selectRaw('COUNT(*) as pending_count, COALESCE(SUM(collections.total_amount), 0) as pending_amount, COUNT(DISTINCT collections.affiliation_code) as affiliations')
                ->selectRaw('SUM(CASE WHEN collections.filter_next_payment_date < ? THEN 1 ELSE 0 END) as overdue_count', [$today->toDateString()])
                ->selectRaw('COALESCE(SUM(CASE WHEN collections.filter_next_payment_date < ? THEN collections.total_amount ELSE 0 END), 0) as overdue_amount', [$today->toDateString()]);

            $index = 0;

            foreach (self::AGING as [$min, $max]) {
                [$condition, $bindings] = self::agingCondition($today, $min, $max);
                $query->selectRaw("COALESCE(SUM(CASE WHEN {$condition} THEN collections.total_amount ELSE 0 END), 0) as aging_{$index}", $bindings);
                $index++;
            }

            $row = $query->toBase()->first();
            $aging = [];
            $index = 0;

            foreach (array_keys(self::AGING) as $label) {
                $aging[$label] = round((float) ($row->{'aging_'.$index} ?? 0), 2);
                $index++;
            }

            return [
                'pending_amount' => round((float) ($row->pending_amount ?? 0), 2),
                'pending_count' => (int) ($row->pending_count ?? 0),
                'overdue_amount' => round((float) ($row->overdue_amount ?? 0), 2),
                'overdue_count' => (int) ($row->overdue_count ?? 0),
                'affiliations' => (int) ($row->affiliations ?? 0),
                'aging' => $aging,
            ];
        });
    }

    /**
     * Deuda pendiente por agencia, a hoy.
     *
     * @return array{rows: list<array{label: string, value: float, key: string}>, others: float, total: float}
     */
    public static function receivablesByAgency(int $limit = 8): array
    {
        return CorporateMetrics::remember('finance_receivables_agency_'.$limit.'_'.CarbonImmutable::today()->toDateString(), null, static function () use ($limit): array {
            $rows = self::pendingCollectableQuery()
                ->leftJoin('agencies', 'agencies.code', '=', 'collections.code_agency')
                ->selectRaw("COALESCE(NULLIF(TRIM(collections.code_agency), ''), 'SIN AGENCIA') as code")
                ->selectRaw('MAX(agencies.name_corporative) as name')
                ->selectRaw('COALESCE(SUM(collections.total_amount), 0) as amount')
                ->groupByRaw("COALESCE(NULLIF(TRIM(collections.code_agency), ''), 'SIN AGENCIA')")
                ->orderByDesc('amount')
                ->toBase()
                ->get();

            $mapped = $rows->map(static fn (object $row): array => [
                'label' => trim((string) $row->code.(filled($row->name) ? ' · '.$row->name : '')),
                'value' => round((float) $row->amount, 2),
                'key' => (string) $row->code,
            ]);

            return [
                'rows' => $mapped->take($limit)->values()->all(),
                'others' => round((float) $mapped->slice($limit)->sum('value'), 2),
                'total' => round((float) $mapped->sum('value'), 2),
            ];
        });
    }

    /**
     * @return array<string, float>
     */
    public static function commissionsByBeneficiary(Period $period): array
    {
        return CorporateMetrics::remember('finance_commissions_beneficiary', $period, static function () use ($period): array {
            $query = self::commissionsBetween($period->from, $period->to);

            foreach (self::COMMISSION_COLUMNS as $label => $column) {
                $query->selectRaw("COALESCE(SUM(commissions.{$column}), 0) as `".md5($label).'`');
            }

            $row = $query->first();
            $out = [];

            foreach (self::COMMISSION_COLUMNS as $label => $column) {
                $out[$label] = round((float) ($row->{md5($label)} ?? 0), 2);
            }

            arsort($out);

            return array_filter($out, static fn (float $value): bool => $value > 0);
        });
    }

    /**
     * Costo médico estimado por proveedor.
     *
     * @return array{rows: list<array{label: string, value: float, key: string}>, others: float, total: float}
     */
    public static function costByProvider(Period $period, int $limit = 8): array
    {
        return CorporateMetrics::remember('finance_cost_provider_'.$limit, $period, static function () use ($period, $limit): array {
            $rows = self::costOrdersBetween($period->from, $period->to)
                ->leftJoin('suppliers', 'suppliers.id', '=', 'operation_service_orders.supplier_id')
                ->selectRaw("COALESCE(MAX(suppliers.name), NULLIF(TRIM(MAX(operation_service_orders.supplier_external)), ''), 'Sin proveedor') as name")
                ->selectRaw('COALESCE(operation_service_orders.supplier_id, 0) as id')
                ->selectRaw('COALESCE(SUM('.self::COST_SQL.'), 0) as amount')
                ->groupByRaw('COALESCE(operation_service_orders.supplier_id, 0)')
                ->orderByDesc('amount')
                ->get();

            $mapped = $rows->map(static fn (object $row): array => [
                'label' => (string) $row->name,
                'value' => round((float) $row->amount, 2),
                'key' => (string) $row->id,
            ]);

            return [
                'rows' => $mapped->take($limit)->values()->all(),
                'others' => round((float) $mapped->slice($limit)->sum('value'), 2),
                'total' => round((float) $mapped->sum('value'), 2),
            ];
        });
    }

    /**
     * Aviso cuando el período empieza antes de que existan costos médicos en el
     * sistema: margen y siniestralidad quedan sobreestimados/subestimados.
     */
    public static function costCoverageNotice(Period $period): ?string
    {
        $first = CorporateMetrics::remember('finance_first_cost_date', null, static fn (): ?string => DB::table('operation_service_orders')->min('created_at'));

        if ($first === null) {
            return 'Aún no hay costos médicos registrados: margen y siniestralidad son estimados sin costo.';
        }

        $firstDate = CarbonImmutable::parse($first)->startOfDay();

        return $period->previousFrom->lessThan($firstDate)
            ? 'Estimado: los costos médicos existen desde el '.$firstDate->format('d/m/Y').'; antes de esa fecha el margen no los descuenta.'
            : null;
    }

    public static function salesBetween(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return DB::table('sales')->whereBetween('sales.created_at', CorporateMetrics::range($from, $to));
    }

    public static function commissionsBetween(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return DB::table('commissions')->whereBetween('commissions.created_at', CorporateMetrics::range($from, $to));
    }

    public static function costOrdersBetween(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return DB::table('operation_service_orders')
            ->where('operation_service_orders.status', self::COST_STATUS)
            ->whereBetween('operation_service_orders.created_at', CorporateMetrics::range($from, $to));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Collection>
     */
    public static function pendingCollectableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return CollectionReceivableReport::excludeNonCollectableAffiliations(
            Collection::query()->where('collections.status', CollectionAdjuster::STATUS_PENDING),
        );
    }

    /**
     * @return list<float>
     */
    public static function incomeDaily(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return CorporateMetrics::daily(self::salesBetween($from, $to), 'sales.created_at', $from, $to, 'COALESCE(SUM(sales.total_amount), 0)');
    }

    /**
     * @return list<float>
     */
    public static function costDaily(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return CorporateMetrics::daily(self::costOrdersBetween($from, $to), 'operation_service_orders.created_at', $from, $to, 'COALESCE(SUM('.self::COST_SQL.'), 0)');
    }

    public static function saleTypeSql(): string
    {
        return "CASE
            WHEN UPPER(sales.type) LIKE '%CORPORATIV%' THEN 'Afiliación corporativa'
            WHEN UPPER(sales.type) LIKE '%INDIVIDUAL%' THEN 'Afiliación individual'
            WHEN TRIM(COALESCE(sales.type, '')) = '' THEN 'Sin tipo'
            ELSE CONCAT(UPPER(LEFT(TRIM(sales.type), 1)), LOWER(SUBSTRING(TRIM(sales.type), 2)))
        END";
    }

    public static function commissionSumSql(): string
    {
        return implode(' + ', array_map(static fn (string $column): string => "COALESCE(commissions.{$column}, 0)", self::COMMISSION_COLUMNS));
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    public static function agingCondition(CarbonImmutable $today, int $min, ?int $max): array
    {
        $newest = $today->subDays($min)->toDateString();

        return $max === null
            ? ['collections.filter_next_payment_date <= ?', [$newest]]
            : ['collections.filter_next_payment_date BETWEEN ? AND ?', [$today->subDays($max)->toDateString(), $newest]];
    }

    private static function commissionsTotal(CarbonImmutable $from, CarbonImmutable $to): float
    {
        return round((float) self::commissionsBetween($from, $to)->selectRaw('COALESCE(SUM('.self::commissionSumSql().'), 0) as total')->value('total'), 2);
    }

    private static function medicalCost(CarbonImmutable $from, CarbonImmutable $to): float
    {
        return round((float) self::costOrdersBetween($from, $to)->selectRaw('COALESCE(SUM('.self::COST_SQL.'), 0) as total')->value('total'), 2);
    }
}
