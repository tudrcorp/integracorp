<?php

declare(strict_types=1);

use App\Support\Telemedicine\TelemedicineCaseDocumentsCatalog;

it('TelemedicineCaseInfolist incluye tab de expediente documental con hub de búsqueda', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineCases/Schemas/TelemedicineCaseInfolist.php');

    expect($contents)
        ->toContain("Tab::make('Expediente documental')")
        ->toContain('TelemedicineCaseDocumentsCatalog::hubViewContext')
        ->toContain('case-documents-hub');
});

it('hub de documentos del caso incluye acciones de envio por whatsapp y correo', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/operations/telemedicine-cases/case-documents-hub.blade.php');
    $actionContents = file_get_contents(dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineCaseDocumentSendAction.php');
    $viewPageContents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineCases/Pages/ViewTelemedicineCase.php');

    expect($contents)
        ->toContain('$wire.mountAction(\'sendCaseDocument\'')
        ->toContain('Enviar documentos')
        ->toContain('Descargar')
        ->toContain('$defaultPhone')
        ->toContain('$defaultEmail')
        ->not->toContain('SendTelemedicineCaseDocumentModal');

    expect($actionContents)
        ->toContain("Action::make('sendCaseDocument')")
        ->toContain('modalWidth(Width::Large)');

    expect($viewPageContents)->toContain('sendCaseDocumentAction');
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
        ->and($reflection->hasMethod('categories'))->toBeTrue()
        ->and($reflection->hasMethod('hubViewContext'))->toBeTrue();
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
