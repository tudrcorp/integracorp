<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateOrganizationMetrics;
use Illuminate\Contracts\Support\Htmlable;

class TicketsStatusChart extends CorporateDonutChart
{
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    public function getHeading(): string|Htmlable|null
    {
        return 'Estatus de los tickets';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Tickets creados en el período, por su estatus actual.';
    }

    protected function values(): array
    {
        return CorporateOrganizationMetrics::byStatus($this->period());
    }

    public function noun(): string
    {
        return 'tickets';
    }

    public function detailDimension(): string
    {
        return 'ticket_status';
    }

    protected function colorRoles(): array
    {
        return [
            'TERMINADO' => 'good',
            'EN PROCESO' => 'series1',
            'EN ANALISIS' => 'violet',
            'PENDIENTE POR INICIAR' => 'warning',
            'CANCELADO' => 'muted',
        ];
    }
}
