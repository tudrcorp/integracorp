<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateUsersMetrics;
use Illuminate\Contracts\Support\Htmlable;

class NetworkByTypeChart extends CorporateBarChart
{
    public function getHeading(): string|Htmlable|null
    {
        return 'Red comercial conectada';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Personas de la red comercial que usaron sus portales en el período, por tipo.';
    }

    protected function values(): array
    {
        return CorporateUsersMetrics::networkByType($this->period());
    }

    protected function noun(): string
    {
        return 'personas';
    }
}
