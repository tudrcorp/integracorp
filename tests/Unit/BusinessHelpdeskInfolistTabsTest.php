<?php

declare(strict_types=1);

it('organiza el infolist helpdesk en pestañas con equipo de ejecución', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/HelpdeskInfolistSchema.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Tabs::make('helpdeskInfolistTabs')")
        ->toContain("Tab::make('Resumen')")
        ->toContain("Tab::make('Equipo de ejecución')")
        ->toContain("Tab::make('Adjunto')")
        ->toContain("Tab::make('Notas')")
        ->toContain('observation_summary')
        ->toContain('HelpdeskObservationHtmlRenderer::render')
        ->toContain("TextEntry::make('team')")
        ->toContain("RepeatableEntry::make('team_members')")
        ->toContain('telefono_corporativo')
        ->toContain('hasExecutionTeam');
});

it('delega el infolist helpdesk de cada panel al schema compartido', function (string $panel): void {
    $path = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Schemas/HelpdeskInfolist.php";
    $contents = file_get_contents($path);

    expect($contents)->toContain('HelpdeskInfolistSchema::configure');
})->with(['Business', 'Administration', 'Marketing', 'Operations']);
