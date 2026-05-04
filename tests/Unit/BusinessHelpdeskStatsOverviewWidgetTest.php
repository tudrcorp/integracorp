<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Helpdesks\Widgets\HelpdeskStatusWeeklyChart;
use App\Filament\Business\Resources\Helpdesks\Widgets\StatsOverviewHelpdesk;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\StatsOverviewWidget;

it('registra el widget de resumen de tickets en el listado de helpdesks de business', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Pages/ListHelpdesks.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->not->toBeFalse()
        ->toContain('use Filament\Pages\Concerns\ExposesTableToWidgets;')
        ->toContain('use ExposesTableToWidgets;')
        ->toContain('StatsOverviewHelpdesk::class')
        ->toContain('HelpdeskStatusWeeklyChart::class');
});

it('el widget de helpdesks extiende StatsOverviewWidget', function (): void {
    expect(class_exists(StatsOverviewHelpdesk::class))->toBeTrue()
        ->and(is_subclass_of(StatsOverviewHelpdesk::class, StatsOverviewWidget::class))->toBeTrue();
});

it('registra el alias livewire del widget de helpdesks', function (): void {
    $path = dirname(__DIR__, 2).'/app/Providers/AppServiceProvider.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->not->toBeFalse()
        ->toContain('use App\\Filament\\Business\\Resources\\Helpdesks\\Widgets\\HelpdeskStatusWeeklyChart;')
        ->toContain('use App\\Filament\\Business\\Resources\\Helpdesks\\Widgets\\StatsOverviewHelpdesk;')
        ->toContain("Livewire::component('app.filament.business.resources.helpdesks.widgets.helpdesk-status-weekly-chart', HelpdeskStatusWeeklyChart::class);")
        ->toContain("Livewire::component('app.filament.business.resources.helpdesks.widgets.stats-overview-helpdesk', StatsOverviewHelpdesk::class);");
});

it('el widget semanal de estatus extiende ChartWidget', function (): void {
    expect(class_exists(HelpdeskStatusWeeklyChart::class))->toBeTrue()
        ->and(is_subclass_of(HelpdeskStatusWeeklyChart::class, ChartWidget::class))->toBeTrue();
});

it('el widget semanal permite seleccionar rangos semanales de lunes a domingo', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Widgets/HelpdeskStatusWeeklyChart.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->not->toBeFalse()
        ->toContain("protected string \$view = 'filament.widgets.helpdesk-status-weekly-chart';")
        ->toContain('public ?string $fromDate = null;')
        ->toContain('public ?string $toDate = null;')
        ->toContain('public function applyWeekRange(): void')
        ->toContain('public function resetWeekRange(): void')
        ->toContain('public function updatedFromDate(?string $value): void')
        ->toContain('startOfWeek(CarbonInterface::MONDAY)')
        ->toContain('endOfWeek(CarbonInterface::SUNDAY)');
});

it('la vista del widget semanal muestra calendario con desde y hasta', function (): void {
    $path = dirname(__DIR__, 2).'/resources/views/filament/widgets/helpdesk-status-weekly-chart.blade.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->not->toBeFalse()
        ->toContain('Resetear los filtros')
        ->toContain('<x-filament::dropdown')
        ->toContain('icon="heroicon-m-funnel"')
        ->toContain('id="helpdesk-from-date"')
        ->toContain('id="helpdesk-to-date"')
        ->toContain('readonly')
        ->toContain('wire:click="applyWeekRange"');
});
