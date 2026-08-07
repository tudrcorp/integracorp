<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets;

use App\Filament\Metrics\Widgets\Concerns\AfiliacionesPlanAmountPieChart;

class AfiliacionesCorporativasPlanAmountPieChart extends AfiliacionesPlanAmountPieChart
{
    protected function affiliationKind(): string
    {
        return 'corporate';
    }

    protected function overviewHeading(): string
    {
        return 'Total US$ corporativas por plan';
    }

    protected function overviewDescription(): string
    {
        return 'Afiliaciones corporativas activas · cuota en dólares de Plan Inicial, Ideal y Especial.';
    }
}
