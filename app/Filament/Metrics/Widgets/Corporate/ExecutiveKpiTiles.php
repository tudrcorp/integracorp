<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateCommercialMetrics;
use App\Support\Metrics\Corporate\CorporateFinanceMetrics;
use App\Support\Metrics\Corporate\CorporateOrganizationMetrics;
use App\Support\Metrics\Corporate\CorporatePortfolioMetrics;
use App\Support\Operations\Dashboard\OperationsDashboardAnalytics;

/**
 * Resumen ejecutivo: una cifra clave de cada área, comparada con el período
 * anterior, y el tamaño de la empresa a hoy arriba.
 */
class ExecutiveKpiTiles extends CorporateKpiTiles
{
    protected function tiles(): array
    {
        $period = $this->period();
        $finance = CorporateFinanceMetrics::kpis($period);
        $portfolio = CorporatePortfolioMetrics::kpis($period);
        $commercial = CorporateCommercialMetrics::kpis($period);
        $operations = OperationsDashboardAnalytics::kpis($period);
        $receivables = CorporateFinanceMetrics::receivables();
        $notice = CorporateFinanceMetrics::costCoverageNotice($period) !== null ? 'Estimado: costos médicos incompletos' : 'Estimado';

        return [
            self::moneyTile('income', 'Ingresos', (float) $finance['current']['income'], (float) $finance['previous']['income'], 'heroicon-o-banknotes', 'Pagos registrados en el período (ventas), en USD.', $finance['sparklines']['income']),
            self::moneyTile('margin', 'Margen estimado', (float) $finance['current']['margin'], (float) $finance['previous']['margin'], 'heroicon-o-scale', 'Ingresos menos comisiones y costo médico del período.', note: $notice),
            self::tile('net', 'Crecimiento de cartera', (int) $portfolio['current']['net'], (int) $portfolio['previous']['net'], 'heroicon-o-arrow-trending-up', 'Afiliaciones nuevas menos bajas del período.', $portfolio['sparklines']['new']),
            self::tile('conversion', 'Conversión comercial', (float) ($commercial['current']['conversion'] ?? 0), (float) ($commercial['previous']['conversion'] ?? 0), 'heroicon-o-funnel', 'Cotizaciones individuales del período que ya son afiliaciones. La variación está en puntos porcentuales.', [], isPercent: true),
            self::tile('services', 'Servicios médicos', (int) $operations['current']['services'], (int) $operations['previous']['services'], 'heroicon-o-heart', 'Servicios de operaciones registrados en el período.', $operations['sparklines']['services']),
            self::snapshotTile('overdue', 'Cuotas vencidas', $receivables['overdue_amount'], 'heroicon-o-exclamation-triangle', 'Cuotas pendientes cuya fecha de pago ya pasó, a hoy.', 'US$ '),
        ];
    }

    protected function context(): array
    {
        $snapshot = CorporatePortfolioMetrics::snapshot();
        $network = CorporateCommercialMetrics::network();

        return [
            ['label' => 'afiliaciones activas', 'value' => $snapshot['active'], 'icon' => 'heroicon-o-identification'],
            ['label' => 'vidas cubiertas', 'value' => $snapshot['lives'], 'icon' => 'heroicon-o-heart'],
            ['label' => 'agentes', 'value' => $network['agents'], 'icon' => 'heroicon-o-users'],
            ['label' => 'agencias', 'value' => $network['agencies'], 'icon' => 'heroicon-o-building-office-2'],
            ['label' => 'tickets abiertos', 'value' => CorporateOrganizationMetrics::kpis($this->period())['open'], 'icon' => 'heroicon-o-lifebuoy'],
        ];
    }

    protected function contextLabel(): string
    {
        return 'La empresa hoy';
    }
}
