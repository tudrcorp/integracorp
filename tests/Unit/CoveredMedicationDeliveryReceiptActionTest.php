<?php

declare(strict_types=1);

use App\Support\Operations\CoordinationServiceCoveredItemsFinalizer;

it('expone la acción de comprobante de entrega de medicamentos cubiertos', function (): void {
    $support = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/CoordinationServiceCoveredItemsFinalizer.php');

    expect($support)
        ->toContain('makeUploadCoveredMedicationDeliveryReceiptAction')
        ->toContain("Action::make('uploadCoveredMedicationDeliveryReceipt')")
        ->toContain("->label('Comprobante entrega medicamentos')")
        ->toContain('handleCoveredMedicationDeliveryReceipt')
        ->toContain('hasCoveredMedicationsPendingFinalization')
        ->toContain('coveredMedicationPendingFinalizationOptions')
        ->toContain('finalizeCoveredItemsByKeys')
        ->toContain('MEDICATION_DELIVERY_RECEIPT_DOCUMENT_NAME')
        ->toContain('COMPROBANTE DE ENTREGA DE MEDICAMENTOS')
        ->toContain("->modalSubmitActionLabel('Guardar')")
        ->toContain("'status' => 'FINALIZADO'");
});

it('el infolist de coordinación enlaza el comprobante de entrega de medicamentos', function (): void {
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/OperationCoordinationServiceInfolist.php');

    expect($infolist)
        ->toContain('CoordinationServiceCoveredItemsFinalizer::makeUploadCoveredMedicationDeliveryReceiptAction()');
});

it('define el tipo de documento de comprobante de entrega en el catálogo', function (): void {
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_07_13_145829_add_comprobante_entrega_medicamentos_to_operation_document_lists_table.php');

    expect($migration)
        ->toContain('COMPROBANTE DE ENTREGA DE MEDICAMENTOS')
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
