<?php

declare(strict_types=1);

namespace App\Filament\Administration\Widgets\Dashboard;

use App\Filament\Metrics\Widgets\Corporate\CorporateTrendChart;
use App\Support\Administration\Dashboard\AdministrationFinanceMetrics;
use Illuminate\Contracts\Support\Htmlable;

class AdministrationCommissionsTrendChart extends CorporateTrendChart
{
    public function getHeading(): string|Htmlable|null
    {
        return 'Evolución de las comisiones';
    }

    protected function subject(): string
    {
        return 'Comisiones generadas';
    }

    protected function isMoney(): bool
    {
        return true;
    }

    protected function trendData(): array
    {
        return AdministrationFinanceMetrics::commissionsTrend($this->period());
    }
}
