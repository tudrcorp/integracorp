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
        ->toContain('service_item_keys')
        ->toContain('CoordinationServiceCoveredItemsFinalizer::buildUploadedDocumentsFromForm');
});

it('ViewOperationCoordinationService solo muestra la carga de documentos cuando el servicio está EN GESTION', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Pages/ViewOperationCoordinationService.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('->visible(fn (): bool => $this->coordinationIsEnGestion())')
        ->toContain('private function coordinationIsEnGestion(): bool')
        ->toContain("=== 'EN GESTION'");
});

it('coordinationIsEnGestion evalúa el estatus del registro de la coordinación', function (): void {
    $page = new class extends \App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ViewOperationCoordinationService
    {
        public \App\Models\OperationCoordinationService $fakeRecord;

        public function __construct() {}

        public function getRecord(): \Illuminate\Database\Eloquent\Model
        {
            return $this->fakeRecord;
        }

        public function isEnGestionProxy(): bool
        {
            $method = new \ReflectionMethod(\App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ViewOperationCoordinationService::class, 'coordinationIsEnGestion');
            $method->setAccessible(true);

            return $method->invoke($this);
        }
    };

    $page->fakeRecord = new \App\Models\OperationCoordinationService(['status' => 'EN GESTION']);
    expect($page->isEnGestionProxy())->toBeTrue();

    $page->fakeRecord = new \App\Models\OperationCoordinationService(['status' => 'PENDIENTE']);
    expect($page->isEnGestionProxy())->toBeFalse();

    $page->fakeRecord = new \App\Models\OperationCoordinationService(['status' => null]);
    expect($page->isEnGestionProxy())->toBeFalse();
});

it('ViewOperationCoordinationService muestra contadores de estatus clínicos en el encabezado', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Pages/ViewOperationCoordinationService.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('CoordinationServiceItemsManager::clinicalItemsWithEffectiveDisplayStatus')
        ->toContain('CoordinationServiceItemsManager::renderClinicalItemsStatusCounterPills');
});

it('OperationCoordinationServiceInfolist muestra document_types desde la fila del repeatable', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/OperationCoordinationServiceInfolist.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Tab::make('Documentos')")
        ->toContain("RepeatableEntry::make('uploaded_documents')")
        ->toContain('CoordinationServiceDocumentsAggregator::forCoordination')
        ->toContain("TextEntry::make('document_types')")
        ->toContain("TextEntry::make('services')")
        ->toContain("TextEntry::make('source')")
        ->toContain("TableColumn::make('Servicio')")
        ->toContain("TableColumn::make('Origen')")
        ->toContain('Sin servicio asociado')
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
