<?php

declare(strict_types=1);

namespace App\Support\Metrics\Corporate;

use App\Support\Operations\Dashboard\DashboardGrouping;
use App\Support\Operations\Dashboard\OperationsDashboardAnalytics;
use App\Support\Operations\Dashboard\OperationsDashboardPeriod as Period;
use Carbon\CarbonImmutable;

/**
 * El mapa de Venezuela del panel: una capa por métrica, todas por estado, para
 * cambiar de capa en el navegador sin volver al servidor.
 *
 * - Ingresos: el estado de la afiliación pagada (individual: `state_id_ti`;
 *   corporativa: `state_id`), por el código de afiliación de la venta.
 * - Afiliaciones activas y red comercial: foto a hoy (sin período anterior).
 * - Servicios médicos: el estado donde se prestó el servicio.
 */
final class CorporateMapMetrics
{
    public const LAYERS = ['income', 'affiliations', 'network', 'services'];

    /**
     * @return array<string, array{label: string, short: string, money: bool, snapshot: bool, hint: string, values: array<string, float>, previous: array<string, float>|null, total: float, unknown: float}>
     */
    public static function layers(Period $period): array
    {
        return CorporateMetrics::remember('map_layers', $period, static function () use ($period): array {
            $services = self::servicesByState($period->from, $period->to);

            return [
                'income' => self::layer('Ingresos', 'Ingresos', true, false, 'Pagos registrados en el período, por el estado de la afiliación.', self::incomeByState($period->from, $period->to), $period->compare ? self::incomeByState($period->previousFrom, $period->previousTo) : null),
                'affiliations' => self::layer('Afiliaciones activas', 'Afiliaciones', false, true, 'Afiliaciones individuales y corporativas activas hoy.', CorporatePortfolioMetrics::activeByState(), null),
                'network' => self::layer('Red comercial', 'Agentes y agencias', false, true, 'Agentes y agencias activos hoy, por su estado de registro.', CorporateCommercialMetrics::networkByState(), null),
                'services' => self::layer('Servicios médicos', 'Servicios', false, false, 'Servicios de operaciones prestados en el período.', $services, $period->compare ? self::servicesByState($period->previousFrom, $period->previousTo) : null),
            ];
        });
    }

    /**
     * @return array<string, float>
     */
    public static function incomeByState(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = DashboardGrouping::by(
            CorporateFinanceMetrics::salesBetween($from, $to)
                ->leftJoin('affiliations', 'affiliations.code', '=', 'sales.affiliation_code')
                ->leftJoin('affiliation_corporates', 'affiliation_corporates.code', '=', 'sales.affiliation_code'),
            ['grp_state' => 'COALESCE(affiliations.state_id_ti, affiliation_corporates.state_id, 0)'],
            ['amount' => 'sales.total_amount'],
        )
            ->selectRaw('COALESCE(SUM(g.amount), 0) as amount')
            ->pluck('amount', 'grp_state');

        return CorporateGeography::byGeoKeyFromIds($rows);
    }

    /**
     * @return array<string, float>
     */
    public static function servicesByState(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $byName = DashboardGrouping::by(OperationsDashboardAnalytics::inPeriod($from, $to), ['grp_state' => "COALESCE(NULLIF(TRIM(state), ''), '')"])
            ->selectRaw('COUNT(*) as total')
            ->pluck('total', 'grp_state');

        return CorporateGeography::byGeoKeyFromNames($byName);
    }

    /**
     * Ids del catálogo `states` que caen en una clave del mapa (para el detalle).
     *
     * @return list<int>
     */
    public static function stateIdsFor(string $geoKey): array
    {
        return array_keys(array_filter(CorporateGeography::stateIds(), static fn (string $key): bool => $key === $geoKey));
    }

    /**
     * @param  array<string, float>  $values
     * @param  array<string, float>|null  $previous
     * @return array{label: string, short: string, money: bool, snapshot: bool, hint: string, values: array<string, float>, previous: array<string, float>|null, total: float, unknown: float}
     */
    private static function layer(string $label, string $short, bool $money, bool $snapshot, string $hint, array $values, ?array $previous): array
    {
        $unknown = (float) ($values[CorporateGeography::UNKNOWN] ?? 0);
        unset($values[CorporateGeography::UNKNOWN]);

        if ($previous !== null) {
            unset($previous[CorporateGeography::UNKNOWN]);
        }

        arsort($values);

        return [
            'label' => $label,
            'short' => $short,
            'money' => $money,
            'snapshot' => $snapshot,
            'hint' => $hint,
            'values' => $values,
            'previous' => $previous,
            'total' => round(array_sum($values) + $unknown, 2),
            'unknown' => round($unknown, 2),
        ];
    }
}
