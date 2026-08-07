<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets;

use App\Filament\Metrics\Widgets\Concerns\AfiliacionesByMonthDrillChart;

class AfiliacionesCorporativasByMonthChart extends AfiliacionesByMonthDrillChart
{
    protected function affiliationKind(): string
    {
        return 'corporate';
    }

    protected function overviewHeading(): string
    {
        return 'Afiliados corporativos por mes';
    }

    protected function affiliationsNoun(): string
    {
        return 'afiliados corporativos';
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    protected function accentRgb(): array
    {
        return [16, 185, 129];
    }
}
