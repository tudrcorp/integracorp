<?php

declare(strict_types=1);

it('HelpdesksTable define la acción addNote con estilo iOS', function () {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Tables/HelpdesksTable.php';
    $contents = file_get_contents($path);
    expect($contents)->toContain("Action::make('addNote')")
        ->toContain('HelpdeskObservationAppender::append')
        ->toContain('fi-helpdesk-ios-section')
        ->toContain('aviso-btn-ios-success');
});
