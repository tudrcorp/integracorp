<?php

declare(strict_types=1);

it('ListHelpdesks incluye acción de tutorial de uso en todos los módulos', function (): void {
    $paths = [
        dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Pages/ListHelpdesks.php',
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Helpdesks/Pages/ListHelpdesks.php',
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Helpdesks/Pages/ListHelpdesks.php',
        dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/Helpdesks/Pages/ListHelpdesks.php',
    ];

    $businessPath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Pages/ListHelpdesks.php';
    $createActionPath = dirname(__DIR__, 2).'/app/Support/HelpdeskBusinessCreateTicketHeaderAction.php';

    foreach ($paths as $path) {
        $contents = file_get_contents($path);

        expect($contents)
            ->toContain("Action::make('helpdeskVideoTutorial')")
            ->toContain("'id' => 'helpdesk-video-tutorial-btn'")
            ->toContain("Action::make('helpdeskFlowProcess')")
            ->toContain("'id' => 'helpdesk-flow-process-btn'")
            ->toContain("Action::make('helpdeskTour')")
            ->toContain("->label('Tutorial de uso')")
            ->toContain("'id' => 'helpdesk-tour-btn'")
            ->toContain("'data-helpdesk-tour-trigger' => 'true'")
            ->not->toContain("TOUR_BUTTON_CLASS = 'ticket-btn-ios-shell");

        if ($path === $businessPath) {
            expect($contents)->toContain('HelpdeskBusinessCreateTicketHeaderAction::make()');
        } else {
            expect($contents)
                ->toContain("'id' => 'helpdesk-create-ticket-btn'")
                ->toContain("'data-tour-shape' => 'pill'");
        }
    }

    expect(file_get_contents($createActionPath))
        ->toContain("'id' => 'helpdesk-create-ticket-btn'")
        ->toContain("'data-tour-shape' => 'pill'")
        ->toContain('canSeeCreateTicketButton')
        ->toContain('HelpdeskBusinessTicketCreationGate::allowsCreation()');

    $theme = file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css');

    expect($theme)
        ->toContain('#helpdesk-tour-btn')
        ->toContain('rgba(59, 130, 246, 0.58)')
        ->toContain('#helpdesk-video-tutorial-btn')
        ->toContain('rgba(22, 163, 74, 0.62)')
        ->toContain('#helpdesk-flow-process-btn')
        ->toContain('rgba(202, 138, 4, 0.65)');
});
