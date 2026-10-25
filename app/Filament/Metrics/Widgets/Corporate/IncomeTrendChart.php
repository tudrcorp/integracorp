<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateFinanceMetrics;
use Illuminate\Contracts\Support\Htmlable;

class IncomeTrendChart extends CorporateTrendChart
{
    public function getHeading(): string|Htmlable|null
    {
        return 'Evolución de los ingresos';
    }

    protected function subject(): string
    {
        return 'Ingresos registrados';
    }

    protected function isMoney(): bool
    {
        return true;
    }

    protected function trendData(): array
    {
        return CorporateFinanceMetrics::incomeTrend($this->period());
    }
}
