<?php

namespace App\Filament\Marketing\Resources\Events\Widgets;

use App\Models\Guest;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;

class ChartOverviewEventTwo extends ChartWidget
{
    protected ?string $heading = 'Gráfico comparativo de inscritos y no inscritos';

    protected ?string $maxHeight = '300px';

    public ?Model $record = null;

    protected function getData(): array
    {
        $total_guest = Guest::where('event_id', $this->record->id)->count();
        $porcenYes = $total_guest * 100 / $this->record->total_guest;
        $porcenNo = 100 - $porcenYes;

        return [
            //porcentaje de incritos
            'labels' => ['Incritos', 'No Incritos'],
            'datasets' => [
                [
                    'label' => 'Incritos',
                    'backgroundColor' => ['rgb(0,255,0)', 'rgb(255,128,0)'],
                    'borderColor' => '#ffffff',
                    'data' => [$porcenYes, $porcenNo],
                ],      
            ],
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}