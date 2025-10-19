<?php

namespace App\Filament\Master\Widgets;

use Carbon\Carbon;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Filament\Widgets\ChartWidget;
use App\Models\AffiliationCorporate;
use Illuminate\Support\Facades\Auth;

class AffiliationCorporateChart extends ChartWidget
{
    protected ?string $heading = 'Afiliaciones Corporativas';

    public ?string $filter = 'week';

    protected static ?int $sort = 4;

    protected ?string $maxHeight = '300px';

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
        return 'Gráfico de afiliaciones individuales creadas por nuestras agencias generales, agentes y subagentes';
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
        
        $data = Trend::query(AffiliationCorporate::where('owner_code', Auth::user()->code_agency))
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
                    // 'data' => [30, 10, 5, 2, 21, 32, 45, 74, 65, 45, 77, 89],
                    'backgroundColor' => [
                        '#D2FFD2', // Rosado muy claro
                        '#E6FFF0', // Verde menta claro
                        '#E6F5FF', // Azul hielo
                        '#FFF5E6', // Amarillo suave
                        '#F0DCFF', // Lavanda claro
                        '#FAFAEA', // Beige claro
                        '#DCF0DC', // Verde claro
                        '#FFE6F0', // Magenta pastel
                        '#EBEBEB', // Gris claro
                        '#B8E6FF', // Celeste claro
                        '#FFD2D2', // Rosado cálido
                        '#D2FFD2', // Verde pálido
                        '#FFFAEA', // Amarillo dorado claro
                        '#D2D2FF', // Azul lavanda
                        '#FFDCDC', // Rosado suave
                        '#DCFFFF', // Cyan claro
                        '#F5DCDC', // Coral suave
                        '#DCE0FF', // Azul claro
                        '#FFE7DC', // Naranja melocotón
                        '#DCDCDC', // Gris plata
                        '#E6FFE6', // Verde agua
                        '#FFE6FF', // Lila claro
                        '#F0F0FF', // Azul niebla
                        '#FFF0F0', // Rosado nieve
                    ],
                    'borderColor' => [
                        '#D2FFD2', // Rosado muy claro
                        '#E6FFF0', // Verde menta claro
                        '#E6F5FF', // Azul hielo
                        '#FFF5E6', // Amarillo suave
                        '#F0DCFF', // Lavanda claro
                        '#FAFAEA', // Beige claro
                        '#DCF0DC', // Verde claro
                        '#FFE6F0', // Magenta pastel
                        '#EBEBEB', // Gris claro
                        '#B8E6FF', // Celeste claro
                        '#FFD2D2', // Rosado cálido
                        '#D2FFD2', // Verde pálido
                        '#FFFAEA', // Amarillo dorado claro
                        '#D2D2FF', // Azul lavanda
                        '#FFDCDC', // Rosado suave
                        '#DCFFFF', // Cyan claro
                        '#F5DCDC', // Coral suave
                        '#DCE0FF', // Azul claro
                        '#FFE7DC', // Naranja melocotón
                        '#DCDCDC', // Gris plata
                        '#E6FFE6', // Verde agua
                        '#FFE6FF', // Lila claro
                        '#F0F0FF', // Azul niebla
                        '#FFF0F0', // Rosado nieve
                    ],
                    'fill' => true,
                ],
            ],
            'labels' => ($data->map(fn(TrendValue $value) => Carbon::parse($value->date)->isoFormat('DD-MMM'))->toArray()),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}