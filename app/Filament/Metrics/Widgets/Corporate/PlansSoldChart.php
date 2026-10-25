<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateCommercialMetrics;
use Illuminate\Contracts\Support\Htmlable;

class PlansSoldChart extends CorporateBarChart
{
    public function getHeading(): string|Htmlable|null
    {
        return 'Planes más vendidos';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Afiliaciones individuales con pago en el período, por plan.';
    }

    protected function values(): array
    {
        return CorporateCommercialMetrics::plansSold($this->period());
    }

    protected function noun(): string
    {
        return 'afiliaciones';
    }

    protected function shareText(): string
    {
        return ' de las afiliaciones';
    }
}
