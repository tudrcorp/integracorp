<?php

declare(strict_types=1);

namespace App\Filament\Administration\Widgets\Dashboard;

use App\Filament\Metrics\Widgets\Corporate\CorporateKpiTiles;
use App\Support\Administration\Dashboard\AdministrationFinanceMetrics;
use App\Support\Administration\Dashboard\AdministrationPeopleMetrics;
use App\Support\Metrics\Corporate\CorporateFinanceMetrics;

/**
 * Indicadores financieros del Escritorio: lo que entra, lo que sale, el
 * resultado y la cobranza (vencida y esperada), con la tasa BCV del día.
 */
class AdministrationKpiTiles extends CorporateKpiTiles
{
    protected function tiles(): array
    {
        $data = AdministrationFinanceMetrics::kpis($this->period());
        $current = $data['current'];
        $previous = $data['previous'];
        $receivables = CorporateFinanceMetrics::receivables();
        $upcoming = AdministrationFinanceMetrics::upcomingCollections();

        return [
            self::moneyTile('income', 'Ingresos', (float) $current['income'], (float) $previous['income'], 'heroicon-o-banknotes', 'Pagos registrados en el período (ventas), en USD.', $data['sparklines']['income']),
            self::moneyTile('outflows', 'Egresos', (float) $current['outflows'], (float) $previous['outflows'], 'heroicon-o-arrow-trending-down', 'Comisiones generadas, costo médico (órdenes finalizadas) y facturas de proveedores registradas en el período. No incluye nómina. Aquí, bajar es mejor.', $data['sparklines']['outflows'], lowerIsBetter: true),
            self::moneyTile('net', 'Resultado', (float) $current['net'], (float) $previous['net'], 'heroicon-o-scale', 'Ingresos menos egresos del período (sin nómina).', note: 'Estimado'),
            self::snapshotTile('pending', 'Por cobrar', $receivables['pending_amount'], 'heroicon-o-clock', $receivables['pending_count'].' cuotas pendientes de cobro, a hoy.', 'US$ '),
            self::snapshotTile('overdue', 'Vencido', $receivables['overdue_amount'], 'heroicon-o-exclamation-triangle', $receivables['overdue_count'].' cuotas cuya fecha de pago ya pasó, a hoy.', 'US$ '),
            self::snapshotTile('upcoming', 'Cobranza esperada', $upcoming['total'], 'heroicon-o-calendar-days', $upcoming['count'].' cuotas con fecha de pago en los próximos '.AdministrationFinanceMetrics::HORIZON_DAYS.' días.', 'US$ '),
        ];
    }

    protected function context(): array
    {
        $rate = AdministrationFinanceMetrics::bcvRate();
        $payables = AdministrationFinanceMetrics::payables();
        $context = [];

        if ($rate !== null) {
            $context[] = ['label' => 'tasa BCV', 'value' => $rate, 'icon' => 'heroicon-o-currency-dollar', 'prefix' => 'Bs. ', 'decimals' => 2];
        }

        $context[] = ['label' => 'facturas de proveedores por pagar', 'value' => $payables['amount'], 'icon' => 'heroicon-o-document-text', 'prefix' => 'US$ '];
        $context[] = ['label' => 'comisiones por pagar', 'value' => AdministrationFinanceMetrics::pendingCommissions()['amount'], 'icon' => 'heroicon-o-user-group', 'prefix' => 'US$ '];
        $context[] = ['label' => 'colaboradores', 'value' => AdministrationPeopleMetrics::people()->count(), 'icon' => 'heroicon-o-identification'];

        return $context;
    }
}
