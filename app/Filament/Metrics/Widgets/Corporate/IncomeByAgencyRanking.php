<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateFinanceMetrics;

class IncomeByAgencyRanking extends CorporateRanking
{
    public function getHeading(): string
    {
        return 'Agencias que más venden';
    }

    public function getDescription(): string
    {
        return 'Ingresos del período por agencia. Clic para ver los pagos.';
    }

    protected function dimension(): string
    {
        return 'sale_agency';
    }

    protected function icon(): string
    {
        return 'heroicon-o-building-office-2';
    }

    protected function ranking(): array
    {
        return CorporateFinanceMetrics::incomeByAgency($this->period(), 8);
    }
}
