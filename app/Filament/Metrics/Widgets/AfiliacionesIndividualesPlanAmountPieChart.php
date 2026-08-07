<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets;

use App\Filament\Metrics\Widgets\Concerns\AfiliacionesPlanAmountPieChart;

class AfiliacionesIndividualesPlanAmountPieChart extends AfiliacionesPlanAmountPieChart
{
    protected function affiliationKind(): string
    {
        return 'individual';
    }

    protected function overviewHeading(): string
    {
        return 'Total US$ individuales por plan';
    }

    protected function overviewDescription(): string
    {
        return 'Afiliaciones activas · cuota en dólares de Plan Inicial, Ideal y Especial.';
    }
}
