<?php

namespace App\Filament\Agents\Widgets;

use Carbon\Carbon;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Filament\Widgets\ChartWidget;
use App\Models\AffiliationCorporate;
use Illuminate\Support\Facades\Auth;

class AffiliationCorporativeChart extends ChartWidget
{
    protected ?string $heading = 'Grafico de Afiliaciones Corporativas';

    public ?string $filter = 'week';

    protected static ?int $sort = 5;

    protected function getFilters(): ?array
    {
        return [
            'today' => 'HOY',
            'week' => 'SEMANA',
            'month' => 'MES',
            'year' => 'AÑO',
        ];
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

        $data = Trend::query(AffiliationCorporate::where('agent_id', Auth::user()->agent_id))
            ->between(
                start: $rangeStartDate,
                end: $rangeEndDate,
            )
            ->perDay()
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Afiliaciones Corporativas',
                    'data' => $data->map(fn(TrendValue $value) => $value->aggregate),
                    'backgroundColor' => [
                        '#280120',
                        '#450063',
                        '#ffcd00',
                        '#27e9b5',
                        '#3b5265',
                        '#df2531',
                        '#0033ff',
                        '#522a6f',
                        '#222023',
                        '#ff8a02',
                        '#09080d',
                        '#fe6807',
                        '#0033ff',
                        '#522a6f',
                        '#222023',
                        '#ff8a02',
                        '#450063',
                        '#ffcd00',
                        '#27e9b5',
                        '#3b5265',
                        '#ff1800',
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

    public function getDescription(): ?string
    {
        return 'Cotizaciones individuales creadas por el agente en el periodo seleccionado';
    }

    protected function getType(): string
    {
        return 'bar';
    }
}