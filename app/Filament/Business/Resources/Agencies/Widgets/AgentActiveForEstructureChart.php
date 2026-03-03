<?php

namespace App\Filament\Business\Resources\Agencies\Widgets;

use App\Models\Agency;
use App\Models\Agent;
use Filament\Notifications\Notification;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class AgentActiveForEstructureChart extends ChartWidget
{
    protected ?string $heading = 'Agentes activos por agencia';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '500px';

    // Estado para controlar si estamos viendo el detalle de una agencia
    public ?string $selectedAgencyCode = null;

    public ?string $selectedAgencyName = null;

    // Clave para forzar el refresco del componente
    public int $chartKey = 0;

    protected function getData(): array
    {
        // CASO: Vista de Detalle (Activos vs Inactivos de la agencia seleccionada)
        if ($this->selectedAgencyCode) {
            $stats = Agent::query()
                ->select([
                    'status',
                    DB::raw('COUNT(*) as total'),
                ])
                ->where('owner_code', $this->selectedAgencyCode)
                ->groupBy('status')
                ->get()
                ->pluck('total', 'status');

            $activeCount = (int) ($stats['ACTIVO'] ?? 0);
            $inactiveCount = (int) ($stats['INACTIVO'] ?? 0);

            return [
                'datasets' => [
                    [
                        'label' => 'Estado de Agentes',
                        'data' => [$activeCount, $inactiveCount],
                        'backgroundColor' => ['#22c55e', '#ef4444'], // Verde para Activos, Rojo para Inactivos
                        'borderColor' => ['#16a34a', '#dc2626'],
                        'borderWidth' => 1,
                        'borderRadius' => 6,
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
            ->leftJoin('agents', function ($join): void {
                $join
                    ->on('agents.owner_code', '=', 'agencies.code')
                    ->where('agents.status', 'ACTIVO');
            })
            ->groupBy('agencies.code', 'agencies.name_corporative')
            ->having('total_agents', '>', 0)
            ->orderByDesc('total_agents')
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
                    'borderWidth' => 1,
                    'borderRadius' => 4,
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

                    $wire.handleChartClick({
                        label: label,
                        index: index
                    });
                }
            },
            onHover: (event, chartElement) => {
                event.native.target.style.cursor = chartElement[0] ? 'pointer' : 'default';
            },
            barPercentage: 0.6,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        footer: () => 'Clic para ver detalles de activos/inactivos o regresar'
                    }
                }
            },
            scales: {
                y: { 
                    beginAtZero: true,
                    grid: { display: true }
                },
                x: { grid: { display: true } }
            }
        }
        JS);
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
