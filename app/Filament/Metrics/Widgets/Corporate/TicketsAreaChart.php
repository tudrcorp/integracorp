<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateOrganizationMetrics;
use Illuminate\Contracts\Support\Htmlable;

class TicketsAreaChart extends CorporateBarChart
{
    public function getHeading(): string|Htmlable|null
    {
        return 'Quién pide';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Tickets del período por área de quien los creó.';
    }

    protected function values(): array
    {
        return CorporateOrganizationMetrics::byArea($this->period());
    }

    protected function noun(): string
    {
        return 'tickets';
    }
}
