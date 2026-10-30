<?php

declare(strict_types=1);

namespace App\Filament\Business\Widgets\Dashboard;

use App\Filament\Metrics\Widgets\Corporate\CorporateKpiTiles;
use App\Support\Business\Dashboard\BusinessNewVsRenewalMetrics;

/**
 * Nuevas ventas frente a renovaciones: lo que el equipo trajo y lo que la
 * cartera mantiene, para no medir lo segundo como si fuera venta nueva.
 */
class BusinessNewVsRenewalTiles extends CorporateKpiTiles
{
    protected function tiles(): array
    {
        $data = BusinessNewVsRenewalMetrics::kpis($this->period());
        $current = $data['current'];
        $previous = $data['previous'];

        return [
            self::moneyTile('new_income', 'Dinero de ventas nuevas', (float) $current['new_income'], (float) $previous['new_income'], 'heroicon-o-sparkles', 'Pagos dentro del primer año de cada afiliación, incluidas sus cuotas.', $data['sparklines']['new']),
            self::moneyTile('renewal_income', 'Dinero de renovaciones', (float) $current['renewal_income'], (float) $previous['renewal_income'], 'heroicon-o-arrow-path', 'Pagos de afiliaciones que ya cumplieron un año: cartera que se mantiene.', $data['sparklines']['renewal']),
            self::tile('new_share', 'Peso de lo nuevo', (float) ($current['new_share'] ?? 0), (float) ($previous['new_share'] ?? 0), 'heroicon-o-chart-pie', 'Parte del dinero clasificado que viene de ventas nuevas. La variación está en puntos porcentuales.', [], isPercent: true),
            self::tile('new_affiliations', 'Afiliaciones nuevas', (int) $current['new_affiliations'], (int) $previous['new_affiliations'], 'heroicon-o-user-plus', 'Afiliaciones emitidas por primera vez en el período.', []),
            self::tile('renewed', 'Afiliaciones renovadas', (int) $current['renewed'], (int) $previous['renewed'], 'heroicon-o-arrow-path-rounded-square', 'Renovaciones aceptadas en el período. El historial existe desde junio de 2026.', []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [...parent::getViewData(), 'tilesGrid' => 'md:grid-cols-3 2xl:grid-cols-5'];
    }

    protected function contextLabel(): string
    {
        return 'Nuevas ventas frente a renovaciones';
    }
}
