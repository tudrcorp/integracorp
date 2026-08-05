<?php

declare(strict_types=1);

use App\Services\TelemedicineMedicationInventoryDeductor;
use App\Support\Telemedicine\TelemedicineMedicationInventoryOptions;

it('define tipo de movimiento y salida para telemedicina', function (): void {
    expect(TelemedicineMedicationInventoryDeductor::MOVEMENT_TYPE)->toBe('SALIDA TELEMEDICINA')
        ->and(TelemedicineMedicationInventoryDeductor::OUTFLOW_TYPE)->toBe('SALIDA TELEMEDICINA')
        ->and(TelemedicineMedicationInventoryDeductor::DEFAULT_QUANTITY)->toBe(1);
});

it('no descuenta cuando no hay inventario o el doctor no es TDG de almacén conocido', function (): void {
    $deductor = new TelemedicineMedicationInventoryDeductor;

    $consultation = new App\Models\TelemedicineConsultationPatient;
    $consultation->forceFill([
        'id' => 1,
        'telemedicine_patient_id' => 1,
        'telemedicine_case_id' => 1,
        'telemedicine_doctor_id' => 1,
    ]);

    $provider = new App\Models\TelemedicineDoctor(['managed_by' => 'ATENMEDI', 'supplier_id' => 9]);
    $case = new App\Models\TelemedicineCase(['belongs_to' => 'Diagnomovil']);

    expect($deductor->deductIfApplicable(null, $consultation, $case, $provider))->toBeNull()
        ->and($deductor->deductIfApplicable(10, $consultation, $case, $provider))->toBeNull()
        ->and(TelemedicineMedicationInventoryOptions::shouldDeductInventory($provider, $case))->toBeFalse();
});

it('create y edit de consulta descuentan inventario TDG al guardar medicamentos', function (): void {
    $create = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php'
    );
    $edit = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/EditTelemedicineConsultationPatient.php'
    );

    expect($create)
        ->toContain('TelemedicineMedicationInventoryDeductor')
        ->toContain('deductIfApplicable')
        ->toContain('quantityForInventoryDeduction');

    expect($edit)
        ->toContain('TelemedicineMedicationInventoryDeductor')
        ->toContain('deductIfApplicable')
        ->toContain('quantityForInventoryDeduction');
});

it('el deductor sincroniza existencia, stock, outflow y movimiento', function (): void {
    $contents = file_get_contents(
        dirname(__DIR__, 2).'/app/Services/TelemedicineMedicationInventoryDeductor.php'
    );

    expect($contents)
        ->toContain('lockForUpdate()')
        ->toContain('OperationInventoryOutflow::query()->create')
        ->toContain('OperationInventoryMovement::query()->create')
        ->toContain('OperationInventoryProductStock::query()')
        ->toContain('TelemedicineMedicationInventoryOptions::shouldDeductInventory')
        ->toContain("'telemedicine_case_id' => \$consultationModel->telemedicine_case_id");
});
