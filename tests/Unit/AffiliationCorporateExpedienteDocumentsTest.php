<?php

declare(strict_types=1);

it('muestra la sección expediente digital en el infolist de afiliación corporativa', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Schemas/AffiliationCorporateInfolist.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Section::make('Expediente digital')")
        ->toContain("RepeatableEntry::make('affiliationCorporateDocuments')")
        ->toContain("TableColumn::make('Documento')")
        ->toContain("TableColumn::make('Acciones')")
        ->toContain("Action::make('downloadExpedienteDocument')")
        ->toContain('response()->download(')
        ->toContain("Action::make('deleteExpedienteDocument')")
        ->toContain("TextEntry::make('expediente_delete')")
        ->toContain('->getStateUsing(fn (): string =>')
        ->toContain('->prefixActions([');
});

it('permite adjuntar múltiples documentos desde la vista de afiliación corporativa', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Pages/ViewAffiliationCorporate.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Action::make('back')")
        ->toContain("Action::make('attachDocuments')")
        ->toContain("FileUpload::make('documents')")
        ->toContain('->multiple()')
        ->toContain("'application/pdf'");
});
