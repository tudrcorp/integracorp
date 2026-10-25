<?php

declare(strict_types=1);

namespace App\Filament\Administration\Widgets\Dashboard;

use App\Filament\Metrics\Widgets\Corporate\CorporateRanking;
use App\Support\Administration\Dashboard\AdministrationFinanceMetrics;
use App\Support\Metrics\Corporate\CorporateMetrics;

/**
 * Facturas de proveedores pendientes de pago, a hoy.
 */
class AdministrationPayablesRanking extends CorporateRanking
{
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 6];

    public function getHeading(): string
    {
        return 'Facturas de proveedores por pagar';
    }

    public function getDescription(): string
    {
        $payables = AdministrationFinanceMetrics::payables();

        return 'Foto a hoy: '.CorporateMetrics::number($payables['count']).' facturas pendientes por '.CorporateMetrics::money($payables['amount']).'.';
    }

    protected function dimension(): string
    {
        return '';
    }

    protected function icon(): string
    {
        return 'heroicon-o-document-text';
    }

    protected function emptyText(): string
    {
        return 'No hay facturas de proveedores pendientes de pago.';
    }

    protected function ranking(): array
    {
        return AdministrationFinanceMetrics::payables()['ranking'];
    }
}
