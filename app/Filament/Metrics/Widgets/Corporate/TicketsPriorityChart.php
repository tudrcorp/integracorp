<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateOrganizationMetrics;
use Illuminate\Contracts\Support\Htmlable;

class TicketsPriorityChart extends CorporateDonutChart
{
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    public function getHeading(): string|Htmlable|null
    {
        return 'Prioridad';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Tickets creados en el período, por prioridad.';
    }

    protected function values(): array
    {
        return CorporateOrganizationMetrics::byPriority($this->period());
    }

    public function noun(): string
    {
        return 'tickets';
    }

    public function detailDimension(): string
    {
        return 'ticket_priority';
    }

    protected function colorRoles(): array
    {
        return ['ALTA' => 'critical', 'MEDIA' => 'warning', 'BAJA' => 'series1'];
    }
}
