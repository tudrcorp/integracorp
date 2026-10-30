<?php

declare(strict_types=1);

namespace App\Filament\Business\Widgets\Dashboard;

use App\Filament\Metrics\Widgets\Corporate\CorporateComparisonChart;
use App\Support\Business\Dashboard\BusinessNewVsRenewalMetrics;
use Illuminate\Contracts\Support\Htmlable;

class BusinessNewVsRenewalIncomeChart extends CorporateComparisonChart
{
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 6];

    public function getHeading(): string|Htmlable|null
    {
        return 'Dinero: ventas nuevas frente a renovaciones';
    }

    protected function intro(): string
    {
        return 'Lo que trae el equipo frente a lo que la cartera mantiene.';
    }

    protected function isMoney(): bool
    {
        return true;
    }

    protected function series(): array
    {
        return BusinessNewVsRenewalMetrics::incomeSeries($this->period());
    }

    protected function names(): array
    {
        return ['Ventas nuevas', 'Renovaciones'];
    }

    protected function differenceLabel(): string
    {
        return 'Nuevas menos renovaciones';
    }
}
