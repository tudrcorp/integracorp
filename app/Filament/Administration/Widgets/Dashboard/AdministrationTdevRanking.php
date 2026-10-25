<?php

declare(strict_types=1);

namespace App\Filament\Administration\Widgets\Dashboard;

use App\Filament\Metrics\Widgets\Corporate\CorporateRanking;
use App\Support\Administration\Dashboard\AdministrationFinanceMetrics;
use App\Support\Metrics\Corporate\CorporateMetrics;

/**
 * Ventas TDEV (viajes) del período por agencia, con el estado de pago de los vouchers.
 */
class AdministrationTdevRanking extends CorporateRanking
{
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 6];

    public function getHeading(): string
    {
        return 'Ventas TDEV por agencia';
    }

    public function getDescription(): string
    {
        $tdev = AdministrationFinanceMetrics::tdev($this->period());

        return CorporateMetrics::number($tdev['vouchers']).' vouchers por '.CorporateMetrics::money($tdev['amount'])
            .' · '.CorporateMetrics::number($tdev['paid']).' pagados, '.CorporateMetrics::number($tdev['pending']).' pendientes, '.CorporateMetrics::number($tdev['voided']).' anulados.';
    }

    protected function dimension(): string
    {
        return '';
    }

    protected function icon(): string
    {
        return 'heroicon-o-paper-airplane';
    }

    protected function emptyText(): string
    {
        return 'No hay vouchers TDEV en este período.';
    }

    protected function ranking(): array
    {
        return AdministrationFinanceMetrics::tdev($this->period())['ranking'];
    }
}
