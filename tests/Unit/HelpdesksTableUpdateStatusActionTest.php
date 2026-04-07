<?php

declare(strict_types=1);

it('HelpdesksTable define la acción updateStatus con HelpdeskTaskStatusOptions', function () {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Tables/HelpdesksTable.php';
    $contents = file_get_contents($path);
    expect($contents)->toContain("Action::make('updateStatus')")
        ->toContain('HelpdeskTaskStatusOptions::forSelect')
        ->toContain('HelpdeskTaskStatusOptions::sanitizeStatusForSave')
        ->toContain('fi-helpdesk-ios-section')
        ->toContain('->native(true)')
        ->toContain('helpdesk-status-native-select');
});
