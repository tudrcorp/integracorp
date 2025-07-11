<?php

namespace App\Filament\Agents\Widgets;

use Carbon\Carbon;
use Flowframe\Trend\Trend;
use App\Models\CorporateQuote;
use App\Models\IndividualQuote;
use Flowframe\Trend\TrendValue;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class IndividualQuoteChart extends ChartWidget
{
    protected ?string $heading = 'Gráfico de Cotizaciones Individuales';

    public ?string $filter = 'week';

    protected static ?int $sort = 2;

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

        $data = Trend::query(IndividualQuote::where('agent_id', Auth::user()->agent_id))
            ->between(
                start: $rangeStartDate,
                end: $rangeEndDate,
            )
            ->perDay()
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Cotizaciones Individuales',
                    'data' => $data->map(fn(TrendValue $value) => $value->aggregate),
                    'backgroundColor' => [
                        '#27e9b5',
                        '#3b5265',
                        '#df2531',
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

    protected function getType(): string
    {
        return 'bar';
    }

    public function getColumns(): int | string | array
    {
        return 12;
    }
}