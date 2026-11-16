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
        ->toContain("TextEntry::make('expediente_delete')")
        ->toContain('self::expedienteRowActions($record)');

    /**
     * Desde el 16/11/2026 descargar y eliminar son acciones de la página: una acción anidada
     * en la tabla no se podía volver a ubicar (la ficha construye solo la pestaña activa) y
     * el botón no hacía nada. El borrado deja traza (ExpedienteDocumentDeletion).
     */
    $page = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Pages/ViewAffiliationCorporate.php');

    expect($page)
        ->toContain("Action::make('downloadExpedienteDocument')")
        ->toContain('response()->download(')
        ->toContain("Action::make('deleteExpedienteDocument')")
        ->toContain('ExpedienteDocumentDeletion::delete(');
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

it('oculta relation managers en la vista de afiliación corporativa', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Pages/ViewAffiliationCorporate.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('public function getRelationManagers(): array')
        ->toContain('return [];');
});

it('muestra un título personalizado en la vista de afiliación corporativa', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Pages/ViewAffiliationCorporate.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('public function getTitle(): string|\\Illuminate\\Contracts\\Support\\Htmlable')
        ->toContain('public function getHeading(): string|\\Illuminate\\Contracts\\Support\\Htmlable')
        ->toContain('RecordPageHeader::render(');
});
