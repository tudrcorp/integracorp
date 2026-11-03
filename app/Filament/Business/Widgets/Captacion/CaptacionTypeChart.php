<?php

declare(strict_types=1);

namespace App\Filament\Business\Widgets\Captacion;

use App\Filament\Metrics\Widgets\Corporate\CorporateBarChart;
use App\Support\ProspectAgents\Dashboard\ProspectDashboardMetrics;
use Illuminate\Contracts\Support\Htmlable;

class CaptacionTypeChart extends CorporateBarChart
{
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    public function getHeading(): string|Htmlable|null
    {
        return 'Prospectos por tipo';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Tipo de prospecto registrado · '.$this->period()->label().'. Clic en una barra para ver los prospectos.';
    }

    protected function detailDimension(): string
    {
        return 'type';
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
        return ProspectDashboardMetrics::byType($this->period());
    }
}
