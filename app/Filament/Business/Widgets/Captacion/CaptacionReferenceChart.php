<?php

declare(strict_types=1);

namespace App\Filament\Business\Widgets\Captacion;

use App\Filament\Metrics\Widgets\Corporate\CorporateBarChart;
use App\Support\ProspectAgents\Dashboard\ProspectDashboardMetrics;
use Illuminate\Contracts\Support\Htmlable;

class CaptacionReferenceChart extends CorporateBarChart
{
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    public function getHeading(): string|Htmlable|null
    {
        return 'Prospectos por referido';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Canal por el que llegó cada prospecto · '.$this->period()->label().'. Clic en una barra para ver los prospectos.';
    }

    protected function detailDimension(): string
    {
        return 'reference';
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
        return ProspectDashboardMetrics::byReference($this->period());
    }
}
