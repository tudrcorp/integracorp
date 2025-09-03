<?php

namespace App\Filament\Marketing\Resources\Events\Widgets;

use Carbon\Carbon;
use App\Models\Guest;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;

class ChartOverviewEvent extends ChartWidget
{
    protected ?string $heading = 'Gráfico de Receptividad';

    // protected ?string $maxHeight = '350px';

    public ?Model $record = null;

    protected function getData(): array
    {
        $data = Trend::query(Guest::where('event_id', $this->record->id))
            ->between(
                start: now()->startOfMonth(),
                end: now()->endOfMonth()
            )
            ->perDay()
            ->count();
        //quiero crear un grafico de lines que me indique la cantidad de personas que se unieron a un evento
        return [

            //quiero crear un grafico de lines que me indique la cantidad de personas que se unieron a un evento
            'labels' => ($data->map(fn(TrendValue $value) => Carbon::parse($value->date)->isoFormat('DD-MMM'))->toArray()),
            'datasets' => [
                [
                    'label' => 'Cantidad de personas',
                    'backgroundColor' => 'rgb(255, 99, 132)',
                    'borderColor' => 'rgb(255, 99, 132)',
                    'data' => $data->map(fn(TrendValue $value) => $value->aggregate),
                ],
            ],

        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    
}