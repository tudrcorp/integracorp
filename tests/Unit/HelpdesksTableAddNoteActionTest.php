<?php

declare(strict_types=1);

it('HelpdesksTable define la acción addNote con estilo iOS', function () {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Tables/HelpdesksTable.php';
    $contents = file_get_contents($path);
    $actionsPath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Actions/HelpdeskTicketModalActions.php';
    $actions = file_get_contents($actionsPath);

    expect($contents)->toContain('EditAction::make()')
        ->and($contents)->toContain('HelpdeskTicketModalActions::makeAddNoteAction')
        ->toContain("Action::make('previewDocuments')")
        ->toContain('HelpdeskDocumentPaths::')
        ->toContain('HelpdeskTicketModalActions')
        ->and($actions)
        ->toContain('fi-helpdesk-ios-section')
        ->toContain('HelpdeskObservationAppender::append')
        ->toContain('RichEditor::make')
        ->toContain('currentUserIsTicketAssignee')
        ->toContain('shouldHideAddNoteAction')
        ->toContain('assertMayAddNote')
        ->toContain('EN PROCESO');
});

it('las tablas helpdesk de administration, operations y marketing ocultan añadir nota con la misma regla de asignado', function (): void {
    foreach (['Administration', 'Operations', 'Marketing'] as $panel) {
        $path = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Tables/HelpdesksTable.php";
        $src = file_get_contents($path);
        expect($src)->toContain('HelpdeskTicketModalActions::shouldHideAddNoteAction')
            ->and($src)->toContain('HelpdeskTicketModalActions::assertMayAddNote');
    }
});
