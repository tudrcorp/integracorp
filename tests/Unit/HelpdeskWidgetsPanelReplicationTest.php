<?php

declare(strict_types=1);

it('registra widgets de helpdesk en administration, marketing y operations', function (): void {
    $paths = [
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Helpdesks/Pages/ListHelpdesks.php',
        dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/Helpdesks/Pages/ListHelpdesks.php',
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Helpdesks/Pages/ListHelpdesks.php',
    ];

    foreach ($paths as $path) {
        $contents = file_get_contents($path);

        expect($contents)
            ->not->toBeFalse()
            ->toContain('use Filament\Pages\Concerns\ExposesTableToWidgets;')
            ->toContain('use ExposesTableToWidgets;')
            ->toContain('StatsOverviewHelpdesk::class')
            ->toContain('HelpdeskStatusWeeklyChart::class');
    }
});

it('registra aliases livewire para widgets helpdesk en los tres paneles adicionales', function (): void {
    $path = dirname(__DIR__, 2).'/app/Providers/AppServiceProvider.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->not->toBeFalse()
        ->toContain("Livewire::component('app.filament.administration.resources.helpdesks.widgets.helpdesk-status-weekly-chart', AdministrationHelpdeskStatusWeeklyChart::class);")
        ->toContain("Livewire::component('app.filament.administration.resources.helpdesks.widgets.stats-overview-helpdesk', AdministrationStatsOverviewHelpdesk::class);")
        ->toContain("Livewire::component('app.filament.marketing.resources.helpdesks.widgets.helpdesk-status-weekly-chart', MarketingHelpdeskStatusWeeklyChart::class);")
        ->toContain("Livewire::component('app.filament.marketing.resources.helpdesks.widgets.stats-overview-helpdesk', MarketingStatsOverviewHelpdesk::class);")
        ->toContain("Livewire::component('app.filament.operations.resources.helpdesks.widgets.helpdesk-status-weekly-chart', OperationsHelpdeskStatusWeeklyChart::class);")
        ->toContain("Livewire::component('app.filament.operations.resources.helpdesks.widgets.stats-overview-helpdesk', OperationsStatsOverviewHelpdesk::class);");
});
