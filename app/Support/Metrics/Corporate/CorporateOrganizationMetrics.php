<?php

declare(strict_types=1);

namespace App\Support\Metrics\Corporate;

use App\Support\Operations\Dashboard\OperationsDashboardPeriod as Period;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Organización: el helpdesk como termómetro de la gestión interna (sin RRHH ni
 * nómina, por decisión de negocio).
 *
 * - Creados: tickets abiertos en el período. Resueltos: con fecha de resolución
 *   en el período. Abiertos: foto a hoy.
 * - SLA incumplido: tickets del período marcados fuera de SLA.
 * - Horas de resolución: promedio entre creación y resolución de los resueltos.
 *
 * Casi ningún ticket histórico guarda `resolved_at`: para los TERMINADOS sin esa
 * fecha se toma la última actualización ({@see self::RESOLVED_SQL}). Y la mitad
 * guarda al creador solo por nombre (`created_by`): el área se busca por ahí.
 */
final class CorporateOrganizationMetrics
{
    public const CLOSED_STATUSES = ['TERMINADO', 'CANCELADO'];

    /** Momento de resolución: el registrado o, si es TERMINADO sin él, su última actualización. */
    public const RESOLVED_SQL = "COALESCE(help_desks.resolved_at, CASE WHEN UPPER(TRIM(help_desks.status)) = 'TERMINADO' THEN help_desks.updated_at END)";

    /** Tipos de ticket (código interno → texto). */
    public const TYPES = [
        'incident' => 'Incidente',
        'problem' => 'Problema',
        'feature_request' => 'Nueva funcionalidad',
        'change_request' => 'Solicitud de cambio',
        'service_request' => 'Solicitud de servicio',
    ];

    /**
     * @return array{created: int, resolved: int, sla_breached: int, sla_rate: float|null, resolution_hours: float|null}
     */
    public static function summary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $created = self::createdBetween($from, $to)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN sla_breached_at IS NOT NULL THEN 1 ELSE 0 END) as breached')
            ->first();
        $resolved = self::resolvedBetween($from, $to)
            ->selectRaw('COUNT(*) as total, AVG(TIMESTAMPDIFF(MINUTE, help_desks.created_at, '.self::RESOLVED_SQL.')) / 60 as hours')
            ->first();

        $createdTotal = (int) ($created->total ?? 0);
        $breached = (int) ($created->breached ?? 0);

        return [
            'created' => $createdTotal,
            'resolved' => (int) ($resolved->total ?? 0),
            'sla_breached' => $breached,
            'sla_rate' => $createdTotal > 0 ? round(($createdTotal - $breached) * 100 / $createdTotal, 1) : null,
            'resolution_hours' => $resolved->hours !== null ? round((float) $resolved->hours, 1) : null,
        ];
    }

    /**
     * @return array{current: array<string, int|float|null>, previous: array<string, int|float|null>, sparklines: array<string, list<float>>, open: int}
     */
    public static function kpis(Period $period): array
    {
        return CorporateMetrics::remember('organization_kpis', $period, static fn (): array => [
            'current' => self::summary($period->from, $period->to),
            'previous' => self::summary($period->previousFrom, $period->previousTo),
            'sparklines' => [
                'created' => self::createdDaily($period->from, $period->to),
                'resolved' => self::resolvedDaily($period->from, $period->to),
            ],
            'open' => DB::table('help_desks')->whereNotIn(DB::raw('UPPER(TRIM(status))'), self::CLOSED_STATUSES)->count(),
        ]);
    }

    /**
     * @return array{labels: list<string>, created: list<float>, resolved: list<float>}
     */
    public static function createdVsResolved(Period $period): array
    {
        return CorporateMetrics::remember('organization_created_vs_resolved', $period, static function () use ($period): array {
            $granularity = $period->granularity();
            $created = CorporateMetrics::bucket(self::createdDaily($period->from, $period->to), $period->from, $granularity);
            $resolved = CorporateMetrics::bucket(self::resolvedDaily($period->from, $period->to), $period->from, $granularity);

            return ['labels' => $created['labels'], 'created' => $created['values'], 'resolved' => $resolved['values']];
        });
    }

    /**
     * Tickets del período por estatus actual.
     *
     * @return array<string, int>
     */
    public static function byStatus(Period $period): array
    {
        return CorporateMetrics::remember('organization_by_status', $period, static fn (): array => self::grouped($period, "COALESCE(NULLIF(UPPER(TRIM(status)), ''), 'SIN ESTATUS')"));
    }

    /**
     * @return array<string, int>
     */
    public static function byPriority(Period $period): array
    {
        return CorporateMetrics::remember('organization_by_priority', $period, static function () use ($period): array {
            $counts = self::grouped($period, "COALESCE(NULLIF(UPPER(TRIM(priority)), ''), 'SIN PRIORIDAD')");
            $ordered = [];

            foreach (['ALTA', 'MEDIA', 'BAJA'] as $priority) {
                if (isset($counts[$priority])) {
                    $ordered[$priority] = $counts[$priority];
                    unset($counts[$priority]);
                }
            }

            return $ordered + $counts;
        });
    }

    /**
     * @return array<string, int>
     */
    public static function byType(Period $period): array
    {
        return CorporateMetrics::remember('organization_by_type', $period, static function () use ($period): array {
            $out = [];

            foreach (self::grouped($period, "COALESCE(NULLIF(TRIM(ticket_type), ''), 'sin_tipo')") as $type => $total) {
                $label = self::TYPES[$type] ?? 'Sin clasificar';
                $out[$label] = ($out[$label] ?? 0) + $total;
            }

            arsort($out);

            return $out;
        });
    }

    /**
     * Tickets del período por área de quien los creó (su primer departamento).
     *
     * @return array<string, int>
     */
    public static function byArea(Period $period): array
    {
        return CorporateMetrics::remember('organization_by_area', $period, static function () use ($period): array {
            $rows = self::createdBetween($period->from, $period->to)
                ->leftJoin('users as by_id', 'by_id.id', '=', 'help_desks.created_by_user_id')
                ->leftJoinSub(
                    DB::table('users')->selectRaw('name, MAX(departament) as departament')->groupBy('name'),
                    'by_name',
                    static fn ($join) => $join->on('by_name.name', '=', 'help_desks.created_by')->whereNull('help_desks.created_by_user_id'),
                )
                ->selectRaw('COALESCE(by_id.departament, by_name.departament) as departament, COUNT(*) as total')
                ->groupByRaw('COALESCE(by_id.departament, by_name.departament)')
                ->get();

            $out = [];

            foreach ($rows as $row) {
                $departments = json_decode((string) $row->departament, true);
                $department = is_array($departments) ? collect($departments)->reject(fn ($value): bool => $value === 'SUPERADMIN')->first() : null;
                $label = filled($department) ? ucfirst(mb_strtolower((string) $department)) : 'Sin área';
                $out[$label] = ($out[$label] ?? 0) + (int) $row->total;
            }

            arsort($out);

            return $out;
        });
    }

    public static function resolvedBetween(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return DB::table('help_desks')->whereRaw(self::RESOLVED_SQL.' BETWEEN ? AND ?', CorporateMetrics::range($from, $to));
    }

    public static function createdBetween(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return DB::table('help_desks')->whereBetween('help_desks.created_at', CorporateMetrics::range($from, $to));
    }

    /**
     * @return array<string, int>
     */
    private static function grouped(Period $period, string $expression): array
    {
        $counts = self::createdBetween($period->from, $period->to)
            ->selectRaw($expression.' as label, COUNT(*) as total')
            ->groupByRaw($expression)
            ->pluck('total', 'label')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();

        arsort($counts);

        return $counts;
    }

    /**
     * @return list<float>
     */
    private static function createdDaily(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return CorporateMetrics::daily(self::createdBetween($from, $to), 'help_desks.created_at', $from, $to);
    }

    /**
     * @return list<float>
     */
    private static function resolvedDaily(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return CorporateMetrics::daily(self::resolvedBetween($from, $to), self::RESOLVED_SQL, $from, $to);
    }
}
