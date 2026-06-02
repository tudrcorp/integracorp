<?php

declare(strict_types=1);

it('HelpdesksTable define la acción addNote con estilo iOS', function () {
    $configuratorPath = dirname(__DIR__, 2).'/app/Support/HelpdeskTableConfigurator.php';
    $contents = file_get_contents($configuratorPath);
    $actionsPath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Actions/HelpdeskTicketModalActions.php';
    $actions = file_get_contents($actionsPath);

    expect($contents)->toContain('makeAddNoteAction()')
        ->toContain("Action::make('previewDocuments')")
        ->toContain('HelpdeskDocumentPaths::')
        ->and($actions)
        ->toContain('fi-helpdesk-ios-section')
        ->toContain('HelpdeskObservationAppender::append')
        ->toContain('RichEditor::make')
        ->toContain('currentUserIsTicketAssignee')
        ->toContain('shouldHideAddNoteAction')
        ->toContain('assertMayAddNote')
        ->toContain('EN PROCESO');
});

it('las tablas helpdesk de administration, operations y marketing usan el configurador compartido con addNote', function (): void {
    $configurator = file_get_contents(dirname(__DIR__, 2).'/app/Support/HelpdeskTableConfigurator.php');

    expect($configurator)->toContain('makeAddNoteAction()');

    foreach (['Administration', 'Operations', 'Marketing', 'Business'] as $panel) {
        $path = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Tables/HelpdesksTable.php";
        $src = file_get_contents($path);

        expect($src)->toContain('HelpdeskTableConfigurator::configure');
    }
});
