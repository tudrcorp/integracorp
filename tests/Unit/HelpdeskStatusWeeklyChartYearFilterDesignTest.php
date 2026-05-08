<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

it('renderiza el selector de año con el diseño tipo select (igual al chart con filtro de año)', function (): void {
    $widgetClassPath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Widgets/HelpdeskStatusWeeklyChart.php';
    $widgetViewPath = dirname(__DIR__, 2).'/resources/views/filament/widgets/helpdesk-status-weekly-chart.blade.php';

    expect(file_exists($widgetClassPath))->toBeTrue()
        ->and(file_exists($widgetViewPath))->toBeTrue();

    $widgetClass = file_get_contents($widgetClassPath);
    $widgetView = file_get_contents($widgetViewPath);

    expect($widgetClass)
        ->toContain('protected function getFilters(): ?array');

    expect($widgetView)
        ->toContain('$filters = $this->getFilters();')
        ->toContain('class="fi-wi-chart-filter"')
        ->toContain('wire:target="year"')
        ->toContain('<x-filament::input.select')
        ->toContain('wire:model.live="year"');
});

it('en todos los paneles el retorno desde detalle mensual es solo con el botón (sin toggleMonthDetail en el gráfico)', function (): void {
    $basePath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Widgets/HelpdeskStatusWeeklyChart.php';
    $baseContents = file_get_contents($basePath);

    expect($baseContents)->not->toBeFalse()
        ->toContain('openMonthDetail')
        ->not->toContain('toggleMonthDetail');

    foreach ([
        dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/Helpdesks/Widgets/HelpdeskStatusWeeklyChart.php',
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Helpdesks/Widgets/HelpdeskStatusWeeklyChart.php',
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Helpdesks/Widgets/HelpdeskStatusWeeklyChart.php',
    ] as $panelWidgetPath) {
        expect(file_get_contents($panelWidgetPath))
            ->not->toBeFalse()
            ->not->toContain('detailReturnUsesBackButtonOnly = false');
    }

    $widgetViewPath = dirname(__DIR__, 2).'/resources/views/filament/widgets/helpdesk-status-weekly-chart.blade.php';
    $widgetView = file_get_contents($widgetViewPath);

    expect($widgetView)
        ->toContain('wire:click="resetToAnnual"')
        ->not->toContain('usesDetailBackButtonOnly()');
});
