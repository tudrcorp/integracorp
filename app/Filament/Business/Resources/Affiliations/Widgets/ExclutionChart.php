<?php

namespace App\Filament\Business\Resources\Affiliations\Widgets;

use App\Models\Affiliation;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class ExclutionChart extends ChartWidget
{
    protected ?string $heading = 'RESUMEN DE AFILIACIONES EXCLUIDAS';

    protected ?string $maxHeight = '300px';

    protected ?string $description = 'Visualización mensual de afiliaciones excluidas con desglose por mes.';

    protected function getData(): array
    {
        $data = Trend::query(Affiliation::query()->where('status', 'EXCLUIDO')->whereYear('updated_at', now()->year))
            ->between(
                start: now()->startOfYear(),
                end: now()->endOfYear(),
            )
            ->perMonth()
            ->count();

        // Paleta de colores minimalista (uno por mes)
        $minimalistColors = [
            '#94a3b8',
            '#93c5fd',
            '#60a5fa',
            '#3b82f6',
            '#2563eb',
            '#1d4ed8',
            '#1e40af',
            '#1e3a8a',
            '#64748b',
            '#475569',
            '#334155',
            '#0f172a'
        ];

        return [
            'datasets' => [
                [
                    'label' => 'Total Afiliaciones Excluidas',
                    'data' => $data->map(fn(TrendValue $value) => $value->aggregate),
                    'backgroundColor' => $minimalistColors,
                    'borderRadius' => 8, // Barras redondeadas modernas
                    // Se elimina el color fijo negro para permitir que JS gestione el hover dinámicamente
                ],
            ],
            'labels' => ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            onClick: (event, elements, chart) => {
                if (elements && elements.length > 0) {
                    const activeElement = elements[0];
                    const dataIndex = activeElement.index;
                    const label = chart.data.labels[dataIndex];

                    $wire.handleChartClick({
                        mes: label,
                        indice: dataIndex
                    });
                }
            },
            onHover: (event, chartElement) => {
                event.native.target.style.cursor = chartElement[0] ? 'pointer' : 'default';
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        footer: () => 'Haz clic para alternar entre Afiliaciones y Afiliados'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            }
        }
        JS);
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
