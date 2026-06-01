<?php

declare(strict_types=1);

it('HelpdesksTable define la acción updateStatus con HelpdeskTaskStatusOptions', function () {
    $tablePath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Tables/HelpdesksTable.php';
    $actionsPath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Actions/HelpdeskTicketModalActions.php';

    expect(file_get_contents($tablePath))->toContain('makeUpdateStatusAction()');

    expect(file_get_contents($actionsPath))
        ->toContain('HelpdeskTaskStatusOptions::forSelect')
        ->toContain('currentUserIsTicketAssignee($record)')
        ->toContain('HelpdeskTaskStatusOptions::sanitizeStatusForSave')
        ->toContain('statusModalDescription')
        ->toContain('fi-helpdesk-ios-section')
        ->toContain('helpdesk-status-native-select')
        ->toContain('HelpdeskStatusChangeNote::assigneeExplanationEditor');
});
