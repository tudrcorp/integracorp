<?php

namespace App\Filament\Business\Resources\ProspectAgents\Widgets;

use App\Models\ProspectAgent;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class ReferenceProspect extends ChartWidget
{
    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Prospectos por referido';

    protected ?string $description = 'Total de prospectos registrados por canal de referencia.';

    protected ?string $maxHeight = '320px';

    private const REFERENCE_LABELS = [
        'directiva-TDG' => 'Directiva TDG',
        'gerencia-de-negocios' => 'Gerencia de Negocios',
        'whatsapp-comercial' => 'Whatsapp Comercial',
        'redes-sociales' => 'Redes sociales',
        'tercero' => 'Tercero',
        'otro' => 'Otro',
    ];

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $distribution = ProspectAgent::query()
            ->selectRaw('reference_by, COUNT(*) as total')
            ->groupBy('reference_by')
            ->orderByDesc('total')
            ->pluck('total', 'reference_by')
            ->toArray();

        $labels = [];
        $data = [];
        $colors = [];

        foreach ($distribution as $referenceBy => $total) {
            $labels[] = self::REFERENCE_LABELS[$referenceBy] ?? $referenceBy;
            $data[] = $total;
            $colors[] = $this->randomHexColor();
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Prospectos',
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderColor' => $colors,
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                    'borderSkipped' => false,
                ],
            ],
        ];
    }

    private function randomHexColor(): string
    {
        $r = random_int(60, 220);
        $g = random_int(60, 220);
        $b = random_int(60, 220);

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            responsive: true,
            maintainAspectRatio: false,
            // layout: {
            //     padding: { top: 16, right: 16, bottom: 16, left: 16 }
            // },
            scales: {
                x: {
                    grid: {
                        display: true,
                        drawBorder: true,
                        color: 'rgba(156, 163, 175, 0.25)',
                    },
                    ticks: {
                        maxRotation: 45,
                        minRotation: 0,
                        font: { size: 11 }
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        display: true,
                        drawBorder: true,
                        color: 'rgba(156, 163, 175, 0.25)',
                    },
                    ticks: {
                        stepSize: 1,
                        font: { size: 11 }
                    }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#1e293b',
                    bodyColor: '#1e293b',
                    borderColor: '#e2e8f0',
                    borderWidth: 1,
                    padding: 12,
                    callbacks: {
                        label: (context) => ' Total: ' + context.raw + ' prospecto(s)'
                    }
                }
            },
            animation: {
                duration: 800,
                easing: 'easeOutQuart'
            }
        }
        JS);
    }
}
