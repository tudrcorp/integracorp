<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\ProspectAgents\Widgets;

use App\Filament\Business\Resources\ProspectAgents\Concerns\HasProspectResourceChartTimeStateFilters;
use App\Filament\Business\Resources\ProspectAgents\Widgets\Concerns\AgencyLikeBarChartStyling;
use App\Support\ProspectAgents\ProspectCollaboratorLabels;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class TopRegisterProspect extends ChartWidget
{
    use AgencyLikeBarChartStyling;
    use HasProspectResourceChartTimeStateFilters;

    protected string $view = 'filament.widgets.top-register-prospect-chart';

    protected string $color = 'gray';

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Prospectos registrados por colaborador';

    protected ?string $description = 'Prospectos registrados agrupados por colaborador (creador del registro). Clic en una barra para ver sus cambios de estatus.';

    protected ?string $maxHeight = '400px';

    public ?string $selectedCollaborator = null;

    public int $chartKey = 0;

    public function mount(): void
    {
        parent::mount();
        $this->bootProspectChartFilters();
    }

    public function isDrillDown(): bool
    {
        return filled($this->selectedCollaborator);
    }

    public function getHeading(): string|Htmlable|null
    {
        if (! $this->isDrillDown()) {
            return 'Prospectos registrados por colaborador';
        }

        return 'Cambios de estatus · '.$this->selectedCollaborator;
    }

    public function getDescription(): string|Htmlable|null
    {
        if (! $this->isDrillDown()) {
            return 'Prospectos registrados agrupados por colaborador (creador del registro). Clic en una barra para ver sus cambios de estatus.';
        }

        return 'Los 5 estatus del embudo para este colaborador en '.$this->periodLabel().' (actualizaciones por updated_by y altas sin updated_by).';
    }

    /**
     * @param  array{label?: string, index?: int}  $payload
     */
    public function handleChartClick(array $payload = []): void
    {
        if ($this->isDrillDown()) {
            return;
        }

        $label = trim((string) ($payload['label'] ?? ''));
        if ($label === '') {
            return;
        }

        $this->selectedCollaborator = $label;
        $this->chartKey++;
        $this->cachedData = null;
    }

    public function resetDrillDown(): void
    {
        $this->selectedCollaborator = null;
        $this->chartKey++;
        $this->cachedData = null;
    }

    public function updatedChartYear(mixed $value): void
    {
        $this->resetDrillDown();
    }

    public function updatedChartMonth(mixed $value): void
    {
        $this->resetDrillDown();
    }

    protected function getData(): array
    {
        $year = $this->resolvedChartYear();
        $month = $this->resolvedChartMonth();

        if ($this->isDrillDown()) {
            return $this->buildStatusChangesChartData($year, $month);
        }

        return $this->buildCollaboratorRegistrationsChartData($year, $month);
    }

    /**
     * @return array{datasets: list<array<string, mixed>>, labels: list<string>}
     */
    private function buildCollaboratorRegistrationsChartData(int $year, ?int $month): array
    {
        $ordered = ProspectCollaboratorLabels::prospectCountsOrdered($year, $month);
        $labels = $ordered['labels'];
        $prospectCounts = $ordered['counts'];
        $n = count($labels);
        $blueFill = 'rgba(10, 132, 255, 0.88)';
        $stroke = 'rgba(255, 255, 255, 0.82)';

        return [
            'datasets' => [
                [
                    'label' => 'Prospectos registrados',
                    'data' => $prospectCounts,
                    'backgroundColor' => array_fill(0, $n, $blueFill),
                    'borderColor' => array_fill(0, $n, $stroke),
                    'borderWidth' => 1.25,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                    'hoverBackgroundColor' => array_fill(0, $n, $this->brighterGlassFill($blueFill)),
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * @return array{datasets: list<array<string, mixed>>, labels: list<string>}
     */
    private function buildStatusChangesChartData(int $year, ?int $month): array
    {
        $ordered = ProspectCollaboratorLabels::statusChangeCountsForCollaborator(
            (string) $this->selectedCollaborator,
            $year,
            $month,
        );

        $labels = $ordered['labels'];
        $values = $ordered['counts'];
        $colors = $this->glassBarColorsForValues($values);

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Cambios de estatus · '.$this->periodLabel(),
                    'data' => $values,
                    'backgroundColor' => $colors['fills'],
                    'borderColor' => $colors['strokes'],
                    'borderWidth' => 1.25,
                    'borderRadius' => 10,
                    'borderSkipped' => false,
                    'hoverBackgroundColor' => $colors['hovers'],
                ],
            ],
        ];
    }

    private function periodLabel(): string
    {
        $year = $this->resolvedChartYear();
        $month = $this->resolvedChartMonth();

        if ($month === null) {
            return (string) $year;
        }

        return ucfirst(Carbon::create($year, $month, 1)->locale(app()->getLocale())->translatedFormat('F Y'));
    }

    protected function getOptions(): RawJs
    {
        $livewireId = (string) $this->getId();
        $isDetail = $this->isDrillDown() ? 'true' : 'false';
        $tooltipFooter = $this->isDrillDown()
            ? 'Usa Volver para regresar al ranking de colaboradores'
            : 'Clic para ver cambios de estatus de este colaborador';
        $unitSingular = $this->isDrillDown() ? 'cambio' : 'prospecto';
        $unitPlural = $this->isDrillDown() ? 'cambios' : 'prospectos';

        return RawJs::make(<<<JS
        {
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
            onClick: (event, elements, chart) => {
                if ({$isDetail}) {
                    return;
                }
                if (!elements || !elements.length) {
                    return;
                }
                const index = elements[0].index;
                const label = chart?.data?.labels?.[index] ?? '';
                const component = window.Livewire?.find('{$livewireId}');
                component?.call('handleChartClick', { label, index });
            },
            onHover: (event, elements) => {
                const target = event?.native?.target;
                if (!target) {
                    return;
                }
                if ({$isDetail}) {
                    target.style.cursor = 'default';
                    return;
                }
                target.style.cursor = elements?.[0] ? 'pointer' : 'default';
            },
            datasets: {
                bar: {
                    categoryPercentage: 0.82,
                    barPercentage: 0.92
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
                legend: {
                    display: false
                },
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
                    displayColors: true,
                    usePointStyle: true,
                    callbacks: {
                        label: (context) => {
                            const value = Number(context.parsed?.y ?? 0) || 0;
                            const unit = value === 1 ? '{$unitSingular}' : '{$unitPlural}';
                            return value + ' ' + unit;
                        },
                        footer: () => '{$tooltipFooter}'
                    }
                }
            },
            scales: {
                x: {
                    offset: true,
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#000000',
                        maxRotation: 35,
                        minRotation: 0,
                        font: {
                            size: 13,
                            family: '-apple-system, BlinkMacSystemFont, system-ui, sans-serif'
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(120, 120, 128, 0.10)'
                    },
                    ticks: {
                        precision: 0,
                        stepSize: 1,
                        color: '#8e8e93',
                        font: {
                            size: 10,
                            family: '-apple-system, BlinkMacSystemFont, system-ui, sans-serif'
                        }
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
