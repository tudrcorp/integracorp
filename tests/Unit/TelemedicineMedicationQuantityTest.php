<?php

declare(strict_types=1);

use App\Support\Telemedicine\TelemedicineMedicationsPdfRows;

it('resuelve la cantidad desde la fila del formulario', function (): void {
    expect(TelemedicineMedicationsPdfRows::quantityFromRow(['quantity' => 3]))->toBe(3)
        ->and(TelemedicineMedicationsPdfRows::quantityFromRow(['quantity' => '5']))->toBe(5)
        ->and(TelemedicineMedicationsPdfRows::quantityFromRow(['quantity' => null]))->toBeNull()
        ->and(TelemedicineMedicationsPdfRows::quantityFromRow(['quantity' => '']))->toBeNull()
        ->and(TelemedicineMedicationsPdfRows::quantityFromRow(['quantity' => 0]))->toBeNull()
        ->and(TelemedicineMedicationsPdfRows::quantityFromRow([]))->toBeNull();
});

it('usa cantidad 1 por defecto al descontar inventario si no viene informada', function (): void {
    expect(TelemedicineMedicationsPdfRows::quantityForInventoryDeduction(['quantity' => 4]))->toBe(4)
        ->and(TelemedicineMedicationsPdfRows::quantityForInventoryDeduction([]))->toBe(1)
        ->and(TelemedicineMedicationsPdfRows::quantityForInventoryDeduction(['quantity' => null]))->toBe(1);
});

it('normaliza la cantidad en las filas de medicamentos para el pdf', function (): void {
    $rows = TelemedicineMedicationsPdfRows::normalize([
        [
            'medicines' => 'PARACETAMOL 500MG',
            'indications' => '1 CADA 8 HORAS',
            'duration' => '5',
            'quantity' => 2,
            'operation_inventory_id' => null,
        ],
    ]);

    expect($rows[0]['quantity'])->toBe(2)
        ->and($rows[0]['medicines'])->toBe('PARACETAMOL 500MG');
});

it('expone cantidad en formulario create y edit con descuento de inventario', function (): void {
    $form = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php'
    );
    $create = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php'
    );
    $edit = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/EditTelemedicineConsultationPatient.php'
    );
    $model = file_get_contents(dirname(__DIR__, 2).'/app/Models/TelemedicinePatientMedications.php');

    expect($form)
        ->toContain("TextInput::make('quantity')")
        ->toContain("TableColumn::make('Cantidad')")
        ->toContain('no descuenta inventario')
        ->toContain("filled(\$get('operation_inventory_id'))");

    expect($create)
        ->toContain('quantityFromRow')
        ->toContain('quantityForInventoryDeduction')
        ->toContain('->quantity =');

    expect($edit)
        ->toContain('quantityFromRow')
        ->toContain('quantityForInventoryDeduction')
        ->toContain('->quantity =');

    expect($model)->toContain("'quantity'");
});
