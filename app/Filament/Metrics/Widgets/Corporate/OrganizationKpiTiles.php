<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateOrganizationMetrics;

/**
 * Organización: la gestión interna vista desde el helpdesk.
 */
class OrganizationKpiTiles extends CorporateKpiTiles
{
    protected function tiles(): array
    {
        $data = CorporateOrganizationMetrics::kpis($this->period());
        $current = $data['current'];
        $previous = $data['previous'];

        return [
            self::tile('created', 'Tickets nuevos', (int) $current['created'], (int) $previous['created'], 'heroicon-o-lifebuoy', 'Tickets de helpdesk abiertos en el período, de todas las áreas.', $data['sparklines']['created']),
            self::tile('resolved', 'Resueltos', (int) $current['resolved'], (int) $previous['resolved'], 'heroicon-o-check-circle', 'Tickets resueltos en el período (por su fecha de resolución).', $data['sparklines']['resolved']),
            self::tile('sla_rate', 'Dentro de SLA', (float) ($current['sla_rate'] ?? 0), (float) ($previous['sla_rate'] ?? 0), 'heroicon-o-shield-check', 'Tickets del período que no incumplieron su SLA. La variación está en puntos porcentuales.', [], isPercent: true),
            self::tile('sla_breached', 'Fuera de SLA', (int) $current['sla_breached'], (int) $previous['sla_breached'], 'heroicon-o-exclamation-triangle', 'Tickets del período que incumplieron su SLA. Aquí, bajar es mejor.', [], lowerIsBetter: true),
            self::tile('resolution_hours', 'Horas para resolver', (float) ($current['resolution_hours'] ?? 0), (float) ($previous['resolution_hours'] ?? 0), 'heroicon-o-clock', 'Promedio entre la creación y la resolución de los tickets resueltos en el período. Aquí, bajar es mejor.', [], lowerIsBetter: true, decimals: 1, suffix: ' h'),
            self::snapshotTile('open', 'Abiertos', (int) $data['open'], 'heroicon-o-inbox-stack', 'Tickets que hoy no están terminados ni cancelados.'),
        ];
    }
}
