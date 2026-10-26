<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateUsersMetrics;
use Illuminate\Contracts\Support\Htmlable;

class UsersHoursTrendChart extends CorporateTrendChart
{
    public function getHeading(): string|Htmlable|null
    {
        return 'Evolución de las horas activas';
    }

    protected function subject(): string
    {
        return 'Horas activas del personal';
    }

    protected function noun(): string
    {
        return 'horas';
    }

    protected function trendData(): array
    {
        return CorporateUsersMetrics::hoursTrend($this->period());
    }
}
