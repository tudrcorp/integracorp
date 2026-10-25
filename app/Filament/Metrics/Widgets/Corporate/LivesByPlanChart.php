<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporatePortfolioMetrics;
use Illuminate\Contracts\Support\Htmlable;

class LivesByPlanChart extends CorporateBarChart
{
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 6];

    public function getHeading(): string|Htmlable|null
    {
        return 'Vidas cubiertas por plan';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Foto a hoy: afiliados activos, individuales y corporativos.';
    }

    protected function values(): array
    {
        return CorporatePortfolioMetrics::livesByPlan();
    }

    protected function noun(): string
    {
        return 'vidas';
    }

    protected function shareText(): string
    {
        return ' de las vidas';
    }
}
