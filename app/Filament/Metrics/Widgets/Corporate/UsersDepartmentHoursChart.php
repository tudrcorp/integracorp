<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateUsersMetrics;
use Illuminate\Contracts\Support\Htmlable;

class UsersDepartmentHoursChart extends CorporateBarChart
{
    public function getHeading(): string|Htmlable|null
    {
        return 'Promedio diario por área';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Horas activas por persona y día conectado. Clic en una barra para ver a su gente.';
    }

    protected function values(): array
    {
        return CorporateUsersMetrics::avgHoursByDepartment($this->period());
    }

    protected function noun(): string
    {
        return 'horas';
    }

    protected function shareText(): string
    {
        return ' de la suma de promedios';
    }

    protected function detailDimension(): string
    {
        return 'user_department';
    }
}
