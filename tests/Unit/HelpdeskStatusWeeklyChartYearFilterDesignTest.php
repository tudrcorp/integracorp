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
