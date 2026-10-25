<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporatePortfolioMetrics;
use Illuminate\Contracts\Support\Htmlable;

class LivesByAgeChart extends CorporateBarChart
{
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 6];

    public function getHeading(): string|Htmlable|null
    {
        return 'Vidas cubiertas por edad';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Foto a hoy: el perfil de riesgo de la cartera.';
    }

    protected function values(): array
    {
        return CorporatePortfolioMetrics::livesByAge();
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
