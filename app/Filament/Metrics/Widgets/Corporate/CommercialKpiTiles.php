<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateCommercialMetrics;

/**
 * Comercial: cotizaciones, conversión y crecimiento de la red de ventas.
 */
class CommercialKpiTiles extends CorporateKpiTiles
{
    protected function tiles(): array
    {
        $data = CorporateCommercialMetrics::kpis($this->period());
        $current = $data['current'];
        $previous = $data['previous'];

        return [
            self::tile('quotes', 'Cotizaciones', (int) $current['quotes'], (int) $previous['quotes'], 'heroicon-o-document-text', 'Cotizaciones individuales y corporativas emitidas en el período.', $data['sparklines']['quotes']),
            self::tile('executed', 'Convertidas', (int) $current['executed'], (int) $previous['executed'], 'heroicon-o-check-badge', 'Cotizaciones individuales del período que ya son afiliaciones.', []),
            self::tile('conversion', 'Conversión', (float) ($current['conversion'] ?? 0), (float) ($previous['conversion'] ?? 0), 'heroicon-o-funnel', 'Convertidas sobre cotizaciones individuales del período. Las más recientes aún pueden convertirse.', [], isPercent: true),
            self::tile('corporate_quotes', 'Cotizaciones corporativas', (int) $current['corporate_quotes'], (int) $previous['corporate_quotes'], 'heroicon-o-building-office', 'Cotizaciones corporativas emitidas en el período.', []),
            self::tile('new_agents', 'Agentes nuevos', (int) $current['new_agents'], (int) $previous['new_agents'], 'heroicon-o-user-plus', 'Agentes registrados en el período.', $data['sparklines']['agents']),
            self::tile('new_agencies', 'Agencias nuevas', (int) $current['new_agencies'], (int) $previous['new_agencies'], 'heroicon-o-building-storefront', 'Agencias registradas en el período.', []),
        ];
    }

    protected function context(): array
    {
        $network = CorporateCommercialMetrics::network();

        return [
            ['label' => 'agentes activos', 'value' => $network['agents'], 'icon' => 'heroicon-o-users'],
            ['label' => 'agencias activas', 'value' => $network['agencies'], 'icon' => 'heroicon-o-building-office-2'],
        ];
    }

    protected function contextLabel(): string
    {
        return 'Red hoy';
    }
}
