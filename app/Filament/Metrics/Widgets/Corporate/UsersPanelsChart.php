<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateUsersMetrics;
use Illuminate\Contracts\Support\Htmlable;

class UsersPanelsChart extends CorporateBarChart
{
    public function getHeading(): string|Htmlable|null
    {
        return 'Dónde trabaja el personal';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Pantallas abiertas y acciones por panel. El recorrido detallado se conserva 90 días.';
    }

    protected function values(): array
    {
        return CorporateUsersMetrics::panels($this->period());
    }

    protected function noun(): string
    {
        return 'movimientos';
    }
}
