<?php

declare(strict_types=1);

namespace App\Filament\Business\Widgets\Captacion;

use App\Filament\Metrics\Widgets\Corporate\CorporateBarChart;
use App\Support\ProspectAgents\Dashboard\ProspectDashboardMetrics;
use Illuminate\Contracts\Support\Htmlable;

class CaptacionClassificationChart extends CorporateBarChart
{
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 6];

    public function getHeading(): string|Htmlable|null
    {
        return 'Empresas por clasificación';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Las 15 clasificaciones con más prospectos · '.$this->period()->label().'. Clic en una barra para ver los prospectos.';
    }

    protected function detailDimension(): string
    {
        return 'classification';
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
        return ProspectDashboardMetrics::byClassification($this->period());
    }
}
