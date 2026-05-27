<?php

declare(strict_types=1);

it('ViewOperationCoordinationService incluye acción de carga de documentos', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Pages/ViewOperationCoordinationService.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Action::make('upload_coordination_documents')")
        ->toContain('operation-coordination-services/')
        ->toContain("'uploaded_documents'")
        ->toContain('document_type_ids')
        ->toContain('document_types');
});

it('OperationCoordinationServiceInfolist muestra document_types desde la fila del repeatable', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/OperationCoordinationServiceInfolist.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Tab::make('Documentos')")
        ->toContain("RepeatableEntry::make('uploaded_documents')")
        ->toContain("TextEntry::make('document_types')")
        ->toContain('->badge()')
        ->toContain('Sin tipo asociado')
        ->toContain("TextEntry::make('document_name')")
        ->toContain('uploadedDocumentRowFromComponent')
        ->toContain('uploadedDocumentDownloadPrefixActions')
        ->toContain('OutlinedArrowDownTray')
        ->toContain('iconButton()')
        ->toContain("asset('storage/'")
        ->not->toContain('renderDownloadButton');
});

it('OperationCoordinationService model soporta uploaded_documents', function (): void {
    $path = dirname(__DIR__, 2).'/app/Models/OperationCoordinationService.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('uploaded_documents')
        ->toContain("'uploaded_documents' => 'array'");
});
