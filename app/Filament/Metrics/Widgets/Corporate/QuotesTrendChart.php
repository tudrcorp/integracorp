<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateCommercialMetrics;
use Illuminate\Contracts\Support\Htmlable;

class QuotesTrendChart extends CorporateTrendChart
{
    public function getHeading(): string|Htmlable|null
    {
        return 'Evolución de las cotizaciones';
    }

    protected function subject(): string
    {
        return 'Cotizaciones emitidas';
    }

    protected function noun(): string
    {
        return 'cotizaciones';
    }

    protected function trendData(): array
    {
        return CorporateCommercialMetrics::quotesTrend($this->period());
    }
}
