<?php

declare(strict_types=1);

use App\Support\Operations\CoordinationServiceCoveredItemsFinalizer;

it('expone la acción de comprobante de entrega de medicamentos cubiertos', function (): void {
    $support = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/CoordinationServiceCoveredItemsFinalizer.php');

    expect($support)
        ->toContain('makeUploadCoveredMedicationDeliveryReceiptAction')
        ->toContain("Action::make(\$config['action_name'])")
        ->toContain("->label(\$config['label'])")
        ->toContain('handleCoveredMedicationDeliveryReceipt')
        ->toContain('handleCoveredItemDeliveryReceipt')
        ->toContain('hasCoveredMedicationsPendingFinalization')
        ->toContain('coveredMedicationPendingFinalizationOptions')
        ->toContain('finalizeCoveredItemsByKeys')
        ->toContain('MEDICATION_DELIVERY_RECEIPT_DOCUMENT_NAME')
        ->toContain('COMPROBANTE DE ENTREGA DE MEDICAMENTOS')
        ->toContain("->modalSubmitActionLabel('Guardar')")
        ->toContain("'status' => 'FINALIZADO'");
});

it('expone acciones de comprobante de entrega para laboratorios, estudios y especialistas cubiertos', function (): void {
    $support = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/CoordinationServiceCoveredItemsFinalizer.php');

    expect($support)
        ->toContain('makeUploadCoveredLabDeliveryReceiptAction')
        ->toContain('makeUploadCoveredStudyDeliveryReceiptAction')
        ->toContain('makeUploadCoveredSpecialtyDeliveryReceiptAction')
        ->toContain('makeUploadCoveredItemDeliveryReceiptAction')
        ->toContain('LAB_DELIVERY_RECEIPT_DOCUMENT_NAME')
        ->toContain('STUDY_DELIVERY_RECEIPT_DOCUMENT_NAME')
        ->toContain('SPECIALTY_DELIVERY_RECEIPT_DOCUMENT_NAME')
        ->toContain('COMPROBANTE DE ENTREGA DE LABORATORIOS')
        ->toContain('COMPROBANTE DE ENTREGA DE ESTUDIOS')
        ->toContain('COMPROBANTE DE ENTREGA DE ESPECIALISTAS')
        ->toContain("'label' => 'Comprobante entrega laboratorios'")
        ->toContain("'label' => 'Comprobante entrega estudios'")
        ->toContain("'label' => 'Comprobante entrega especialistas'");
});

it('el infolist de coordinación enlaza el comprobante de entrega de medicamentos', function (): void {
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/OperationCoordinationServiceInfolist.php');

    expect($infolist)
        ->toContain('CoordinationServiceCoveredItemsFinalizer::makeUploadCoveredMedicationDeliveryReceiptAction()');
});

it('el infolist de coordinación enlaza comprobantes de laboratorios, estudios y especialistas', function (): void {
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/OperationCoordinationServiceInfolist.php');

    expect($infolist)
        ->toContain('CoordinationServiceCoveredItemsFinalizer::makeUploadCoveredLabDeliveryReceiptAction()')
        ->toContain('CoordinationServiceCoveredItemsFinalizer::makeUploadCoveredStudyDeliveryReceiptAction()')
        ->toContain('CoordinationServiceCoveredItemsFinalizer::makeUploadCoveredSpecialtyDeliveryReceiptAction()');
});

it('define el tipo de documento de comprobante de entrega en el catálogo', function (): void {
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_07_13_145829_add_comprobante_entrega_medicamentos_to_operation_document_lists_table.php');

    expect($migration)
        ->toContain('COMPROBANTE DE ENTREGA DE MEDICAMENTOS')
        ->toContain('operation_document_lists');
});

it('define tipos de documento de comprobante para labs, estudios y especialistas', function (): void {
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_07_14_114303_add_comprobantes_entrega_servicios_cubiertos_to_operation_document_lists_table.php');

    expect($migration)
        ->toContain('COMPROBANTE DE ENTREGA DE LABORATORIOS')
        ->toContain('COMPROBANTE DE ENTREGA DE ESTUDIOS')
        ->toContain('COMPROBANTE DE ENTREGA DE ESPECIALISTAS')
        ->toContain('operation_document_lists');
});

it('solo considera medicamentos cubiertos pendientes al filtrar opciones del comprobante', function (): void {
    expect(CoordinationServiceCoveredItemsFinalizer::isCoveredItemPendingFinalization([
        'coverage' => true,
        'status' => 'EN GESTION',
    ]))->toBeTrue();

    $constant = CoordinationServiceCoveredItemsFinalizer::MEDICATION_DELIVERY_RECEIPT_DOCUMENT_NAME;

    expect($constant)->toBe('COMPROBANTE DE ENTREGA DE MEDICAMENTOS');
});

it('configura comprobantes de entrega por tipo de ítem cubierto', function (string $itemType, string $documentName, string $label): void {
    $config = CoordinationServiceCoveredItemsFinalizer::coveredItemDeliveryReceiptConfig($itemType);

    expect($config['document_name'])->toBe($documentName)
        ->and($config['label'])->toBe($label);
})->with([
    'medicamentos' => ['medication', 'COMPROBANTE DE ENTREGA DE MEDICAMENTOS', 'Comprobante entrega medicamentos'],
    'laboratorios' => ['lab', 'COMPROBANTE DE ENTREGA DE LABORATORIOS', 'Comprobante entrega laboratorios'],
    'estudios' => ['study', 'COMPROBANTE DE ENTREGA DE ESTUDIOS', 'Comprobante entrega estudios'],
    'especialistas' => ['specialty', 'COMPROBANTE DE ENTREGA DE ESPECIALISTAS', 'Comprobante entrega especialistas'],
]);
