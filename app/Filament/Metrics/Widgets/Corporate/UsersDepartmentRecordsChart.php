<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateUsersMetrics;
use Illuminate\Contracts\Support\Htmlable;

class UsersDepartmentRecordsChart extends CorporateBarChart
{
    public function getHeading(): string|Htmlable|null
    {
        return 'Productividad por área';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Registros creados en el período por el personal de cada área. Clic en una barra para ver a su gente.';
    }

    protected function values(): array
    {
        return CorporateUsersMetrics::recordsByDepartment($this->period());
    }

    protected function noun(): string
    {
        return 'registros';
    }

    protected function detailDimension(): string
    {
        return 'user_department';
    }
}
