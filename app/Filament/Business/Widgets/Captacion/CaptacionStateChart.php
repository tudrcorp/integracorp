<?php

declare(strict_types=1);

namespace App\Filament\Business\Widgets\Captacion;

use App\Filament\Metrics\Widgets\Corporate\CorporateBarChart;
use App\Support\ProspectAgents\Dashboard\ProspectDashboardMetrics;
use Illuminate\Contracts\Support\Htmlable;

class CaptacionStateChart extends CorporateBarChart
{
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    public function getHeading(): string|Htmlable|null
    {
        return 'Prospectos por estado';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Dónde están los prospectos registrados · '.$this->period()->label().'. Clic en una barra para ver los prospectos.';
    }

    protected function detailDimension(): string
    {
        return 'state';
    }

    protected function noun(): string
    {
        return 'prospectos';
    }

    protected function isMoney(): bool
    {
        return false;
    }

    protected function values(): array
    {
        return ProspectDashboardMetrics::byState($this->period());
    }
}
