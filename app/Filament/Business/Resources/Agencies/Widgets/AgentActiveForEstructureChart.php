<?php

namespace App\Filament\Business\Resources\Agencies\Widgets;

use App\Filament\Business\Resources\Agencies\Concerns\HasAgencyResourceChartTimeStateFilters;
use App\Models\Agency;
use App\Models\Agent;
use Filament\Notifications\Notification;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class AgentActiveForEstructureChart extends ChartWidget
{
    use HasAgencyResourceChartTimeStateFilters;

    protected string $view = 'filament.widgets.agent-active-for-estructure-chart';

    protected string $color = 'gray';

    protected ?string $heading = 'Agentes activos por agencia';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '440px';

    // Estado para controlar si estamos viendo el detalle de una agencia
    public ?string $selectedAgencyCode = null;

    public ?string $selectedAgencyName = null;

    // Clave para forzar el refresco del componente
    public int $chartKey = 0;

    public function mount(): void
    {
        parent::mount();
        $this->bootAgencyChartFilters();
    }

    /**
     * @return array<string, string>
     */
    public function getChartStateSelectOptions(): array
    {
        return [];
    }

    protected function getData(): array
    {
        $year = $this->resolvedChartYear();
        $month = $this->resolvedChartMonth();

        // CASO: Vista de Detalle (Activos vs Inactivos de la agencia seleccionada)
        if ($this->selectedAgencyCode) {
            $stats = Agent::query()
                ->select([
                    'agents.status',
                    DB::raw('COUNT(*) as total'),
                ])
                ->join('agencies', 'agencies.code', '=', 'agents.owner_code')
                ->where('agents.owner_code', $this->selectedAgencyCode)
                ->whereYear('agents.created_at', $year)
                ->when($month, fn ($q) => $q->whereMonth('agents.created_at', $month))
                ->groupBy('agents.status')
                ->get()
                ->pluck('total', 'status');

            $activeCount = (int) ($stats['ACTIVO'] ?? 0);
            $inactiveCount = (int) ($stats['INACTIVO'] ?? 0);

            return [
                'datasets' => [
                    [
                        'label' => 'Estado de Agentes',
                        'data' => [$activeCount, $inactiveCount],
                        'backgroundColor' => ['#22c55e', '#ef4444'],
                        'borderColor' => ['#16a34a', '#dc2626'],
                        'borderWidth' => 1.25,
                        'borderRadius' => 8,
                        'borderSkipped' => false,
                    ],
                ],
                'labels' => ['ACTIVOS', 'INACTIVOS'],
                'key' => $this->chartKey,
            ];
        }

        // CASO: Vista General (Todas las agencias con agentes activos)
        $agenciesWithActiveAgents = Agency::query()
            ->select([
                'agencies.code',
                'agencies.name_corporative',
                DB::raw('COUNT(agents.id) as total_agents'),
            ])
            ->leftJoin('agents', function ($join) use ($year, $month): void {
                $join
                    ->on('agents.owner_code', '=', 'agencies.code')
                    ->where('agents.status', 'ACTIVO')
                    ->whereYear('agents.created_at', $year);
                if ($month) {
                    $join->whereMonth('agents.created_at', $month);
                }
            })
            ->groupBy('agencies.code', 'agencies.name_corporative')
            ->having('total_agents', '>', 0)
            ->orderByDesc('total_agents')
            ->limit(20)
            ->get();

        $labels = $agenciesWithActiveAgents
            ->pluck('name_corporative')
            ->map(static fn (?string $name): string => $name ?? 'Sin nombre')
            ->toArray();

        $data = $agenciesWithActiveAgents
            ->pluck('total_agents')
            ->map(static fn (mixed $value): int => (int) $value)
            ->toArray();

        $palette = [
            '#4ade80',
            '#60a5fa',
            '#fbbf24',
            '#f87171',
            '#c084fc',
            '#2dd4bf',
            '#fb923c',
            '#a78bfa',
            '#f472b6',
            '#94a3b8',
        ];

        $backgroundColors = [];
        foreach ($data as $index => $value) {
            $backgroundColors[] = $palette[$index % count($palette)];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total de agentes activos',
                    'data' => $data,
                    'backgroundColor' => $backgroundColors,
                    'borderColor' => 'rgba(0,0,0,0.1)',
                    'borderWidth' => 1.25,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                ],
            ],
            'labels' => $labels,
            'key' => $this->chartKey,
        ];
    }

    /**
     * Procesa el clic para profundizar o regresar
     */
    public function handleChartClick(array $payload): void
    {
        $label = $payload['label'] ?? null;
        if (! $label) {
            return;
        }

        if ($this->selectedAgencyCode === null) {
            // Intentar encontrar la agencia por nombre (label)
            $agency = Agency::where('name_corporative', $label)->first();

            if ($agency) {
                $this->selectedAgencyCode = $agency->code;
                $this->selectedAgencyName = $agency->name_corporative;
                $this->heading = "Estado de Agentes: {$agency->name_corporative}";

                Notification::make()
                    ->title("Cargando detalles de {$agency->name_corporative}")
                    ->info()
                    ->send();
            }
        } else {
            // Si ya estamos en detalle, el clic regresa a la vista general
            $this->selectedAgencyCode = null;
            $this->selectedAgencyName = null;
            $this->heading = 'Agentes activos por agencia';

            Notification::make()
                ->title('Volviendo a vista general')
                ->success()
                ->send();
        }

        $this->chartKey++;
        $this->updateChartData();
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            onClick: (event, elements, chart) => {
                if (elements && elements.length > 0) {
                    const index = elements[0].index;
                    const label = chart.data.labels[index];
                    $wire.handleChartClick({ label: label, index: index });
                }
            },
            onHover: (event, chartElement) => {
                event.native.target.style.cursor = chartElement[0] ? 'pointer' : 'default';
            },
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: { top: 8, right: 8, bottom: 4, left: 4 }
            },
            interaction: {
                mode: 'nearest',
                intersect: true,
                axis: 'xy'
            },
            datasets: {
                bar: {
                    categoryPercentage: 0.92,
                    barPercentage: 0.98
                }
            },
            elements: {
                bar: {
                    borderWidth: 1.25,
                    borderRadius: 10,
                    inflateAmount: 0.6,
                    hoverBorderWidth: 2.5,
                    hoverBorderColor: 'rgba(255, 255, 255, 0.92)'
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: true,
                    position: 'nearest',
                    xAlign: 'center',
                    yAlign: 'bottom',
                    backgroundColor: 'rgba(22, 22, 24, 0.56)',
                    titleColor: '#f5f5f7',
                    bodyColor: 'rgba(235, 235, 245, 0.88)',
                    footerColor: 'rgba(235, 235, 245, 0.7)',
                    borderColor: 'rgba(255, 255, 255, 0.2)',
                    borderWidth: 1,
                    padding: 10,
                    cornerRadius: 12,
                    caretSize: 6,
                    caretPadding: 8,
                    titleFont: {
                        size: 14,
                        weight: '700',
                        family: '-apple-system, BlinkMacSystemFont, system-ui, sans-serif'
                    },
                    bodyFont: {
                        size: 13,
                        weight: '500',
                        family: '-apple-system, BlinkMacSystemFont, system-ui, sans-serif'
                    },
                    titleSpacing: 0,
                    titleMarginBottom: 8,
                    bodySpacing: 6,
                    footerSpacing: 8,
                    displayColors: true,
                    usePointStyle: true,
                    boxWidth: 12,
                    boxHeight: 12,
                    boxPadding: 8,
                    multiKeyBackground: 'rgba(255, 255, 255, 0.08)',
                    callbacks: {
                        footer: () => 'Clic para ver detalles de activos/inactivos o regresar'
                    }
                }
            },
            scales: {
                x: {
                    stacked: false,
                    grid: {
                        display: true,
                        drawBorder: false,
                        color: 'rgba(120, 120, 128, 0.1)'
                    },
                    ticks: {
                        maxRotation: 45,
                        minRotation: 0,
                        color: '#8e8e93',
                        font: {
                            size: 10,
                            family: '-apple-system, BlinkMacSystemFont, system-ui, sans-serif'
                        }
                    }
                },
                y: {
                    stacked: false,
                    beginAtZero: true,
                    grid: {
                        display: true,
                        drawBorder: false,
                        color: 'rgba(120, 120, 128, 0.12)'
                    },
                    ticks: {
                        precision: 0,
                        stepSize: 1,
                        color: '#8e8e93',
                        font: {
                            size: 10,
                            family: '-apple-system, BlinkMacSystemFont, system-ui, sans-serif'
                        },
                        callback: (value) => Number(value).toLocaleString()
                    }
                }
            },
            animation: {
                duration: 900,
                easing: 'easeOutQuart'
            }
        }
        JS);
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
