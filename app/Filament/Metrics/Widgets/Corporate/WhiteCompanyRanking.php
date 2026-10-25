<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateFinanceMetrics;

class WhiteCompanyRanking extends CorporateRanking
{
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 6];

    public function getHeading(): string
    {
        return 'Empresas aliadas';
    }

    public function getDescription(): string
    {
        return 'Ingresos del período vendidos con marca blanca.';
    }

    protected function dimension(): string
    {
        return 'sale_white_company';
    }

    protected function icon(): string
    {
        return 'heroicon-o-building-library';
    }

    protected function emptyText(): string
    {
        return 'No hubo ventas con empresas aliadas en este período.';
    }

    protected function ranking(): array
    {
        return CorporateFinanceMetrics::incomeByWhiteCompany($this->period(), 8);
    }
}
