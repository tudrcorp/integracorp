<?php

declare(strict_types=1);

namespace App\Support\Administration\Dashboard;

use App\Support\BcvOfficialRate;
use App\Support\Metrics\Corporate\CorporateFinanceMetrics;
use App\Support\Metrics\Corporate\CorporateMetrics;
use App\Support\Operations\Dashboard\DashboardGrouping;
use App\Support\Operations\Dashboard\OperationsDashboardPeriod as Period;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cifras financieras del Escritorio de Administración que el panel corporativo
 * no tiene: flujo de caja (ingresos frente a egresos), cobranza esperada,
 * facturas de proveedores, comisiones pendientes, TDEV y renovaciones por
 * vencer. Lo que ya existe (ingresos, cobranza, comisiones, costo médico) se
 * toma de {@see CorporateFinanceMetrics} para que ambos paneles cuadren.
 *
 * Todo en USD. Egresos = comisiones generadas + costo médico (órdenes
 * finalizadas) + facturas de proveedores registradas en el período. La nómina
 * va aparte, en RRHH, porque sus montos tienen permiso propio.
 */
final class AdministrationFinanceMetrics
{
    public const PAYABLE_PENDING = 'PENDIENTE POR PAGAR';

    public const COMMISSION_PAID = 'COMISION PAGADA';

    public const RENEWAL_WINDOW = 'PERIODO DE RENOVACION';

    /** Días hacia adelante de la cobranza esperada y las renovaciones por vencer. */
    public const HORIZON_DAYS = 30;

    /**
     * Tasa BCV del día. La consulta externa se guarda una hora; si falla, null
     * (no se guarda) y se vuelve a intentar en la próxima carga.
     */
    public static function bcvRate(): ?float
    {
        $key = 'admin-dashboard:bcv:'.CarbonImmutable::today()->toDateString();
        $rate = Cache::get($key);

        if (is_numeric($rate)) {
            return (float) $rate;
        }

        $rate = BcvOfficialRate::resolve();

        if ($rate !== null && $rate > 0) {
            Cache::put($key, $rate, 3600);
        }

        return $rate;
    }

    /**
     * @return array{commissions: float, medical_cost: float, supplier_invoices: float, total: float}
     */
    public static function outflows(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $commissions = (float) CorporateFinanceMetrics::commissionsBetween($from, $to)
            ->selectRaw('COALESCE(SUM('.CorporateFinanceMetrics::commissionSumSql().'), 0) as total')
            ->value('total');
        $medical = (float) CorporateFinanceMetrics::costOrdersBetween($from, $to)
            ->selectRaw('COALESCE(SUM('.CorporateFinanceMetrics::COST_SQL.'), 0) as total')
            ->value('total');
        $invoices = (float) self::invoicesBetween($from, $to)->sum('operation_accounts_payables.invoice_amount');

        return [
            'commissions' => round($commissions, 2),
            'medical_cost' => round($medical, 2),
            'supplier_invoices' => round($invoices, 2),
            'total' => round($commissions + $medical + $invoices, 2),
        ];
    }

    /**
     * @return array{current: array<string, float|int|null>, previous: array<string, float|int|null>, sparklines: array<string, list<float>>}
     */
    public static function kpis(Period $period): array
    {
        return CorporateMetrics::remember('admin_kpis', $period, static function () use ($period): array {
            $build = static function (CarbonImmutable $from, CarbonImmutable $to): array {
                $income = CorporateFinanceMetrics::summary($from, $to);
                $outflows = self::outflows($from, $to);

                return [
                    'income' => $income['income'],
                    'payments' => $income['payments'],
                    'outflows' => $outflows['total'],
                    'net' => round($income['income'] - $outflows['total'], 2),
                    'commissions' => $outflows['commissions'],
                    'medical_cost' => $outflows['medical_cost'],
                    'supplier_invoices' => $outflows['supplier_invoices'],
                ];
            };

            return [
                'current' => $build($period->from, $period->to),
                'previous' => $build($period->previousFrom, $period->previousTo),
                'sparklines' => [
                    'income' => CorporateFinanceMetrics::incomeDaily($period->from, $period->to),
                    'outflows' => self::outflowsDaily($period->from, $period->to),
                ],
            ];
        });
    }

    /**
     * Ingresos frente a egresos en el tiempo.
     *
     * @return array{labels: list<string>, income: list<float>, outflows: list<float>}
     */
    public static function cashFlow(Period $period): array
    {
        return CorporateMetrics::remember('admin_cash_flow', $period, static function () use ($period): array {
            $granularity = $period->granularity();
            $income = CorporateMetrics::bucket(CorporateFinanceMetrics::incomeDaily($period->from, $period->to), $period->from, $granularity);
            $outflows = CorporateMetrics::bucket(self::outflowsDaily($period->from, $period->to), $period->from, $granularity);

            return ['labels' => $income['labels'], 'income' => $income['values'], 'outflows' => $outflows['values']];
        });
    }

    /**
     * Composición de los egresos del período.
     *
     * @return array<string, float>
     */
    public static function outflowComposition(Period $period): array
    {
        return CorporateMetrics::remember('admin_outflow_composition', $period, static function () use ($period): array {
            $outflows = self::outflows($period->from, $period->to);

            return array_filter([
                'Comisiones' => $outflows['commissions'],
                'Costo médico' => $outflows['medical_cost'],
                'Facturas de proveedores' => $outflows['supplier_invoices'],
            ], static fn (float $value): bool => $value > 0);
        });
    }

    /**
     * Cobranza esperada: cuotas pendientes cuya fecha de pago cae en los
     * próximos días, por semana. Foto a hoy.
     *
     * @return array{labels: list<string>, values: list<float>, total: float, count: int}
     */
    public static function upcomingCollections(): array
    {
        return CorporateMetrics::remember('admin_upcoming_collections_'.CarbonImmutable::today()->toDateString(), null, static function (): array {
            $from = CarbonImmutable::today();
            $to = $from->addDays(self::HORIZON_DAYS - 1);
            $query = CorporateFinanceMetrics::pendingCollectableQuery()
                ->whereBetween('collections.filter_next_payment_date', [$from->toDateString(), $to->toDateString()])
                ->toBase();

            $daily = CorporateMetrics::daily($query, 'collections.filter_next_payment_date', $from, $to, 'collections.total_amount');
            $weeks = [];

            foreach (array_chunk($daily, 7) as $index => $chunk) {
                $start = $from->addDays($index * 7);
                $end = $start->addDays(count($chunk) - 1);
                $weeks[Period::rangeLabel($start, $end)] = round(array_sum($chunk), 2);
            }

            return [
                'labels' => array_keys($weeks),
                'values' => array_values($weeks),
                'total' => round(array_sum($daily), 2),
                'count' => (int) (clone $query)->count(),
            ];
        });
    }

    /**
     * Facturas de proveedores pendientes de pago, a hoy.
     *
     * @return array{count: int, amount: float, ranking: array{rows: list<array{label: string, value: float, key: string}>, others: float, total: float}}
     */
    public static function payables(int $limit = 8): array
    {
        return CorporateMetrics::remember('admin_payables_'.$limit.'_'.CarbonImmutable::today()->toDateString(), null, static function () use ($limit): array {
            $pending = DB::table('operation_accounts_payables')
                ->where('payment_status', self::PAYABLE_PENDING);

            $rows = DashboardGrouping::by(
                (clone $pending),
                ['grp_supplier' => "COALESCE(NULLIF(TRIM(operation_accounts_payables.supplier_name), ''), 'Sin proveedor')"],
                ['amount' => 'operation_accounts_payables.invoice_amount'],
            )
                ->selectRaw('COALESCE(SUM(g.amount), 0) as amount')
                ->pluck('amount', 'grp_supplier')
                ->map(static fn (mixed $amount): float => round((float) $amount, 2))
                ->all();

            return [
                'count' => (clone $pending)->count(),
                'amount' => round((float) (clone $pending)->sum('invoice_amount'), 2),
                'ranking' => CorporateMetrics::ranked($rows, $limit),
            ];
        });
    }

    /**
     * Comisiones de ventas que aún no se marcan como pagadas, a hoy.
     *
     * @return array{amount: float, sales: int}
     */
    public static function pendingCommissions(): array
    {
        return CorporateMetrics::remember('admin_pending_commissions_'.CarbonImmutable::today()->toDateString(), null, static function (): array {
            $row = DB::table('commissions')
                ->join('sales', 'sales.id', '=', 'commissions.sale_id')
                ->where(static fn (Builder $query) => $query
                    ->whereNull('sales.status_payment_commission')
                    ->orWhere('sales.status_payment_commission', '<>', self::COMMISSION_PAID))
                ->selectRaw('COALESCE(SUM('.CorporateFinanceMetrics::commissionSumSql().'), 0) as amount, COUNT(DISTINCT sales.id) as sales')
                ->first();

            return ['amount' => round((float) ($row->amount ?? 0), 2), 'sales' => (int) ($row->sales ?? 0)];
        });
    }

    /**
     * @return array{labels: list<string>, current: list<float>, previous: list<float>, previous_labels: list<string>}
     */
    public static function commissionsTrend(Period $period): array
    {
        return CorporateMetrics::remember('admin_commissions_trend', $period, static fn (): array => CorporateMetrics::trend(
            $period,
            static fn (CarbonImmutable $from, CarbonImmutable $to): array => self::commissionsDaily($from, $to),
        ));
    }

    /**
     * Vouchers TDEV del período (fecha de emisión del reporte): cantidad,
     * monto de venta y estatus de pago.
     *
     * @return array{vouchers: int, amount: float, paid: int, pending: int, voided: int, ranking: array{rows: list<array{label: string, value: float, key: string}>, others: float, total: float}}
     */
    public static function tdev(Period $period): array
    {
        return CorporateMetrics::remember('admin_tdev', $period, static function () use ($period): array {
            $query = DB::table('tdev_reports')
                ->whereRaw("STR_TO_DATE(tdev_reports.fecha, '%d/%m/%Y') BETWEEN ? AND ?", [$period->from->toDateString(), $period->to->toDateString()]);

            $summary = (clone $query)
                ->selectRaw('COUNT(*) as vouchers')
                ->selectRaw("COALESCE(SUM(CASE WHEN LOWER(TRIM(estatus_pago)) <> 'anulado' THEN monto_pvp_precio_de_venta ELSE 0 END), 0) as amount")
                ->selectRaw("SUM(CASE WHEN LOWER(TRIM(estatus_pago)) = 'pagado' THEN 1 ELSE 0 END) as paid")
                ->selectRaw("SUM(CASE WHEN LOWER(TRIM(estatus_pago)) = 'pendiente' THEN 1 ELSE 0 END) as pending")
                ->selectRaw("SUM(CASE WHEN LOWER(TRIM(estatus_pago)) = 'anulado' THEN 1 ELSE 0 END) as voided")
                ->first();

            $byAgency = DashboardGrouping::by(
                (clone $query)->whereRaw("LOWER(TRIM(COALESCE(estatus_pago, ''))) <> 'anulado'"),
                ['grp_agency' => "COALESCE(NULLIF(TRIM(tdev_reports.agencia), ''), 'Sin agencia')"],
                ['amount' => 'tdev_reports.monto_pvp_precio_de_venta'],
            )
                ->selectRaw('COALESCE(SUM(g.amount), 0) as amount')
                ->pluck('amount', 'grp_agency')
                ->map(static fn (mixed $amount): float => round((float) $amount, 2))
                ->all();

            return [
                'vouchers' => (int) ($summary->vouchers ?? 0),
                'amount' => round((float) ($summary->amount ?? 0), 2),
                'paid' => (int) ($summary->paid ?? 0),
                'pending' => (int) ($summary->pending ?? 0),
                'voided' => (int) ($summary->voided ?? 0),
                'ranking' => CorporateMetrics::ranked($byAgency, 8),
            ];
        });
    }

    /**
     * Renovaciones que vencen en los próximos días (individuales y
     * corporativas), con su monto anual. Foto a hoy.
     *
     * @return array{count: int, amount: float, in_window: int}
     */
    public static function upcomingRenewals(): array
    {
        return CorporateMetrics::remember('admin_upcoming_renewals_'.CarbonImmutable::today()->toDateString(), null, static function (): array {
            $from = CarbonImmutable::today()->toDateString();
            $to = CarbonImmutable::today()->addDays(self::HORIZON_DAYS)->toDateString();
            $count = 0;
            $amount = 0.0;
            $inWindow = 0;

            foreach (['renovations', 'renovation_corporates'] as $table) {
                $row = DB::table($table)
                    ->whereBetween('date_renewal', [$from, $to])
                    ->selectRaw('COUNT(*) as total, COALESCE(SUM(subtotal_anual), 0) as amount')
                    ->first();
                $count += (int) ($row->total ?? 0);
                $amount += (float) ($row->amount ?? 0);
                $inWindow += DB::table($table)->where('status', self::RENEWAL_WINDOW)->count();
            }

            return ['count' => $count, 'amount' => round($amount, 2), 'in_window' => $inWindow];
        });
    }

    public static function invoicesBetween(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return DB::table('operation_accounts_payables')
            ->whereRaw('COALESCE(operation_accounts_payables.invoice_registration_date, DATE(operation_accounts_payables.created_at)) BETWEEN ? AND ?', [$from->toDateString(), $to->toDateString()]);
    }

    /**
     * @return list<float>
     */
    public static function outflowsDaily(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $commissions = self::commissionsDaily($from, $to);
        $medical = CorporateFinanceMetrics::costDaily($from, $to);
        $invoices = CorporateMetrics::daily(
            self::invoicesBetween($from, $to),
            'COALESCE(operation_accounts_payables.invoice_registration_date, DATE(operation_accounts_payables.created_at))',
            $from,
            $to,
            'operation_accounts_payables.invoice_amount',
        );

        return array_map(static fn (float $a, float $b, float $c): float => round($a + $b + $c, 2), $commissions, $medical, $invoices);
    }

    /**
     * @return list<float>
     */
    public static function commissionsDaily(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return CorporateMetrics::daily(CorporateFinanceMetrics::commissionsBetween($from, $to), 'commissions.created_at', $from, $to, CorporateFinanceMetrics::commissionSumSql());
    }
}
