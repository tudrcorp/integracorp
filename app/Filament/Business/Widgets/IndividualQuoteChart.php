<?php

namespace App\Filament\Business\Widgets;

use App\Models\CorporateQuote;
use App\Models\IndividualQuote;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class IndividualQuoteChart extends ChartWidget
{
    protected ?string $heading = 'Cotizaciones Individuales y Corporativas por mes';

    protected static ?int $sort = 1;

    protected ?string $maxHeight = '400px';

    protected ?string $description = 'Total de cotizaciones por mes. Selecciona el año para ver el movimiento.';

    public ?string $filter = null;

    public function mount(): void
    {
        $this->filter = $this->filter ?? (string) Carbon::now()->year;
    }

    protected function getFilters(): ?array
    {
        $now = Carbon::now();
        $filters = [];
        for ($i = 0; $i < 3; $i++) {
            $y = $now->year - $i;
            $filters[(string) $y] = (string) $y;
        }

        return $filters;
    }

    protected function getData(): array
    {
        $anio = (int) ($this->filter ?? Carbon::now()->year);
        $isAccountManager = Auth::user()->is_accountManagers == 1;
        $userId = Auth::user()->id;

        $scopeIndividual = fn ($q) => $isAccountManager ? $q->where('ownerAccountManagers', $userId) : $q;
        $scopeCorporate = fn ($q) => $isAccountManager ? $q->where('ownerAccountManagers', $userId) : $q;

        $labels = [];
        $individuales = [];
        $corporativas = [];

        for ($mes = 1; $mes <= 12; $mes++) {
            $inicio = Carbon::createFromDate($anio, $mes, 1)->startOfMonth();
            $fin = Carbon::createFromDate($anio, $mes, 1)->endOfMonth();

            $labels[] = $inicio->translatedFormat('F');

            $individuales[] = (clone $scopeIndividual(IndividualQuote::query()))
                ->whereBetween('created_at', [$inicio, $fin])
                ->count();

            $corporativas[] = (clone $scopeCorporate(CorporateQuote::query()))
                ->whereBetween('created_at', [$inicio, $fin])
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Cotizaciones Individuales',
                    'data' => $individuales,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'Cotizaciones Corporativas',
                    'data' => $corporativas,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.8)',
                    'borderColor' => 'rgb(16, 185, 129)',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
                'x' => [
                    'grid' => [
                        'display' => true,
                    ],
                ],
            ],
            'barPercentage' => 0.7,
            'categoryPercentage' => 0.85,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
