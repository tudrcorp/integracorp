<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\ProviderNetwork;

use App\Filament\Operations\Widgets\Dashboard\OperationsKpiTiles;
use App\Support\Operations\Providers\ProviderNetworkAnalytics;

/**
 * Indicadores de la red de proveedores. La red es foto a hoy; las altas son del período.
 */
class ProviderNetworkKpiTiles extends OperationsKpiTiles
{
    protected static ?int $sort = 1;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $period = $this->period();
        $snapshot = ProviderNetworkAnalytics::snapshot();
        $productivity = ProviderNetworkAnalytics::productivity($period);
        $totals = $snapshot['totals'];
        $active = array_sum($snapshot['active']);
        $withLetter = array_sum($snapshot['letters']['with']);
        $letterBase = $withLetter + array_sum($snapshot['letters']['without']);
        $abroad = array_sum(array_map('array_sum', $snapshot['abroad']));
        $preferential = static fn (string $type): int => (int) (($snapshot['agreements']['PREFERENCIAL BAJO COSTO'][$type] ?? 0) + ($snapshot['agreements']['PREFERENCIAL GEOGRAFICO'][$type] ?? 0));

        $tiles = [
            self::tile('total', 'Proveedores en la red', $snapshot['total'], $snapshot['total'], 'heroicon-o-building-office-2', 'Jurídicos, naturales y aliados corporativos vigentes (sin los eliminados).', [], snapshot: true),
            self::tile('juridico', 'Jurídicos', $totals['juridico'], $totals['juridico'], 'heroicon-o-building-office', 'Clínicas, centros y demás proveedores con RIF.', [], snapshot: true,
                note: 'General '.self::number($snapshot['agreements']['GENERAL']['juridico'] ?? 0).' · Preferencial '.self::number($preferential('juridico'))),
            self::tile('natural', 'Naturales', $totals['natural'], $totals['natural'], 'heroicon-o-user', 'Médicos, enfermeros y demás profesionales independientes.', [], snapshot: true,
                note: 'General '.self::number($snapshot['agreements']['GENERAL']['natural'] ?? 0).' · Preferencial '.self::number($preferential('natural'))),
            self::tile('aliado', 'Aliados corporativos', $totals['aliado'], $totals['aliado'], 'heroicon-o-briefcase', 'Empresas aliadas que prestan servicios a TDG.', [], snapshot: true,
                note: $abroad > 0 ? self::number($abroad).' fuera de Venezuela' : null),
            self::tile('active', 'Con convenio activo', $snapshot['total'] > 0 ? $active * 100 / $snapshot['total'] : 0, 0, 'heroicon-o-check-badge', 'Proveedores en estatus Activo afiliado, Afiliado o Activo, sobre el total de la red.', [], isPercent: true, snapshot: true,
                note: self::number($active).' proveedores'),
            self::tile('letters', 'Cartas de aceptación', $letterBase > 0 ? $withLetter * 100 / $letterBase : 0, 0, 'heroicon-o-document-check', 'Jurídicos y naturales con carta de aceptación cargada. Los aliados no manejan carta.', [], isPercent: true, snapshot: true,
                note: self::number(array_sum($snapshot['letters']['active_without'])).' con convenio activo y sin carta'),
            self::tile('created', 'Altas en el período', $productivity['totals']['created'], $productivity['previous']['created'], 'heroicon-o-plus-circle', 'Proveedores registrados en el período elegido (los tres tipos).', array_values($productivity['trend'])),
            self::tile('won', 'Convenios logrados', $productivity['totals']['won'], $productivity['previous']['won'], 'heroicon-o-hand-thumb-up', 'Cambios de estatus a Activo afiliado, Afiliado o Activo en el período. Se registran desde '.($productivity['since'] ?? 'hoy').'.', []),
        ];

        return [
            'tiles' => $tiles,
            'compare' => $period->compare,
            'previousLabel' => $period->previousLabel(),
            'periodKey' => $period->cacheKey(),
            'tilesGrid' => 'md:grid-cols-4',
        ];
    }
}
