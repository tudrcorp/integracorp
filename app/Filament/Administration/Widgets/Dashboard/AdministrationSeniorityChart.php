<?php

declare(strict_types=1);

namespace App\Filament\Administration\Widgets\Dashboard;

use App\Filament\Metrics\Widgets\Corporate\CorporateBarChart;
use App\Support\Administration\Dashboard\AdministrationPeopleMetrics;
use Illuminate\Contracts\Support\Htmlable;

class AdministrationSeniorityChart extends CorporateBarChart
{
    public function getHeading(): string|Htmlable|null
    {
        return 'Antigüedad de la plantilla';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Personas activas por años en la empresa.';
    }

    protected function values(): array
    {
        return AdministrationPeopleMetrics::bySeniority();
    }

    protected function noun(): string
    {
        return 'personas';
    }

    protected function shareText(): string
    {
        return ' de la plantilla';
    }
}
