<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporatePortfolioMetrics;

/**
 * Cartera: altas, bajas, crecimiento neto y renovación del período, con el
 * tamaño de la cartera a hoy arriba.
 */
class PortfolioKpiTiles extends CorporateKpiTiles
{
    protected function tiles(): array
    {
        $data = CorporatePortfolioMetrics::kpis($this->period());
        $current = $data['current'];
        $previous = $data['previous'];

        return [
            self::tile('new', 'Altas', (int) $current['new'], (int) $previous['new'], 'heroicon-o-user-plus', 'Afiliaciones individuales y corporativas emitidas en el período.', $data['sparklines']['new']),
            self::tile('dropouts', 'Bajas', (int) $current['dropouts'], (int) $previous['dropouts'], 'heroicon-o-user-minus', 'Afiliaciones excluidas, inactivadas o anuladas en el período. Aquí, bajar es mejor.', $data['sparklines']['dropouts'], lowerIsBetter: true),
            self::tile('net', 'Crecimiento neto', (int) $current['net'], (int) $previous['net'], 'heroicon-o-arrow-trending-up', 'Altas menos bajas del período.', []),
            self::tile('renewed', 'Renovadas', (int) $current['renewed'], (int) $previous['renewed'], 'heroicon-o-arrow-path', 'Afiliaciones con renovación aceptada en el período.', []),
            self::tile('renewal_rate', 'Tasa de renovación', (float) ($current['renewal_rate'] ?? 0), (float) ($previous['renewal_rate'] ?? 0), 'heroicon-o-chart-pie', 'Renovadas sobre las que llegaron a su fecha de renovación en el período (renovadas + vencidas sin renovar).', [], isPercent: true),
            self::tile('expired', 'Vencidas sin renovar', (int) $current['expired'], (int) $previous['expired'], 'heroicon-o-calendar-days', 'Llegaron a su fecha de renovación en el período y no se renovaron. Aquí, bajar es mejor.', [], lowerIsBetter: true),
        ];
    }

    protected function context(): array
    {
        $snapshot = CorporatePortfolioMetrics::snapshot();

        return [
            ['label' => 'afiliaciones activas', 'value' => $snapshot['active'], 'icon' => 'heroicon-o-identification'],
            ['label' => 'individuales', 'value' => $snapshot['active_individual'], 'icon' => 'heroicon-o-user'],
            ['label' => 'corporativas', 'value' => $snapshot['active_corporate'], 'icon' => 'heroicon-o-building-office'],
            ['label' => 'vidas cubiertas', 'value' => $snapshot['lives'], 'icon' => 'heroicon-o-heart'],
        ];
    }
}
