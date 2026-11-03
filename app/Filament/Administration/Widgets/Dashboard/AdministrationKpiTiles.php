<?php

declare(strict_types=1);

namespace App\Filament\Administration\Widgets\Dashboard;

use App\Filament\Metrics\Widgets\Corporate\CorporateKpiTiles;
use App\Support\Administration\Dashboard\AdministrationFinanceMetrics;
use App\Support\Administration\Dashboard\AdministrationPeopleMetrics;
use App\Support\Metrics\Corporate\CorporateFinanceMetrics;

/**
 * Indicadores financieros del Escritorio: lo que entra, lo que sale, el
 * resultado y lo vencido, con la tasa BCV del día. «Por cobrar» y «Cobranza
 * esperada» se retiraron el 03/11/2026 a pedido de Gustavo.
 */
class AdministrationKpiTiles extends CorporateKpiTiles
{
    protected function tiles(): array
    {
        $data = AdministrationFinanceMetrics::kpis($this->period());
        $current = $data['current'];
        $previous = $data['previous'];
        $receivables = CorporateFinanceMetrics::receivables();

        return [
            self::moneyTile('income', 'Ingresos', (float) $current['income'], (float) $previous['income'], 'heroicon-o-banknotes', 'Pagos registrados en el período (ventas), en USD.', $data['sparklines']['income']),
            self::moneyTile('outflows', 'Egresos', (float) $current['outflows'], (float) $previous['outflows'], 'heroicon-o-arrow-trending-down', 'Comisiones generadas, costo médico (órdenes finalizadas) y facturas de proveedores registradas en el período. No incluye nómina. Aquí, bajar es mejor.', $data['sparklines']['outflows'], lowerIsBetter: true),
            self::moneyTile('net', 'Resultado', (float) $current['net'], (float) $previous['net'], 'heroicon-o-scale', 'Ingresos menos egresos del período (sin nómina).', note: 'Estimado'),
            self::snapshotTile('overdue', 'Vencido', $receivables['overdue_amount'], 'heroicon-o-exclamation-triangle', $receivables['overdue_count'].' cuotas cuya fecha de pago ya pasó, a hoy.', 'US$ '),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [...parent::getViewData(), 'tilesGrid' => 'md:grid-cols-2 2xl:grid-cols-4'];
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
