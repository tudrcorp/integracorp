<?php

declare(strict_types=1);

use App\Support\Telemedicine\TelemedicineCaseDocumentsCatalog;

it('TelemedicineCaseInfolist incluye tab de expediente documental con hub de búsqueda', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineCases/Schemas/TelemedicineCaseInfolist.php');

    expect($contents)
        ->toContain("Tab::make('Expediente documental')")
        ->toContain('TelemedicineCaseDocumentsCatalog::entries')
        ->toContain("SchemaFacade::hasTable('operation_document_lists')")
        ->toContain('OperationDocumentList::query()')
        ->toContain("'documentFilters' => \$documentFilters")
        ->toContain('case-documents-hub');
});

it('hub de documentos del caso incluye paginación en el cliente', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/operations/telemedicine-cases/case-documents-hub.blade.php');

    expect($contents)
        ->toContain('$documentFilters')
        ->toContain('$filters')
        ->toContain('paginatedDocuments()')
        ->toContain('doc.types.includes(this.category)')
        ->toContain('perPage: 5')
        ->toContain('perPageOptions: [5, 10, 20, 50]')
        ->toContain('goToPage')
        ->toContain('Paginación de documentos');
});

it('TelemedicineCaseDocumentsCatalog define estructura de entrada de documento', function (): void {
    $reflection = new ReflectionClass(TelemedicineCaseDocumentsCatalog::class);

    expect($reflection->hasMethod('entries'))->toBeTrue()
        ->and($reflection->hasMethod('categories'))->toBeTrue();
});

it('TelemedicineCaseDocumentsCatalog omite tablas opcionales si no existen en la base', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineCaseDocumentsCatalog.php');

    expect($contents)
        ->toContain('TelemedicineConsultationPatient')
        ->toContain('appendConsultationUploadedDocuments')
        ->toContain("'Consulta telemedicina'")
        ->toContain('Schema::hasTable')
        ->toContain('appendCoordinationClinicDocuments')
        ->toContain('hasTable(OperationCoordinationClinicDocument::class)')
        ->toContain('hasTable(OperationQuoteGenerator::class)')
        ->toContain('hasTable(OperationServiceOrderQuote::class)');
});
