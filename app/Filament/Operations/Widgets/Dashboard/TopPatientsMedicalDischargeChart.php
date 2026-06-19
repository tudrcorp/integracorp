<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\Dashboard;

use App\Support\Operations\OperationsDashboardMetrics;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class TopPatientsMedicalDischargeChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.operations.widgets.top-patients-medical-discharge-chart';

    protected static ?int $sort = 2;

    protected ?string $heading = 'PACIENTES MÁS ATENDIDOS (ALTA MÉDICA)';

    protected ?string $description = 'Top 20 pacientes con más casos de alta médica. Haz clic en una barra para ver el detalle de sus casos.';

    protected ?string $maxHeight = '460px';

    protected int|string|array $columnSpan = 'full';

    public ?int $selectedPatientId = null;

    public ?string $selectedPatientName = null;

    /**
     * Índices del gráfico overview → ID de paciente (para el clic en barras).
     *
     * @var array<int, int>
     */
    public array $chartPatientIds = [];

    public function handleChartClick(array $payload): void
    {
        if ($this->selectedPatientId !== null) {
            return;
        }

        $index = (int) ($payload['index'] ?? -1);
        $patientId = $this->chartPatientIds[$index] ?? null;

        if ($patientId === null || $patientId <= 0) {
            return;
        }

        $patientName = OperationsDashboardMetrics::topPatientsByMedicalDischargeCases(20)
            ->firstWhere('telemedicine_patient_id', $patientId)
            ?->full_name;

        $this->selectedPatientId = $patientId;
        $this->selectedPatientName = filled($patientName)
            ? (string) $patientName
            : "Paciente #{$patientId}";

        $this->refreshChart();
    }

    public function resetToPatientsOverview(): void
    {
        $this->selectedPatientId = null;
        $this->selectedPatientName = null;
        $this->chartPatientIds = [];
        $this->refreshChart();
    }

    protected function refreshChart(): void
    {
        $this->cachedData = null;
        $this->updateChartData();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        if ($this->selectedPatientId !== null) {
            return $this->buildPatientCasesChart($this->selectedPatientId);
        }

        return $this->buildTopPatientsChart();
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildTopPatientsChart(): array
    {
        $topPatients = OperationsDashboardMetrics::topPatientsByMedicalDischargeCases(20);

        $labels = [];
        $values = [];
        $names = [];
        $this->chartPatientIds = [];

        foreach ($topPatients as $row) {
            $name = filled($row->full_name)
                ? (string) $row->full_name
                : "Paciente #{$row->telemedicine_patient_id}";

            $labels[] = mb_strlen($name) > 28 ? mb_substr($name, 0, 25).'…' : $name;
            $names[] = $name;
            $values[] = (int) $row->total;
            $this->chartPatientIds[] = (int) $row->telemedicine_patient_id;
        }

        return [
            'datasets' => [
                $this->makeBarDataset('Casos de alta médica', $values, [
                    'names' => $names,
                ]),
            ],
            'labels' => $labels,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPatientCasesChart(int $patientId): array
    {
        $cases = OperationsDashboardMetrics::medicalDischargeCasesForPatient($patientId);

        $labels = [];
        $values = [];
        $names = [];

        foreach ($cases as $case) {
            $label = filled($case->code) ? (string) $case->code : "Caso #{$case->id}";
            $date = $case->updated_at?->format('d/m/Y') ?? $case->created_at?->format('d/m/Y') ?? '—';

            $labels[] = $label;
            $names[] = "{$label} · {$date}";
            $values[] = 1;
        }

        return [
            'datasets' => [
                $this->makeBarDataset(
                    "Altas médicas · {$this->selectedPatientName}",
                    $values,
                    ['names' => $names],
                ),
            ],
            'labels' => $labels,
        ];
    }

    /**
     * @param  array<int, int|float>  $data
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function makeBarDataset(string $label, array $data, array $extra = []): array
    {
        return array_merge([
            'label' => $label,
            'data' => $data,
            'backgroundColor' => $this->buildBackgroundColors(count($data)),
            'borderColor' => 'rgba(0,0,0,0.08)',
            'borderWidth' => 1.25,
            'borderRadius' => 10,
            'borderSkipped' => false,
        ], $extra);
    }

    /**
     * @return array<int, string>
     */
    protected function buildBackgroundColors(int $count): array
    {
        $palette = [
            '#38bdf8', '#0ea5e9', '#0284c7', '#0369a1', '#075985',
            '#7dd3fc', '#06b6d4', '#0891b2', '#0e7490', '#22d3ee',
            '#2dd4bf', '#14b8a6', '#10b981', '#34d399', '#4ade80',
            '#60a5fa', '#818cf8', '#a78bfa', '#c084fc', '#f472b6',
        ];

        $colors = [];

        for ($index = 0; $index < $count; $index++) {
            $colors[] = $palette[$index % count($palette)];
        }

        return $colors;
    }

    protected function getOptions(): RawJs
    {
        $tooltipFooter = $this->selectedPatientId === null
            ? 'Haz clic para ver los casos del paciente'
            : '';

        $options = <<<'JS'
        {
            onClick: (event, elements) => {
                if (!elements || !elements.length) {
                    return;
                }

                $wire.handleChartClick({
                    index: elements[0].index
                });
            },
            onHover: (event, chartElement) => {
                event.native.target.style.cursor = chartElement[0] ? 'pointer' : 'default';
            },
            interaction: {
                mode: 'nearest',
                intersect: true,
                axis: 'xy'
            },
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: { top: 8, right: 8, bottom: 4, left: 4 }
            },
            datasets: {
                bar: {
                    categoryPercentage: 0.9,
                    barPercentage: 0.95
                }
            },
            elements: {
                bar: {
                    borderWidth: 1.25,
                    borderRadius: 10,
                    hoverBorderWidth: 2.5,
                    hoverBorderColor: 'rgba(255, 255, 255, 0.92)'
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(22, 22, 24, 0.56)',
                    titleColor: '#f5f5f7',
                    bodyColor: 'rgba(235, 235, 245, 0.88)',
                    footerColor: 'rgba(235, 235, 245, 0.7)',
                    borderColor: 'rgba(255, 255, 255, 0.2)',
                    borderWidth: 1,
                    padding: 10,
                    cornerRadius: 12,
                    callbacks: {
                        title: function(context) {
                            const item = context[0];
                            const dataset = item.dataset || {};

                            if (dataset.names && dataset.names[item.dataIndex]) {
                                return dataset.names[item.dataIndex];
                            }

                            return item.label;
                        },
                        label: function(context) {
                            return ' Casos: ' + context.raw;
                        },
                        footer: () => '__TOOLTIP_FOOTER__'
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: true,
                        drawBorder: false,
                        color: 'rgba(120, 120, 128, 0.1)'
                    },
                    ticks: {
                        maxRotation: 45,
                        minRotation: 0
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0, stepSize: 1 },
                    grid: {
                        display: true,
                        drawBorder: false,
                        color: 'rgba(120, 120, 128, 0.12)'
                    }
                }
            },
            animation: {
                duration: 700,
                easing: 'easeOutQuart'
            }
        }
        JS;

        return RawJs::make(str_replace('__TOOLTIP_FOOTER__', $tooltipFooter, $options));
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
