<?php

namespace App\Filament\Business\Resources\Affiliations\Widgets;

use Filament\Widgets\ChartWidget;

class TotalAfiliacionesPorCiudad extends ChartWidget
{
    protected ?string $heading = 'Total Afiliaciones Por Ciudad';

    protected function getData(): array
    {
        return [
            //
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
