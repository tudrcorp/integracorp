<?php

declare(strict_types=1);

it('ListHelpdesks incluye acción de tutorial de uso en todos los módulos', function (): void {
    $paths = [
        dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Pages/ListHelpdesks.php',
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Helpdesks/Pages/ListHelpdesks.php',
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Helpdesks/Pages/ListHelpdesks.php',
        dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/Helpdesks/Pages/ListHelpdesks.php',
    ];

    foreach ($paths as $path) {
        $contents = file_get_contents($path);

        expect($contents)
            ->toContain("Action::make('helpdeskTour')")
            ->toContain("->label('Tutorial de uso')")
            ->toContain("'id' => 'helpdesk-tour-btn'")
            ->toContain("'data-helpdesk-tour-trigger' => 'true'")
            ->toContain("'id' => 'helpdesk-create-ticket-btn'")
            ->toContain("'data-tour-shape' => 'pill'");
    }
});
