<?php

declare(strict_types=1);

namespace App\Support\Metrics\Corporate;

use App\Support\Operations\Dashboard\OperationsDashboardAnalytics;
use App\Support\Operations\Dashboard\OperationsDashboardPeriod as Period;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Cartera: afiliaciones y vidas cubiertas.
 *
 * - Activas y vidas: foto a hoy (afiliaciones ACTIVA; afiliados ACTIVO).
 * - Altas: afiliaciones por su fecha de emisión (`activated_at` dd/mm/aaaa o creación).
 * - Bajas: afiliaciones con una entrada de exclusión/inactivación/anulación en su
 *   bitácora de estatus en el período (cada una cuenta una vez).
 * - Renovaciones: aceptaciones del historial en el período. Tasa = renovadas ÷
 *   (renovadas + vencidas sin renovar con fecha en el período), igual que el
 *   Informe administrativo.
 */
final class CorporatePortfolioMetrics
{
    public const ACTIVE = 'ACTIVA';

    /**
     * [tabla, tabla de afiliados, columna de estado de la afiliación, tipo].
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string}>
     */
    public const SOURCES = [
        ['affiliations', 'affiliates', 'state_id_ti', 'Individual'],
        ['affiliation_corporates', 'affiliate_corporates', 'state_id', 'Corporativa'],
    ];

    /**
     * @return array{active: int, active_individual: int, active_corporate: int, lives: int}
     */
    public static function snapshot(): array
    {
        return CorporateMetrics::remember('portfolio_snapshot_'.CarbonImmutable::today()->toDateString(), null, static function (): array {
            $individual = DB::table('affiliations')->where('status', self::ACTIVE)->count();
            $corporate = DB::table('affiliation_corporates')->where('status', self::ACTIVE)->count();
            $lives = DB::table('affiliates')->where('status', 'ACTIVO')->count()
                + DB::table('affiliate_corporates')->where('status', 'ACTIVO')->count();

            return [
                'active' => $individual + $corporate,
                'active_individual' => $individual,
                'active_corporate' => $corporate,
                'lives' => $lives,
            ];
        });
    }

    /**
     * @return array{new: int, dropouts: int, net: int, renewed: int, expired: int, renewal_rate: float|null}
     */
    public static function movement(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $new = self::newCount($from, $to);
        $dropouts = self::dropoutsCount($from, $to);
        $renewed = self::renewedCount($from, $to);
        $expired = self::expiredCount($from, $to);

        return [
            'new' => $new,
            'dropouts' => $dropouts,
            'net' => $new - $dropouts,
            'renewed' => $renewed,
            'expired' => $expired,
            'renewal_rate' => $renewed + $expired > 0 ? round($renewed * 100 / ($renewed + $expired), 1) : null,
        ];
    }

    /**
     * @return array{current: array<string, int|float|null>, previous: array<string, int|float|null>, sparklines: array<string, list<float>>, snapshot: array<string, int>}
     */
    public static function kpis(Period $period): array
    {
        return CorporateMetrics::remember('portfolio_kpis', $period, static fn (): array => [
            'current' => self::movement($period->from, $period->to),
            'previous' => self::movement($period->previousFrom, $period->previousTo),
            'sparklines' => [
                'new' => self::newDaily($period->from, $period->to),
                'dropouts' => self::dropoutsDaily($period->from, $period->to),
            ],
            'snapshot' => self::snapshot(),
        ]);
    }

    /**
     * Altas y bajas por día/semana/mes del período.
     *
     * @return array{labels: list<string>, new: list<float>, dropouts: list<float>}
     */
    public static function newVsDropouts(Period $period): array
    {
        return CorporateMetrics::remember('portfolio_new_vs_dropouts', $period, static function () use ($period): array {
            $granularity = $period->granularity();
            $new = CorporateMetrics::bucket(self::newDaily($period->from, $period->to), $period->from, $granularity);
            $dropouts = CorporateMetrics::bucket(self::dropoutsDaily($period->from, $period->to), $period->from, $granularity);

            return ['labels' => $new['labels'], 'new' => $new['values'], 'dropouts' => $dropouts['values']];
        });
    }

    /**
     * Afiliaciones por estatus actual (individuales + corporativas).
     *
     * @return array<string, int>
     */
    public static function byStatus(): array
    {
        return CorporateMetrics::remember('portfolio_by_status_'.CarbonImmutable::today()->toDateString(), null, static function (): array {
            $counts = [];

            foreach (self::SOURCES as [$table]) {
                foreach (DB::table($table)->selectRaw("COALESCE(NULLIF(UPPER(TRIM(status)), ''), 'SIN ESTATUS') as grp_status, COUNT(*) as total")->groupBy('grp_status')->get() as $row) {
                    $counts[(string) $row->grp_status] = ($counts[(string) $row->grp_status] ?? 0) + (int) $row->total;
                }
            }

            $ordered = [];

            foreach ([self::ACTIVE, 'PRE-APROBADA', 'EXCLUIDO'] as $status) {
                if (isset($counts[$status])) {
                    $ordered[$status] = $counts[$status];
                    unset($counts[$status]);
                }
            }

            arsort($counts);

            return $ordered + $counts;
        });
    }

    /**
     * Vidas activas por plan.
     *
     * @return array<string, int>
     */
    public static function livesByPlan(): array
    {
        return CorporateMetrics::remember('portfolio_lives_plan_'.CarbonImmutable::today()->toDateString(), null, static function (): array {
            $counts = [];

            foreach ([['affiliates', 'affiliates.plan_id'], ['affiliate_corporates', 'affiliate_corporates.plan_id']] as [$table, $planColumn]) {
                $rows = DB::table($table)
                    ->leftJoin('plans', 'plans.id', '=', $planColumn)
                    ->where("{$table}.status", 'ACTIVO')
                    ->selectRaw("UPPER(COALESCE(NULLIF(TRIM(plans.description), ''), 'SIN PLAN')) as grp_label, COUNT(*) as total")
                    ->groupBy('grp_label')
                    ->get();

                foreach ($rows as $row) {
                    $counts[(string) $row->grp_label] = ($counts[(string) $row->grp_label] ?? 0) + (int) $row->total;
                }
            }

            arsort($counts);

            return $counts;
        });
    }

    /**
     * Vidas activas por franja de edad.
     *
     * @return array<string, int>
     */
    public static function livesByAge(): array
    {
        return CorporateMetrics::remember('portfolio_lives_age_'.CarbonImmutable::today()->toDateString(), null, static function (): array {
            $bands = array_fill_keys(array_keys(OperationsDashboardAnalytics::AGE_BANDS), 0);
            $case = 'CASE';

            foreach (OperationsDashboardAnalytics::AGE_BANDS as $label => [$min, $max]) {
                $case .= " WHEN age BETWEEN {$min} AND {$max} THEN '{$label}'";
            }

            $case .= ' END';

            foreach (['affiliates', 'affiliate_corporates'] as $table) {
                foreach (DB::table($table)->where('status', 'ACTIVO')->whereNotNull('age')->selectRaw($case.' as grp_band, COUNT(*) as total')->groupBy('grp_band')->get() as $row) {
                    if ($row->grp_band !== null) {
                        $bands[(string) $row->grp_band] += (int) $row->total;
                    }
                }
            }

            return $bands;
        });
    }

    /**
     * Afiliaciones activas por estado (clave del mapa).
     *
     * @return array<string, float>
     */
    public static function activeByState(): array
    {
        return CorporateMetrics::remember('portfolio_active_state_'.CarbonImmutable::today()->toDateString(), null, static function (): array {
            $out = [];

            foreach (self::SOURCES as [$table, , $stateColumn]) {
                $rows = DB::table($table)->where('status', self::ACTIVE)->selectRaw("{$stateColumn} as state_id, COUNT(*) as total")->groupBy($stateColumn)->pluck('total', 'state_id');

                foreach (CorporateGeography::byGeoKeyFromIds($rows) as $key => $value) {
                    $out[$key] = ($out[$key] ?? 0) + $value;
                }
            }

            arsort($out);

            return $out;
        });
    }

    /**
     * Expresión de la fecha de emisión de una afiliación.
     */
    public static function emissionSql(string $table): string
    {
        return "COALESCE(STR_TO_DATE(NULLIF(TRIM({$table}.activated_at), ''), '%d/%m/%Y'), DATE({$table}.created_at))";
    }

    /**
     * Entradas de baja de la bitácora de estatus, una por tipo de afiliación.
     *
     * @return list<array{0: Builder, 1: string}> [consulta, columna de la afiliación]
     */
    public static function dropoutLogs(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $out = [];

        foreach ([['status_log_affiliations', 'affiliation_id'], ['status_log_affiliation_corporates', 'affiliation_corporate_id']] as [$logTable, $foreignKey]) {
            $out[] = [
                DB::table($logTable)
                    ->whereBetween("{$logTable}.created_at", CorporateMetrics::range($from, $to))
                    ->where(function (Builder $query) use ($logTable): void {
                        $query->where("{$logTable}.action", 'like', '%EXCLU%')
                            ->orWhere("{$logTable}.action", 'like', '%INACTIV%')
                            ->orWhere("{$logTable}.action", 'like', '%ANULAD%');
                    }),
                "{$logTable}.{$foreignKey}",
                $logTable,
            ];
        }

        return $out;
    }

    private static function newCount(CarbonImmutable $from, CarbonImmutable $to): int
    {
        $total = 0;

        foreach (self::SOURCES as [$table]) {
            $total += DB::table($table)->whereRaw(self::emissionSql($table).' BETWEEN ? AND ?', [$from->toDateString(), $to->toDateString()])->count();
        }

        return $total;
    }

    /**
     * @return list<float>
     */
    private static function newDaily(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $series = array_fill(0, (int) $from->diffInDays($to) + 1, 0.0);

        foreach (self::SOURCES as [$table]) {
            $daily = CorporateMetrics::daily(
                DB::table($table)->whereRaw(self::emissionSql($table).' BETWEEN ? AND ?', [$from->toDateString(), $to->toDateString()]),
                self::emissionSql($table),
                $from,
                $to,
            );

            foreach ($daily as $index => $value) {
                $series[$index] += $value;
            }
        }

        return $series;
    }

    private static function dropoutsCount(CarbonImmutable $from, CarbonImmutable $to): int
    {
        $total = 0;

        foreach (self::dropoutLogs($from, $to) as [$query, $column]) {
            $total += (int) $query->selectRaw("COUNT(DISTINCT {$column}) as total")->value('total');
        }

        return $total;
    }

    /**
     * Cada afiliación cuenta una vez, el día de su última baja del rango.
     *
     * @return list<float>
     */
    private static function dropoutsDaily(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $series = array_fill(0, (int) $from->diffInDays($to) + 1, 0.0);

        foreach (self::dropoutLogs($from, $to) as [$query, $column, $table]) {
            $latest = $query->selectRaw("{$column} as ref, MAX({$table}.created_at) as dropped_at")->groupBy($column);
            $daily = CorporateMetrics::daily(DB::query()->fromSub($latest, 'latest'), 'latest.dropped_at', $from, $to);

            foreach ($daily as $index => $value) {
                $series[$index] += $value;
            }
        }

        return $series;
    }

    private static function renewedCount(CarbonImmutable $from, CarbonImmutable $to): int
    {
        $total = 0;

        foreach (['affiliation_renovation_histories', 'affiliation_corporate_renovation_histories'] as $table) {
            $total += (int) DB::table($table)->whereBetween('accepted_at', CorporateMetrics::range($from, $to))->distinct()->count('code_affiliation');
        }

        return $total;
    }

    private static function expiredCount(CarbonImmutable $from, CarbonImmutable $to): int
    {
        $today = CarbonImmutable::today()->toDateString();
        $total = 0;

        foreach (['renovations', 'renovation_corporates'] as $table) {
            $total += (int) DB::table($table)
                ->whereDate('date_renewal', '<', $today)
                ->whereBetween('date_renewal', [$from->toDateString(), $to->toDateString()])
                ->distinct()
                ->count('code_affiliation');
        }

        return $total;
    }
}
