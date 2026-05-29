<?php

declare(strict_types=1);

it('configura render hooks del panel projects con vistas del módulo projects', function (): void {
    $providerPath = dirname(__DIR__, 2).'/app/Providers/Filament/ProjectsPanelProvider.php';
    $provider = file_get_contents($providerPath);

    expect($provider)
        ->toContain("view('filament.projects.partials.affiliation-documents-panel-script')")
        ->toContain("view('filament.projects.helpdesks.helpdesk-tour-script')")
        ->toContain("view('filament.hooks.projects-helpdesk-tickets-ticker-wrapper'")
        ->toContain("view('filament.panels.internal-quick-nav')");

    expect(file_exists(dirname(__DIR__, 2).'/resources/views/filament/projects/partials/affiliation-documents-panel-script.blade.php'))->toBeTrue();
    expect(file_exists(dirname(__DIR__, 2).'/resources/views/filament/projects/helpdesks/helpdesk-tour-script.blade.php'))->toBeTrue();
    expect(file_exists(dirname(__DIR__, 2).'/resources/views/filament/hooks/projects-helpdesk-tickets-ticker-wrapper.blade.php'))->toBeTrue();

    expect(file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/hooks/projects-helpdesk-tickets-ticker-wrapper.blade.php'))
        ->toContain('ProjectsPanelHelpdeskTicketsTicker::shouldDisplay()');
});
