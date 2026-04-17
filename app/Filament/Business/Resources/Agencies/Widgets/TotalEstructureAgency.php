<?php

namespace App\Filament\Business\Resources\Agencies\Widgets;

use App\Filament\Business\Resources\Agencies\Concerns\HasAgencyResourceChartTimeStateFilters;
use App\Models\Agency;
use App\Models\Sale;
use Carbon\Carbon;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class TotalEstructureAgency extends ChartWidget
{
    use HasAgencyResourceChartTimeStateFilters;

    protected string $view = 'filament.widgets.total-estructure-agency-chart';

    protected string $color = 'gray';

    protected ?string $heading = 'Ventas totales por estructura';

    protected ?string $description = 'Por defecto se muestran todas las agencias consolidadas. Elige una Master o General para el detalle. Ajusta año y mes.';

    protected ?string $maxHeight = '440px';

    protected int|string|array $columnSpan = 'full';

    public ?string $filter = null;

    public function mount(): void
    {
        parent::mount();

        FilamentAsset::register([
            Js::make('chartjs-datalabels', 'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js'),
        ]);

        $this->bootAgencyChartFilters();
        $this->filter ??= '';
    }

    /**
     * Fuerza la recreación del canvas cuando cambian los filtros propios del widget.
     *
     * Importante: este widget **no** respeta filtros/búsqueda/orden de la tabla. Solo usa:
     * - selector de agencia (`filter`)
     * - año/mes (propios del widget) vía {@see HasAgencyResourceChartTimeStateFilters}
     */
    public function getEstructureChartWireKey(): string
    {
        $payload = [
            'filter_select' => $this->filter,
            'chartYear' => $this->chartYear,
            'chartMonth' => $this->chartMonth,
        ];

        return 'agency-estructure-bar-'.hash('xxh128', (string) json_encode($payload));
    }

    /**
     * @return array<string, string>
     */
    public function getChartStateSelectOptions(): array
    {
        return [];
    }

    protected function getFilters(): ?array
    {
        $builder = function () {
            return Agency::query()
                ->whereIn('agency_type_id', [1, 3])
                ->where('status', 'ACTIVO')
                ->orderBy('agency_type_id')
                ->orderBy('name_corporative')
                ->get();
        };

        $prepend = ['' => 'Todas las agencias (consolidado)'];

        $agencies = $builder();

        return $prepend + $agencies->mapWithKeys(function (Agency $a) {
            $tipo = $a->agency_type_id === 1 ? 'Master' : 'General';

            return [$a->code => ($a->name_corporative ?? $a->code)." ({$tipo})"];
        })->all();
    }

    /**
     * @return list<string>
     */
    protected function masterOwnerCodesForAggregate(): array
    {
        $agencies = Agency::query()
            ->whereIn('agency_type_id', [1, 3])
            ->where('status', 'ACTIVO')
            ->get();

        $masters = collect();
        foreach ($agencies as $a) {
            if ((int) $a->agency_type_id === 1 && $a->owner_code === $a->code) {
                $masters->push($a->code);
            } else {
                $masters->push($a->owner_code);
            }
        }

        return $masters->filter()->unique()->values()->all();
    }

    protected function getData(): array
    {
        $year = $this->resolvedChartYear();
        $month = $this->resolvedChartMonth();

        $selectedCode = $this->filter;

        if ($selectedCode === null || $selectedCode === '') {
            return $this->getConsolidatedStructureData($year, $month);
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

        return $this->buildStructureDataset(
            $masterCode,
            $year,
            $month
        );
    }

    /**
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    protected function getConsolidatedStructureData(int $year, ?int $month): array
    {
        $masterCodes = $this->masterOwnerCodesForAggregate();

        if ($masterCodes === []) {
            return [
                'labels' => ['Agencia Master (directas)', 'Agencias Generales (directas)', 'Agentes'],
                'datasets' => [
                    [
                        'label' => $this->datasetLabelForYear($year, $month),
                        'data' => [0.0, 0.0, 0.0],
                        'backgroundColor' => ['#3b82f6', '#10b981', '#f59e0b'],
                        'borderColor' => 'rgba(0,0,0,0.08)',
                        'borderWidth' => 1,
                        'borderRadius' => 6,
                        'barPercentage' => 0.7,
                    ],
                ],
            ];
        }

        $ventasDirectasMaster = (float) Sale::query()
            ->whereNull('agent_id')
            ->whereColumn('code_agency', 'owner_code')
            ->whereIn('owner_code', $masterCodes)
            ->whereYear('created_at', $year)
            ->when($month, fn ($q) => $q->whereMonth('created_at', $month))
            ->sum('total_amount');

        $generalCodes = Agency::query()
            ->where('agency_type_id', 3)
            ->whereIn('owner_code', $masterCodes)
            ->pluck('code')
            ->all();

        $ventasDirectasGenerales = $generalCodes === []
            ? 0.0
            : (float) Sale::query()
                ->whereNull('agent_id')
                ->whereIn('code_agency', $generalCodes)
                ->whereIn('owner_code', $masterCodes)
                ->whereYear('created_at', $year)
                ->when($month, fn ($q) => $q->whereMonth('created_at', $month))
                ->sum('total_amount');

        $ventasAgentes = (float) Sale::query()
            ->whereNotNull('agent_id')
            ->whereIn('owner_code', $masterCodes)
            ->whereYear('created_at', $year)
            ->when($month, fn ($q) => $q->whereMonth('created_at', $month))
            ->sum('total_amount');

        return [
            'labels' => ['Agencia Master (directas)', 'Agencias Generales (directas)', 'Agentes'],
            'datasets' => [
                [
                    'label' => $this->datasetLabelForYear($year, $month),
                    'data' => [$ventasDirectasMaster, $ventasDirectasGenerales, $ventasAgentes],
                    'backgroundColor' => ['#3b82f6', '#10b981', '#f59e0b'],
                    'borderColor' => 'rgba(0,0,0,0.08)',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                    'barPercentage' => 0.7,
                ],
            ],
        ];
    }

    /**
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    protected function buildStructureDataset(string $masterCode, int $year, ?int $month): array
    {
        $generalCodes = Agency::query()
            ->where('owner_code', $masterCode)
            ->where('agency_type_id', 3)
            ->pluck('code')
            ->toArray();

        $baseSaleQuery = function () use ($masterCode, $year, $month) {
            return Sale::query()
                ->where('owner_code', $masterCode)
                ->whereYear('created_at', $year)
                ->when($month, fn ($q2) => $q2->whereMonth('created_at', $month));
        };

        $ventasDirectasMaster = (float) $baseSaleQuery()
            ->whereNull('agent_id')
            ->where('code_agency', $masterCode)
            ->sum('total_amount');

        $ventasDirectasGenerales = $generalCodes === []
            ? 0.0
            : (float) $baseSaleQuery()
                ->whereNull('agent_id')
                ->whereIn('code_agency', $generalCodes)
                ->sum('total_amount');

        $ventasAgentes = (float) $baseSaleQuery()
            ->whereNotNull('agent_id')
            ->sum('total_amount');

        return [
            'labels' => ['Agencia Master (directas)', 'Agencias Generales (directas)', 'Agentes'],
            'datasets' => [
                [
                    'label' => $this->datasetLabelForYear($year, $month),
                    'data' => [$ventasDirectasMaster, $ventasDirectasGenerales, $ventasAgentes],
                    'backgroundColor' => ['#3b82f6', '#10b981', '#f59e0b'],
                    'borderColor' => 'rgba(0,0,0,0.08)',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                    'barPercentage' => 0.7,
                ],
            ],
        ];
    }

    protected function datasetLabelForYear(int $year, ?int $month): string
    {
        if ($month !== null) {
            $nombre = ucfirst(Carbon::createFromDate($year, $month, 1)->locale(app()->getLocale())->translatedFormat('F'));

            return "Ventas (USD) {$year} · {$nombre}";
        }

        return "Ventas (USD) {$year}";
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
                            color: () => document.documentElement.classList.contains('dark') ? '#f1f5f9' : '#0f172a',
                            callback: (value) => '$' + Number(value).toLocaleString()
                        },
                        grid: { color: 'rgba(156, 163, 175, 0.2)', drawBorder: false }
                    },
                    x: {
                        ticks: {
                            color: () => document.documentElement.classList.contains('dark') ? '#f1f5f9' : '#0f172a',
                            maxRotation: 45,
                            minRotation: 35,
                            font: { size: 11 },
                            callback: function(value) {
                                var label = this.getLabelForValue(value);
                                return label.length > 20 ? label.substring(0, 18) + '…' : label;
                            }
                        },
                        grid: { display: false }
                    }
                },
                plugins: {
                    legend: { display: false },
                    datalabels: {
                        display: true,
                        color: () => document.documentElement.classList.contains('dark') ? '#f1f5f9' : '#1e293b',
                        anchor: 'end',
                        align: 'top',
                        offset: 4,
                        font: { weight: '700', size: 11 },
                        formatter: (value) => '$' + Number(value).toLocaleString('es-VE', { minimumFractionDigits: 2 })
                    },
                    tooltip: {
                        backgroundColor: () => document.documentElement.classList.contains('dark') ? 'rgba(30, 41, 59, 0.96)' : 'rgba(255, 255, 255, 0.98)',
                        titleColor: () => document.documentElement.classList.contains('dark') ? '#f1f5f9' : '#0f172a',
                        bodyColor: () => document.documentElement.classList.contains('dark') ? '#e2e8f0' : '#334155',
                        borderWidth: 1,
                        borderColor: () => document.documentElement.classList.contains('dark') ? 'rgba(248, 250, 252, 0.2)' : 'rgba(0, 0, 0, 0.08)',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: (context) => ' Total: $' + Number(context.raw ?? 0).toLocaleString('es-VE')
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
}
