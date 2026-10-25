<?php

declare(strict_types=1);

namespace App\Support\Metrics\Corporate;

use App\Support\Operations\Dashboard\DashboardGrouping;
use App\Support\Operations\Dashboard\OperationsDashboardPeriod as Period;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Comercial: cotizaciones, conversión y red de ventas.
 *
 * - Cotizaciones: individuales y corporativas emitidas en el período.
 * - Conversión: cotizaciones individuales del período que ya están EJECUTADAS
 *   (se convirtieron en afiliación) sobre las emitidas. Las corporativas no
 *   registran ese paso, por eso la tasa es de las individuales.
 * - Red: agentes y agencias registrados en el período; activos a hoy.
 */
final class CorporateCommercialMetrics
{
    public const EXECUTED = 'EJECUTADA';

    public const VOIDED = 'ANULADA';

    /** Estados que significan que la cotización avanzó (aprobada o convertida). */
    public const ADVANCED = ['APROBADA', 'PRE-APROBADA', 'EJECUTADA'];

    /**
     * @return array{quotes: int, individual_quotes: int, corporate_quotes: int, executed: int, conversion: float|null, new_agents: int, new_agencies: int}
     */
    public static function summary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $individual = self::quotesBetween('individual_quotes', $from, $to)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN UPPER(TRIM(status)) = ? THEN 1 ELSE 0 END) as executed', [self::EXECUTED])
            ->first();
        $corporate = self::quotesBetween('corporate_quotes', $from, $to)->count();
        $individualTotal = (int) ($individual->total ?? 0);
        $executed = (int) ($individual->executed ?? 0);

        return [
            'quotes' => $individualTotal + $corporate,
            'individual_quotes' => $individualTotal,
            'corporate_quotes' => $corporate,
            'executed' => $executed,
            'conversion' => $individualTotal > 0 ? round($executed * 100 / $individualTotal, 1) : null,
            'new_agents' => DB::table('agents')->whereBetween('created_at', CorporateMetrics::range($from, $to))->count(),
            'new_agencies' => DB::table('agencies')->whereBetween('created_at', CorporateMetrics::range($from, $to))->count(),
        ];
    }

    /**
     * @return array{current: array<string, int|float|null>, previous: array<string, int|float|null>, sparklines: array<string, list<float>>, network: array{agents: int, agencies: int}}
     */
    public static function kpis(Period $period): array
    {
        return CorporateMetrics::remember('commercial_kpis', $period, static fn (): array => [
            'current' => self::summary($period->from, $period->to),
            'previous' => self::summary($period->previousFrom, $period->previousTo),
            'sparklines' => [
                'quotes' => self::quotesDaily($period->from, $period->to),
                'agents' => CorporateMetrics::daily(DB::table('agents')->whereBetween('created_at', CorporateMetrics::range($period->from, $period->to)), 'agents.created_at', $period->from, $period->to),
            ],
            'network' => self::network(),
        ]);
    }

    /**
     * @return array{agents: int, agencies: int}
     */
    public static function network(): array
    {
        return CorporateMetrics::remember('commercial_network_'.CarbonImmutable::today()->toDateString(), null, static fn (): array => [
            'agents' => DB::table('agents')->where('status', 'ACTIVO')->count(),
            'agencies' => DB::table('agencies')->where('status', 'ACTIVO')->count(),
        ]);
    }

    /**
     * @return array{labels: list<string>, current: list<float>, previous: list<float>, previous_labels: list<string>}
     */
    public static function quotesTrend(Period $period): array
    {
        return CorporateMetrics::remember('commercial_quotes_trend', $period, static fn (): array => CorporateMetrics::trend(
            $period,
            static fn (CarbonImmutable $from, CarbonImmutable $to): array => self::quotesDaily($from, $to),
        ));
    }

    /**
     * Embudo de las cotizaciones individuales del período.
     *
     * @return list<array{label: string, value: int, key: string, hint: string}>
     */
    public static function funnel(Period $period): array
    {
        return CorporateMetrics::remember('commercial_funnel', $period, static function () use ($period): array {
            $placeholders = implode(', ', array_fill(0, count(self::ADVANCED), '?'));
            $row = self::quotesBetween('individual_quotes', $period->from, $period->to)
                ->selectRaw('COUNT(*) as total')
                ->selectRaw("SUM(CASE WHEN UPPER(TRIM(status)) IN ({$placeholders}) THEN 1 ELSE 0 END) as advanced", self::ADVANCED)
                ->selectRaw('SUM(CASE WHEN UPPER(TRIM(status)) = ? THEN 1 ELSE 0 END) as executed', [self::EXECUTED])
                ->first();

            return [
                ['label' => 'Cotizaciones emitidas', 'value' => (int) ($row->total ?? 0), 'key' => 'all', 'hint' => 'Cotizaciones individuales creadas en el período.'],
                ['label' => 'Aprobadas o en curso', 'value' => (int) ($row->advanced ?? 0), 'key' => 'advanced', 'hint' => 'Pre-aprobadas, aprobadas o ya convertidas.'],
                ['label' => 'Convertidas en afiliación', 'value' => (int) ($row->executed ?? 0), 'key' => self::EXECUTED, 'hint' => 'Cotizaciones EJECUTADAS: ya son afiliaciones.'],
            ];
        });
    }

    /**
     * Ventas (monto cobrado) por agente.
     *
     * @return array{rows: list<array{label: string, value: float, key: string}>, others: float, total: float}
     */
    public static function salesByAgent(Period $period, int $limit = 10): array
    {
        return CorporateMetrics::remember('commercial_sales_agent_'.$limit, $period, static function () use ($period, $limit): array {
            $rows = DashboardGrouping::by(
                CorporateFinanceMetrics::salesBetween($period->from, $period->to)->leftJoin('agents', 'agents.id', '=', 'sales.agent_id'),
                ['grp_id' => 'COALESCE(sales.agent_id, 0)'],
                ['agent_name' => 'agents.name', 'amount' => 'sales.total_amount'],
            )
                ->selectRaw('MAX(g.agent_name) as name, COALESCE(SUM(g.amount), 0) as amount')
                ->orderByDesc('amount')
                ->get();

            $mapped = $rows->map(static fn (object $row): array => [
                'label' => filled($row->name) ? (string) $row->name : 'Venta directa (sin agente)',
                'value' => round((float) $row->amount, 2),
                'key' => (string) $row->grp_id,
            ]);

            return [
                'rows' => $mapped->take($limit)->values()->all(),
                'others' => round((float) $mapped->slice($limit)->sum('value'), 2),
                'total' => round((float) $mapped->sum('value'), 2),
            ];
        });
    }

    /**
     * Planes más vendidos (cantidad de pagos de afiliación individual por plan).
     *
     * @return array<string, int>
     */
    public static function plansSold(Period $period): array
    {
        return CorporateMetrics::remember('commercial_plans_sold', $period, static function () use ($period): array {
            $rows = DashboardGrouping::by(
                CorporateFinanceMetrics::salesBetween($period->from, $period->to)
                    ->leftJoin('plans', 'plans.id', '=', 'sales.plan_id')
                    ->whereRaw("UPPER(sales.type) NOT LIKE '%CORPORATIV%'"),
                ['grp_label' => "UPPER(COALESCE(NULLIF(TRIM(plans.description), ''), 'SIN PLAN'))"],
                ['affiliation_code' => 'sales.affiliation_code'],
            )
                ->selectRaw('COUNT(DISTINCT g.affiliation_code) as total')
                ->pluck('total', 'grp_label')
                ->map(static fn (mixed $total): int => (int) $total)
                ->all();

            arsort($rows);

            return $rows;
        });
    }

    /**
     * Agentes activos por estado (clave del mapa), a hoy.
     *
     * @return array<string, float>
     */
    public static function networkByState(): array
    {
        return CorporateMetrics::remember('commercial_network_state_'.CarbonImmutable::today()->toDateString(), null, static function (): array {
            $agents = DB::table('agents')->where('status', 'ACTIVO')->selectRaw('state_id, COUNT(*) as total')->groupBy('state_id')->pluck('total', 'state_id');
            $agencies = DB::table('agencies')->where('status', 'ACTIVO')->selectRaw('state_id, COUNT(*) as total')->groupBy('state_id')->pluck('total', 'state_id');

            $out = CorporateGeography::byGeoKeyFromIds($agents);

            foreach (CorporateGeography::byGeoKeyFromIds($agencies) as $key => $value) {
                $out[$key] = ($out[$key] ?? 0) + $value;
            }

            arsort($out);

            return $out;
        });
    }

    public static function quotesBetween(string $table, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return DB::table($table)->whereBetween("{$table}.created_at", CorporateMetrics::range($from, $to));
    }

    /**
     * @return list<float>
     */
    public static function quotesDaily(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $individual = CorporateMetrics::daily(self::quotesBetween('individual_quotes', $from, $to), 'individual_quotes.created_at', $from, $to);
        $corporate = CorporateMetrics::daily(self::quotesBetween('corporate_quotes', $from, $to), 'corporate_quotes.created_at', $from, $to);

        return array_map(static fn (float $a, float $b): float => $a + $b, $individual, $corporate);
    }
}
