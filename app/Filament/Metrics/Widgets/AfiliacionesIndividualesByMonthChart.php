<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets;

use App\Filament\Metrics\Widgets\Concerns\AfiliacionesByMonthDrillChart;

class AfiliacionesIndividualesByMonthChart extends AfiliacionesByMonthDrillChart
{
    protected function affiliationKind(): string
    {
        return 'individual';
    }

    protected function overviewHeading(): string
    {
        return 'Afiliados individuales por mes';
    }

    protected function affiliationsNoun(): string
    {
        return 'afiliados individuales';
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    protected function accentRgb(): array
    {
        return [14, 165, 233];
    }
}
