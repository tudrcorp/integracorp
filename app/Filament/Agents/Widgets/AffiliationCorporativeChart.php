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
                    // 'backgroundColor' => 'rgba(53, 162, 235, 0.5)',
                    // 'borderColor' => 'rgb(53, 162, 235)',
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
        return 'line';
    }
}