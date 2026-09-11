<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\Dashboard;

use App\Support\Operations\OperationsDashboardMetrics;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class TopPatientsMedicalDischargeChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.operations.widgets.top-patients-medical-discharge-chart';

    protected static ?int $sort = 2;

    protected ?string $heading = 'PACIENTES MÁS ATENDIDOS (ALTA MÉDICA)';

    protected ?string $description = 'Top 20 pacientes con más casos de alta médica. Haz clic en una barra para ver el detalle de sus casos.';

    protected ?string $maxHeight = '460px';

    protected int|string|array $columnSpan = 'full';

    public ?string $selectedPatientKey = null;

    public ?string $selectedPatientName = null;

    /**
     * Índices del gráfico overview → clave del paciente en la tabla de hechos.
     *
     * @var array<int, string>
     */
    public array $chartPatientKeys = [];

    public function handleChartClick(array $payload): void
    {
        if ($this->selectedPatientKey !== null) {
            return;
        }

        $index = (int) ($payload['index'] ?? -1);
        $patientKey = $this->chartPatientKeys[$index] ?? null;

        if (! is_string($patientKey) || $patientKey === '') {
            return;
        }

        $patientName = OperationsDashboardMetrics::topPatientsByMedicalDischargeCases(20)
            ->firstWhere('patient_key', $patientKey)
            ?->full_name;

        $this->selectedPatientKey = $patientKey;
        $this->selectedPatientName = filled($patientName)
            ? (string) $patientName
            : 'Paciente';

        $this->refreshChart();
    }

    public function resetToPatientsOverview(): void
    {
        $this->selectedPatientKey = null;
        $this->selectedPatientName = null;
        $this->chartPatientKeys = [];
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
        if ($this->selectedPatientKey !== null) {
            return $this->buildPatientCasesChart($this->selectedPatientKey);
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
        $this->chartPatientKeys = [];

        foreach ($topPatients as $row) {
            $name = filled($row->full_name)
                ? (string) $row->full_name
                : 'Paciente';

            $labels[] = mb_strlen($name) > 28 ? mb_substr($name, 0, 25).'…' : $name;
            $names[] = $name;
            $values[] = (int) $row->total;
            $this->chartPatientKeys[] = (string) $row->patient_key;
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

    public function getDescription(): string|Htmlable|null
    {
        if ($this->selectedPatientKey !== null) {
            return 'Pasa el cursor sobre cada barra para ver el resumen del caso.';
        }

        return $this->description;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPatientCasesChart(string $patientKey): array
    {
        $cases = OperationsDashboardMetrics::medicalDischargeCasesForPatient($patientKey);

        $labels = [];
        $values = [];
        $names = [];
        $summaries = [];

        foreach ($cases as $case) {
            $label = filled($case->code) ? (string) $case->code : 'Caso #'.$case->id;
            $date = self::formatFactDate($case->service_on ?? $case->started_on);

            $labels[] = $label;
            $names[] = "{$label} · {$date}";
            $values[] = 1;
            $summaries[] = OperationsDashboardMetrics::medicalDischargeCaseHoverLines($case);
        }

        return [
            'datasets' => [
                $this->makeBarDataset(
                    "Altas médicas · {$this->selectedPatientName}",
                    $values,
                    [
                        'names' => $names,
                        'summaries' => $summaries,
                    ],
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
        $isDetail = $this->selectedPatientKey !== null;
        $tooltipFooter = $isDetail
            ? ''
            : 'Haz clic para ver los casos del paciente';
        $displayColors = $isDetail ? 'false' : 'true';
        $tooltipPadding = $isDetail ? '12' : '10';

        $options = <<<JS
        {
            onClick: (event, elements) => {
                if (!elements || !elements.length) {
                    return;
                }

                \$wire.handleChartClick({
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
                    backgroundColor: 'rgba(22, 22, 24, 0.78)',
                    titleColor: '#f5f5f7',
                    bodyColor: 'rgba(235, 235, 245, 0.92)',
                    footerColor: 'rgba(235, 235, 245, 0.7)',
                    borderColor: 'rgba(255, 255, 255, 0.2)',
                    borderWidth: 1,
                    padding: {$tooltipPadding},
                    cornerRadius: 12,
                    displayColors: {$displayColors},
                    bodySpacing: 4,
                    titleMarginBottom: 8,
                    boxPadding: 6,
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
                            const dataset = context.dataset || {};

                            if (dataset.summaries && dataset.summaries[context.dataIndex]) {
                                return [];
                            }

                            return ' Casos: ' + context.raw;
                        },
                        afterBody: function(context) {
                            const item = context[0];
                            const dataset = item.dataset || {};

                            if (dataset.summaries && dataset.summaries[item.dataIndex]) {
                                return dataset.summaries[item.dataIndex];
                            }

                            return [];
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

    private static function formatFactDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        $text = trim((string) $value);

        if ($text === '') {
            return '—';
        }

        try {
            return \Carbon\Carbon::parse($text)->format('d/m/Y');
        } catch (\Throwable) {
            return $text;
        }
    }
}
