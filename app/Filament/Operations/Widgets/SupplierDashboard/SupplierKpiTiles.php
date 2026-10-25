<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\SupplierDashboard;

use App\Filament\Operations\Widgets\Dashboard\OperationsKpiTiles;
use App\Support\Operations\Dashboard\SupplierDashboardAnalytics;
use App\Support\Operations\SupplierDashboardMetrics;

/**
 * KPI del proveedor en el período, con la línea compacta del equipo encima.
 */
class SupplierKpiTiles extends OperationsKpiTiles
{
    protected static ?int $sort = 2;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $period = $this->period();
        $data = SupplierDashboardAnalytics::kpis($period);
        $current = $data['current'];
        $previous = $data['previous'];
        $spark = $data['sparklines'];

        return [
            'team' => [
                ['label' => 'analistas activos', 'value' => SupplierDashboardMetrics::analystsCount(), 'icon' => 'heroicon-o-identification'],
                ['label' => 'médicos acreditados', 'value' => SupplierDashboardMetrics::doctorsCount(), 'icon' => 'heroicon-o-user-circle'],
            ],
            'tiles' => [
                self::tile('new_cases', 'Casos nuevos', $current['new_cases'], $previous['new_cases'], 'heroicon-o-folder-plus', 'Casos abiertos en el período.', $spark['new_cases']),
                self::tile('consultations', 'Consultas realizadas', $current['consultations'], $previous['consultations'], 'heroicon-o-chat-bubble-left-right', 'Consultas registradas en el período en los casos del proveedor.', $spark['consultations']),
                self::tile('discharges', 'Altas médicas', $current['discharges'], $previous['discharges'], 'heroicon-o-check-badge', 'Casos que recibieron el alta médica en el período.', $spark['discharges']),
                self::tile('average_days', 'Días promedio hasta el alta', (float) ($current['average_days'] ?? 0), (float) ($previous['average_days'] ?? 0), 'heroicon-o-clock', 'Tiempo promedio entre la apertura del caso y su alta, para las altas del período. Aquí, bajar es mejor.', [], lowerIsBetter: true, decimals: 1, suffix: ' días'),
                self::tile('patients', 'Pacientes atendidos', $current['patients'], $previous['patients'], 'heroicon-o-user-group', 'Pacientes distintos con al menos una consulta en el período.', $spark['patients']),
                self::tile('doctors', 'Médicos activos', $current['doctors'], $previous['doctors'], 'heroicon-o-user-circle', 'Médicos distintos que registraron consultas en el período.', $spark['doctors']),
            ],
            'compare' => $period->compare,
            'previousLabel' => $period->previousLabel(),
            'periodKey' => $period->cacheKey(),
        ];
    }
}
