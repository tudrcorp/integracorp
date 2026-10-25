<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateOrganizationMetrics;
use Illuminate\Contracts\Support\Htmlable;

class TicketsFlowChart extends CorporateComparisonChart
{
    public function getHeading(): string|Htmlable|null
    {
        return 'Tickets creados frente a resueltos';
    }

    protected function intro(): string
    {
        return 'Si se crean más de los que se resuelven, el trabajo pendiente crece.';
    }

    protected function noun(): string
    {
        return 'tickets';
    }

    protected function series(): array
    {
        $data = CorporateOrganizationMetrics::createdVsResolved($this->period());

        return ['labels' => $data['labels'], 'first' => $data['created'], 'second' => $data['resolved']];
    }

    protected function names(): array
    {
        return ['Creados', 'Resueltos'];
    }

    protected function differenceLabel(): string
    {
        return 'Pendiente que se suma';
    }
}
