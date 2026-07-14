<?php

declare(strict_types=1);

use App\Support\Operations\CoordinationServiceCoveredItemsFinalizer;

it('considera pendiente de finalizar solo los ítems cubiertos no cerrados', function (): void {
    expect(CoordinationServiceCoveredItemsFinalizer::isCoveredItemPendingFinalization([
        'coverage' => true,
        'status' => 'EN GESTION',
    ]))->toBeTrue()
        ->and(CoordinationServiceCoveredItemsFinalizer::isCoveredItemPendingFinalization([
            'coverage' => true,
            'status' => 'PENDIENTE',
        ]))->toBeTrue()
        ->and(CoordinationServiceCoveredItemsFinalizer::isCoveredItemPendingFinalization([
            'coverage' => true,
            'status' => 'FINALIZADO',
        ]))->toBeFalse()
        ->and(CoordinationServiceCoveredItemsFinalizer::isCoveredItemPendingFinalization([
            'coverage' => true,
            'status' => 'CANCELADA',
        ]))->toBeFalse()
        ->and(CoordinationServiceCoveredItemsFinalizer::isCoveredItemPendingFinalization([
            'coverage' => false,
            'status' => 'EN GESTION',
        ]))->toBeFalse()
        ->and(CoordinationServiceCoveredItemsFinalizer::isCoveredItemPendingFinalization([
            'coverage' => null,
            'status' => 'PENDIENTE',
        ]))->toBeFalse();
});

it('construye los documentos cargados a partir del formulario del modal', function (): void {
    $documents = CoordinationServiceCoveredItemsFinalizer::mapFormDocuments([
        'documents' => [
            [
                'document_file' => 'operation-coordination-services/1/documents/resultado-creatinina.pdf',
                'document_type_ids' => [5],
            ],
            ['document_file' => ''],
            'no-es-array',
        ],
    ], [5 => 'Resultado de laboratorio']);

    expect($documents)->toHaveCount(1)
        ->and($documents[0]['document_name'])->toBe('resultado-creatinina')
        ->and($documents[0]['file_path'])->toBe('operation-coordination-services/1/documents/resultado-creatinina.pdf')
        ->and($documents[0]['document_type_ids'])->toBe([5])
        ->and($documents[0]['document_types'])->toBe(['Resultado de laboratorio'])
        ->and($documents[0])->toHaveKeys(['document_name', 'file_path', 'document_type_ids', 'document_types', 'uploaded_at']);
});

it('asocia el servicio al documento a partir de las claves seleccionadas', function (): void {
    $documents = CoordinationServiceCoveredItemsFinalizer::mapFormDocuments([
        'documents' => [
            [
                'document_file' => 'operation-coordination-services/1/documents/resultado.pdf',
                'document_type_ids' => [5],
                'service_item_keys' => ['lab:10', 'lab:99'],
            ],
        ],
    ], [5 => 'Resultado'], ['lab:10' => 'Laboratorio: CREATININA']);

    expect($documents[0]['service_item_keys'])->toBe(['lab:10', 'lab:99'])
        ->and($documents[0]['services'])->toBe(['Laboratorio: CREATININA'])
        ->and($documents[0]['service'])->toBe('Laboratorio: CREATININA');
});

it('considera para colocar en gestión solo los ítems cubiertos pendientes', function (): void {
    expect(CoordinationServiceCoveredItemsFinalizer::isCoveredItemPendingManagement([
        'coverage' => true,
        'status' => 'PENDIENTE',
    ]))->toBeTrue()
        ->and(CoordinationServiceCoveredItemsFinalizer::isCoveredItemPendingManagement([
            'coverage' => true,
            'status' => 'EN GESTION',
        ]))->toBeFalse()
        ->and(CoordinationServiceCoveredItemsFinalizer::isCoveredItemPendingManagement([
            'coverage' => true,
            'status' => 'FINALIZADO',
        ]))->toBeFalse()
        ->and(CoordinationServiceCoveredItemsFinalizer::isCoveredItemPendingManagement([
            'coverage' => false,
            'status' => 'PENDIENTE',
        ]))->toBeFalse()
        ->and(CoordinationServiceCoveredItemsFinalizer::isCoveredItemPendingManagement([
            'coverage' => null,
            'status' => 'PENDIENTE',
        ]))->toBeFalse();
});

it('expone la acción para colocar servicios cubiertos en gestión cambiando de pendiente a en gestión', function (): void {
    $support = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/CoordinationServiceCoveredItemsFinalizer.php');

    expect($support)
        ->toContain('makePlaceCoveredItemsInManagementAction')
        ->toContain("Action::make('placeCoveredItemsInManagement')")
        ->toContain("->label('Colocar cubiertos en gestión')")
        ->toContain('hasCoveredItemsPendingManagement')
        ->toContain('coveredPendingManagementOptions')
        ->toContain('placeCoveredItemsInManagement')
        ->toContain("CheckboxList::make('service_item_keys')")
        ->toContain("whereRaw('UPPER(TRIM(status)) = ?', ['PENDIENTE'])")
        ->toContain("->update(['status' => 'EN GESTION'])")
        ->toContain('OperationServiceOrderCoordinationSync::refreshCoordinationStatus');
});

it('el infolist de coordinación enlaza la acción de colocar cubiertos en gestión en la pestaña de ítems asociados', function (): void {
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/OperationCoordinationServiceInfolist.php');

    expect($infolist)
        ->toContain('CoordinationServiceCoveredItemsFinalizer::makePlaceCoveredItemsInManagementAction()');
});

it('expone la acción de cargar documentos y finalizar servicios cubiertos con ambos botones', function (): void {
    $support = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/CoordinationServiceCoveredItemsFinalizer.php');

    expect($support)
        ->toContain('makeUploadAndFinalizeAction')
        ->toContain("->modalSubmitActionLabel('Guardar')")
        ->toContain('extraModalFooterActions')
        ->toContain("makeModalSubmitAction('save_and_finalize_covered_services', arguments: ['finalize' => true])")
        ->toContain("->label('Guardar y finalizar')")
        ->toContain("'uploaded_documents'")
        ->toContain('finalizeCoveredItems')
        ->toContain("'status' => 'FINALIZADO'")
        ->toContain('OperationServiceOrderCoordinationSync::refreshCoordinationStatus');
});

it('el infolist de coordinación enlaza la acción de finalizar cubiertos en la pestaña de ítems asociados', function (): void {
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/OperationCoordinationServiceInfolist.php');

    expect($infolist)
        ->toContain('use App\Support\Operations\CoordinationServiceCoveredItemsFinalizer;')
        ->toContain('->headerActions([')
        ->toContain('CoordinationServiceCoveredItemsFinalizer::makeUploadAndFinalizeAction()')
        ->toContain('CoordinationServiceCoveredItemsFinalizer::makeUploadCoveredMedicationDeliveryReceiptAction()');
});
