<?php

declare(strict_types=1);

it('muestra la sección expediente digital en el infolist de afiliación individual', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Schemas/AffiliationInfolist.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Section::make('Expediente digital')")
        ->toContain("RepeatableEntry::make('affiliationDocuments')")
        ->toContain("TableColumn::make('Documento')")
        ->toContain("TableColumn::make('Acciones')")
        ->toContain("Action::make('downloadExpedienteDocument')")
        ->toContain('response()->download(')
        ->toContain("Action::make('deleteExpedienteDocument')")
        ->toContain("TextEntry::make('expediente_delete')")
        ->toContain('->getStateUsing(fn (): string =>')
        ->toContain('->prefixActions([');
});

it('permite adjuntar múltiples documentos desde la vista de afiliación individual', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Pages/ViewAffiliation.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Action::make('back')")
        ->toContain("Action::make('attachDocuments')")
        ->toContain('EditAction::make()')
        ->toContain("FileUpload::make('documents')")
        ->toContain('->multiple()')
        ->toContain("'application/pdf'");

    $posBack = strpos($contents, "Action::make('back')");
    $posAttach = strpos($contents, "Action::make('attachDocuments')");
    $posEdit = strpos($contents, 'EditAction::make()');

    expect(
        $posBack !== false
        && $posAttach !== false
        && $posEdit !== false
        && $posBack < $posAttach
        && $posAttach < $posEdit,
    )->toBeTrue();
});

it('oculta relation managers en la vista de afiliación individual', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Pages/ViewAffiliation.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('public function getRelationManagers(): array')
        ->toContain('return [];');
});
