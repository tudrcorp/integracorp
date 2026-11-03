<?php

declare(strict_types=1);

namespace App\Filament\Business\Widgets\Captacion;

use App\Filament\Business\Resources\ProspectAgents\ProspectAgentLabels;
use App\Filament\Metrics\Widgets\Corporate\CorporateKpiTiles;
use App\Support\ProspectAgents\Dashboard\ProspectDashboardMetrics;

/**
 * Los mismos indicadores de siempre: altas del período (contra el anterior) y el
 * embudo de captación a hoy por estatus.
 */
class CaptacionKpiTiles extends CorporateKpiTiles
{
    protected function tiles(): array
    {
        $registrations = ProspectDashboardMetrics::registrations($this->period());
        $funnel = ProspectDashboardMetrics::funnelSnapshot()['statuses'];

        return [
            self::tile('registrations', 'Altas del período', $registrations['current'], $registrations['previous'], 'heroicon-o-user-plus', 'Prospectos registrados en el período elegido.', $registrations['sparkline']),
            self::snapshotTile('prospecting', 'Captación / prospección', $funnel[ProspectAgentLabels::STATUS_CAPTACION_PROSPECCION], 'heroicon-o-clock', 'A hoy: aún en la etapa inicial del embudo.'),
            self::snapshotTile('first_contact', 'Contacto inicial', $funnel[ProspectAgentLabels::STATUS_CONTACTO_INICIAL], 'heroicon-o-phone', 'A hoy: primer contacto comercial realizado.'),
            self::snapshotTile('negotiation', 'En proceso / negociación', $funnel[ProspectAgentLabels::STATUS_EN_PROCESO_NEGOCIACION], 'heroicon-o-arrow-path', 'A hoy: prospectos en gestión o negociación.'),
            self::snapshotTile('active_ally', 'Aliado activo', $funnel[ProspectAgentLabels::STATUS_ALIADO_ACTIVO], 'heroicon-o-check-badge', 'A hoy: convertidos en aliados activos.'),
            self::snapshotTile('inactive', 'Inactivo', $funnel[ProspectAgentLabels::STATUS_INACTIVO], 'heroicon-o-x-circle', 'A hoy: prospectos marcados como inactivos.'),
        ];
    }

    protected function context(): array
    {
        $funnel = ProspectDashboardMetrics::funnelSnapshot();
        $total = max(1, $funnel['total']);
        $allies = $funnel['statuses'][ProspectAgentLabels::STATUS_ALIADO_ACTIVO];

        return [
            ['label' => 'prospectos en captación', 'value' => $funnel['total'], 'icon' => 'heroicon-o-user-group'],
            ['label' => '% convertidos en aliado', 'value' => round($allies * 100 / $total, 1), 'icon' => 'heroicon-o-check-badge'],
        ];
    }

    protected function contextLabel(): string
    {
        return 'Captación a hoy';
    }
}
