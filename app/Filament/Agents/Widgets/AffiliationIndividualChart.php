<?php

namespace App\Filament\Agents\Widgets;

use Carbon\Carbon;
use Flowframe\Trend\Trend;
use App\Models\Affiliation;
use Flowframe\Trend\TrendValue;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class AffiliationIndividualChart extends ChartWidget
{
    protected ?string $heading = 'Grafico de Afiliaciones Individuales';

    public ?string $filter = 'week';

    protected static ?int $sort = 4;

    protected function getFilters(): ?array
    {
        return [
            'today' => 'HOY',
            'week' => 'SEMANA',
            'month' => 'MES',
            'year' => 'AÑO',
        ];
    }

    public function getDescription(): ?string
    {
        return 'Cotizaciones individuales creadas por el agente en el periodo seleccionado';
    }

    protected function getData(): array
    {
        $activeFilter = $this->filter;

        if ($activeFilter === 'today') {
            $rangeStartDate = now()->startOfDay();
            $rangeEndDate   = now()->endOfDay();
        } elseif ($activeFilter === 'week') {
            $rangeStartDate = now()->startOfWeek();
            $rangeEndDate   = now()->endOfWeek();
        } elseif ($activeFilter === 'month') {
            $rangeStartDate = now()->startOfMonth();
            $rangeEndDate   = now()->endOfMonth();
        } elseif ($activeFilter === 'year') {
            $rangeStartDate     = now()->startOfYear();
            $rangeEndDate       = now()->endOfYear();
        }

        $data = Trend::query(Affiliation::where('agent_id', Auth::user()->agent_id))
            ->between(
                start: $rangeStartDate,
                end: $rangeEndDate,
            )
            ->perDay()
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Afiliaciones Individuales',
                    'data' => $data->map(fn(TrendValue $value) => $value->aggregate),
                    'backgroundColor' => [
                        '#a16d69',
                        '#99bcbf',
                        '#bf99a9',
                        '#bfaf99',
                        '#99a9bf',
                        '#99bfaf',
                        '#9c99bf',
                        '#99bf9c',
                        '#bf9c99',
                        '#bf99bc',
                        '#c7a8a5',
                        '#ab7e7a',
                        '#7ba69d',
                        '#7b9aa6',
                        '#a6877b',
                        '#7b85a6',
                        '#a69d7b',
                        '#a67b85',
                        '#9aa67b',
                        '#7ba687',
                        '#a67b9a',
                        '#56737f'
                    ],
                    'borderColor' => false,
                    'fill' => true,
                ],
            ],
            'labels' => ($data->map(fn(TrendValue $value) => Carbon::parse($value->date)->isoFormat('DD-MMM'))->toArray()),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
        ];
    }


    protected function getType(): string
    {
        return 'bar';
    }
}