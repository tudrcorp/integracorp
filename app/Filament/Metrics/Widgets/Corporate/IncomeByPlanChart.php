<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateFinanceMetrics;
use Illuminate\Contracts\Support\Htmlable;

class IncomeByPlanChart extends CorporateBarChart
{
    public function getHeading(): string|Htmlable|null
    {
        return 'Ingresos por plan';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Las corporativas se agrupan en una sola barra. Clic para ver los pagos.';
    }

    protected function values(): array
    {
        return CorporateFinanceMetrics::incomeByPlan($this->period());
    }

    protected function isMoney(): bool
    {
        return true;
    }

    protected function shareText(): string
    {
        return ' de los ingresos';
    }

    protected function detailDimension(): string
    {
        return 'sale_plan';
    }
}
