<?php

declare(strict_types=1);

namespace App\Filament\Business\Widgets\Dashboard;

use App\Filament\Metrics\Widgets\Corporate\CorporateComparisonChart;
use App\Support\Business\Dashboard\BusinessNewVsRenewalMetrics;
use Illuminate\Contracts\Support\Htmlable;

class BusinessNewVsRenewalCountChart extends CorporateComparisonChart
{
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 6];

    public function getHeading(): string|Htmlable|null
    {
        return 'Afiliaciones: nuevas frente a renovadas';
    }

    protected function intro(): string
    {
        return 'Las renovaciones se registran desde junio de 2026.';
    }

    protected function noun(): string
    {
        return 'afiliaciones';
    }

    protected function series(): array
    {
        return BusinessNewVsRenewalMetrics::countSeries($this->period());
    }

    protected function names(): array
    {
        return ['Nuevas', 'Renovadas'];
    }

    protected function differenceLabel(): string
    {
        return 'Nuevas menos renovadas';
    }
}
