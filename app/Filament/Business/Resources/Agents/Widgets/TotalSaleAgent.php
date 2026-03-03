<?php

namespace App\Filament\Business\Resources\Agents\Widgets;

use Filament\Widgets\ChartWidget;

class TotalSaleAgent extends ChartWidget
{
    protected ?string $heading = 'Total Sale Agent';

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
