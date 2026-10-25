<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateOrganizationMetrics;
use Illuminate\Contracts\Support\Htmlable;

class TicketsTypeChart extends CorporateBarChart
{
    public function getHeading(): string|Htmlable|null
    {
        return 'Qué se pide';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Tickets del período por tipo. Clic para verlos.';
    }

    protected function values(): array
    {
        return CorporateOrganizationMetrics::byType($this->period());
    }

    protected function noun(): string
    {
        return 'tickets';
    }

    protected function detailDimension(): string
    {
        return 'ticket_type';
    }
}
