<?php

declare(strict_types=1);

it('abre la bitácora desde la columna uid en todos los paneles helpdesk', function (): void {
    foreach (['Business', 'Administration', 'Marketing', 'Operations'] as $panel) {
        $path = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Tables/HelpdesksTable.php";
        $contents = file_get_contents($path);

        expect($contents)
            ->toContain("Action::make('viewTimeline')")
            ->toContain("view('filament.business.helpdesks.timeline-modal'")
            ->toContain('HelpdeskTimelineBuilder::fromTicket($record)')
            ->toContain('fi-helpdesk-timeline-modal-window');
    }
});

it('el builder de timeline contempla creación, notas, prioridad y estado', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/HelpdeskTimelineBuilder.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("'type' => 'created'")
        ->toContain("'type' => 'note'")
        ->toContain("'type' => 'status_change'")
        ->toContain("'type' => 'priority_change'")
        ->toContain("preg_match('/^\\[(?<meta>")
        ->toContain('HelpdeskTimelineActorResolver::resolve');
});
