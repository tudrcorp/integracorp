<?php

declare(strict_types=1);

use App\Filament\Business\Resources\BusinessAppointments\Pages\ListBusinessAppointments;
use App\Filament\Business\Resources\BusinessAppointments\Widgets\BusinessAppointmentNotesByUserChart;
use App\Filament\Business\Resources\BusinessAppointments\Widgets\BusinessAppointmentsByStateChart;
use App\Filament\Business\Resources\BusinessAppointments\Widgets\BusinessAppointmentsByStatusChart;
use App\Filament\Business\Resources\BusinessAppointments\Widgets\BusinessAppointmentsKpiOverview;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\StatsOverviewWidget;

it('registra los widgets KPI en el listado de citas de negocio', function (): void {
    $page = new class extends ListBusinessAppointments
    {
        public function exposedHeaderWidgets(): array
        {
            return $this->getHeaderWidgets();
        }
    };

    expect($page->exposedHeaderWidgets())->toBe([
        BusinessAppointmentsKpiOverview::class,
        BusinessAppointmentsByStatusChart::class,
        BusinessAppointmentsByStateChart::class,
        BusinessAppointmentNotesByUserChart::class,
    ]);
});

it('define widgets de segmentacion como graficos de Filament', function (): void {
    expect(is_subclass_of(BusinessAppointmentsByStatusChart::class, ChartWidget::class))->toBeTrue()
        ->and(is_subclass_of(BusinessAppointmentsByStateChart::class, ChartWidget::class))->toBeTrue()
        ->and(is_subclass_of(BusinessAppointmentNotesByUserChart::class, ChartWidget::class))->toBeTrue();
});

it('define widget KPI de citas como stats overview', function (): void {
    expect(is_subclass_of(BusinessAppointmentsKpiOverview::class, StatsOverviewWidget::class))->toBeTrue()
        ->and(method_exists(BusinessAppointmentsKpiOverview::class, 'buildSummary'))->toBeTrue()
        ->and(method_exists(BusinessAppointmentsByStatusChart::class, 'buildChartData'))->toBeTrue()
        ->and(method_exists(BusinessAppointmentsByStateChart::class, 'buildChartData'))->toBeTrue()
        ->and(method_exists(BusinessAppointmentNotesByUserChart::class, 'buildChartData'))->toBeTrue();
});
