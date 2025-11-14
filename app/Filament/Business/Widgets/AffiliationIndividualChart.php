<?php

namespace App\Filament\Business\Widgets;

use Carbon\Carbon;
use Flowframe\Trend\Trend;
use App\Models\Affiliation;
use App\Models\CorporateQuote;
use Flowframe\Trend\TrendValue;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AffiliationIndividualChart extends ChartWidget
{
    protected ?string $heading = 'Grafico de Afiliaciones Individuales';

    public ?string $filter = 'year';

    protected static ?int $sort = 3;

    protected ?string $maxHeight = '400px';

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
        return 'Afiliaciones generales';
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

        $rawDateColumn = DB::raw("STR_TO_DATE(activated_at, '%d/%m/%Y')");

        //Si el usuario es admin 
        if (Auth::user()->is_accountManagers == 1) {
            $data = Trend::query(Affiliation::where('ownerAccountManagers', Auth::user()->id))
                ->between(
                    start: $rangeStartDate,
                    end: $rangeEndDate,
                )
                ->perDay()
                ->count();
        } else {
            //Si el usuario no es admin de cuentas t es un super admin
            $data = Trend::model(Affiliation::class)
                ->between(
                    start: $rangeStartDate,
                    end: $rangeEndDate,
                )
                ->perDay()
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Cotizaciones Corporativas',
                    'data' => $data->map(fn(TrendValue $value) => $value->aggregate),
                    // 'data' => [30, 10, 5, 40, 21, 32, 1, 74, 65, 45, 77, 89],
                    'backgroundColor' => [
                        '#D2D2FF', // Rosado muy claro
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
                        '#D2D2FF', // Rosado muy claro
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