<?php

namespace App\Filament\Business\Resources\Agencies\Widgets;

use App\Filament\Business\Resources\Agencies\Pages\ListAgencies;
use App\Models\Agency;
use App\Models\Sale;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;

class TotalEstructureAgency extends ChartWidget
{
    use InteractsWithPageTable;

    protected ?string $heading = 'Ventas totales por estructura';

    protected ?string $description = 'Seleccione una agencia Master o General en el filtro para ver la jerarquía de ventas del año en curso: ventas directas de la agencia master, ventas directas de sus agencias generales y ventas de los agentes. El listado respeta los filtros de la tabla.';

    protected ?string $maxHeight = '500px';

    protected int|string|array $columnSpan = 'full';

    /**
     * Código de la agencia seleccionada en el filtro (Master o General).
     */
    public ?string $filter = null;

    protected function getTablePage(): string
    {
        return ListAgencies::class;
    }

    /**
     * Opciones del select: agencias Master y General activas.
     * Usa la tabla filtrada cuando está disponible; si no hay resultados, fallback a todas.
     */
    protected function getFilters(): ?array
    {
        $builder = function ($query) {
            return $query
                ->whereIn('agency_type_id', [1, 3])
                ->where('status', 'ACTIVO')
                ->orderBy('agency_type_id')
                ->orderBy('name_corporative')
                ->get();
        };

        try {
            $agencies = $builder($this->getPageTableQuery()->clone());
            if ($agencies->isNotEmpty()) {
                return $agencies->mapWithKeys(function (Agency $a) {
                    $tipo = $a->agency_type_id === 1 ? 'Master' : 'General';

                    return [$a->code => ($a->name_corporative ?? $a->code)." ({$tipo})"];
                })->toArray();
            }
        } catch (\Throwable $e) {
            // Fallback si la tabla no está disponible
        }

        $agencies = $builder(Agency::query());

        return $agencies->mapWithKeys(function (Agency $a) {
            $tipo = $a->agency_type_id === 1 ? 'Master' : 'General';

            return [$a->code => ($a->name_corporative ?? $a->code)." ({$tipo})"];
        })->toArray();
    }

    /**
     * Jerarquía: En agencies, code es el identificador; si owner_code == code es Master (agency_type_id=1)
     * o General (agency_type_id=3). Las generales tienen owner_code = code de la Master.
     * En agents, owner_code es el code de la agencia a la que pertenece el agente; se valida en
     * agencies para saber si pertenece a una Master o a una General.
     * Gráfico: 3 barras = ventas directas Master + ventas directas Generales + ventas de Agentes.
     * Todas las ventas se filtran por el año en curso (created_at).
     */
    protected function getData(): array
    {
        $selectedCode = $this->filter;

        if (empty($selectedCode)) {
            return [
                'labels' => [],
                'datasets' => [
                    [
                        'label' => 'Ventas (USD)',
                        'data' => [],
                        'backgroundColor' => [],
                    ],
                ],
            ];
        }

        $agency = Agency::query()
            ->where('code', $selectedCode)
            ->whereIn('agency_type_id', [1, 3])
            ->first();

        if (! $agency) {
            return [
                'labels' => ['Sin datos'],
                'datasets' => [['label' => 'Ventas (USD)', 'data' => [0], 'backgroundColor' => ['#e5e7eb']]],
            ];
        }

        $masterCode = $agency->agency_type_id === 1 ? $agency->code : $agency->owner_code;
        $yearEnCurso = now()->year;

        $generalCodes = Agency::query()
            ->where('owner_code', $masterCode)
            ->where('agency_type_id', 3)
            ->pluck('code')
            ->toArray();

        $baseSaleQuery = fn () => Sale::query()
            ->where('owner_code', $masterCode)
            ->whereYear('created_at', $yearEnCurso);

        $ventasDirectasMaster = (float) (clone $baseSaleQuery())
            ->whereNull('agent_id')
            ->where('code_agency', $masterCode)
            ->sum('total_amount');

        $ventasDirectasGenerales = $generalCodes === []
            ? 0.0
            : (float) (clone $baseSaleQuery())
                ->whereNull('agent_id')
                ->whereIn('code_agency', $generalCodes)
                ->sum('total_amount');

        $ventasAgentes = (float) (clone $baseSaleQuery())
            ->whereNotNull('agent_id')
            ->sum('total_amount');

        $labels = ['Agencia Master (directas)', 'Agencias Generales (directas)', 'Agentes'];
        $data = [$ventasDirectasMaster, $ventasDirectasGenerales, $ventasAgentes];
        $colors = ['#3b82f6', '#10b981', '#f59e0b'];

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => "Ventas (USD) {$yearEnCurso}",
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderColor' => 'rgba(0,0,0,0.08)',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                    'barPercentage' => 0.7,
                    'categoryPercentage' => 0.85,
                ],
            ],
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: function() {
                                return document.documentElement.classList.contains('dark') ? '#f1f5f9' : '#0f172a';
                            },
                            callback: function(value) {
                                return '$' + Number(value).toLocaleString();
                            }
                        },
                        grid: {
                            color: 'rgba(156, 163, 175, 0.2)',
                            drawBorder: false
                        }
                    },
                    x: {
                        ticks: {
                            color: function() {
                                return document.documentElement.classList.contains('dark') ? '#f1f5f9' : '#0f172a';
                            },
                            maxRotation: 45,
                            minRotation: 35,
                            font: { size: 11 },
                            callback: function(value, index, values) {
                                var label = this.getLabelForValue(value);
                                return label.length > 20 ? label.substring(0, 18) + '…' : label;
                            }
                        },
                        grid: {
                            display: false,
                            drawBorder: false
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: function(context) {
                            return document.documentElement.classList.contains('dark') ? 'rgba(30, 41, 59, 0.96)' : 'rgba(255, 255, 255, 0.98)';
                        },
                        titleColor: function() {
                            return document.documentElement.classList.contains('dark') ? '#f1f5f9' : '#0f172a';
                        },
                        bodyColor: function() {
                            return document.documentElement.classList.contains('dark') ? '#e2e8f0' : '#334155';
                        },
                        borderWidth: 1,
                        borderColor: function() {
                            return document.documentElement.classList.contains('dark') ? 'rgba(248, 250, 252, 0.2)' : 'rgba(0, 0, 0, 0.08)';
                        },
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                var value = context.raw ?? 0;
                                return ' Total: $' + Number(value).toLocaleString('es-VE');
                            }
                        }
                    }
                }
            }
        JS);
    }

    protected function getType(): string
    {
        return 'bar';
    }

    public function mount(): void
    {
        if ($this->filter === null || $this->filter === '') {
            $firstMaster = Agency::query()
                ->where('agency_type_id', 1)
                ->where('status', 'ACTIVO')
                ->whereColumn('owner_code', 'code')
                ->orderBy('name_corporative')
                ->value('code');
            $this->filter = $firstMaster ?? '';
        }
    }
}
