<?php

declare(strict_types=1);

it('ListHelpdesks incluye acción de guía del helpdesk', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Pages/ListHelpdesks.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Action::make('helpdeskTour')")
        ->toContain("'id' => 'helpdesk-tour-btn'")
        ->toContain("'data-helpdesk-tour-trigger' => 'true'");
});
