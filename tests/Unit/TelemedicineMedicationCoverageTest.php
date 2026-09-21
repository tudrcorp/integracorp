<?php

declare(strict_types=1);

use App\Models\OperationInventory;
use App\Models\TelemedicinePatientMedications;
use App\Support\Telemedicine\TelemedicineMedicationCoverage;

it('usa cobertura del inventario cuando el medicamento está vinculado', function (): void {
    $inventory = new OperationInventory;
    $inventory->is_covered = true;

    $medication = new TelemedicinePatientMedications;
    $medication->operation_inventory_id = 1;
    $medication->setRelation('operationInventory', $inventory);

    expect(TelemedicineMedicationCoverage::isCovered($medication))->toBeTrue()
        ->and(TelemedicineMedicationCoverage::originLabel($medication))->toBe('Inventario TDC');
});

it('usa cobertura del inventario cuando el inventario indica no cubierto', function (): void {
    $inventory = new OperationInventory;
    $inventory->is_covered = false;

    $medication = new TelemedicinePatientMedications;
    $medication->operation_inventory_id = 1;
    $medication->setRelation('operationInventory', $inventory);

    expect(TelemedicineMedicationCoverage::isCovered($medication))->toBeFalse();
});

it('trata medicamento manual sin inventario como no cubierto', function (): void {
    $medication = new TelemedicinePatientMedications;
    $medication->operation_inventory_id = null;
    $medication->is_covered = false;

    expect(TelemedicineMedicationCoverage::isCovered($medication))->toBeFalse()
        ->and(TelemedicineMedicationCoverage::isManualMedication($medication))->toBeTrue()
        ->and(TelemedicineMedicationCoverage::isCoveredWithoutInventory($medication))->toBeFalse()
        ->and(TelemedicineMedicationCoverage::originLabel($medication))->toBe('No cubierto');
});

it('trata medicamento cubierto sin inventario como cubierto de gestión operaciones', function (): void {
    $medication = new TelemedicinePatientMedications;
    $medication->operation_inventory_id = null;
    $medication->is_covered = true;

    expect(TelemedicineMedicationCoverage::isCovered($medication))->toBeTrue()
        ->and(TelemedicineMedicationCoverage::isCoveredWithoutInventory($medication))->toBeTrue()
        ->and(TelemedicineMedicationCoverage::isManualMedication($medication))->toBeTrue()
        ->and(TelemedicineMedicationCoverage::coverageLabel($medication))->toBe('Cubierto (gestión Operaciones)')
        ->and(TelemedicineMedicationCoverage::originLabel($medication))->toBe('Cubierto sin inventario');
});

it('persiste medicamento manual como no cubierto al guardar', function (): void {
    expect(TelemedicineMedicationCoverage::coverageForPersist(null))->toBeFalse()
        ->and(TelemedicineMedicationCoverage::coverageForPersist(null, true))->toBeTrue();
});

it('arma payload de persistencia sin descontar inventario para cubierto externo', function (): void {
    $payload = TelemedicineMedicationCoverage::persistPayloadFromRow([
        'covered_medicines' => 'AMOXICILINA 500MG',
        'medicines' => null,
        'operation_inventory_id' => 44,
    ]);

    expect($payload)->not->toBeNull()
        ->and($payload['medicine'])->toBe('AMOXICILINA 500MG')
        ->and($payload['operation_inventory_id'])->toBeNull()
        ->and($payload['is_covered'])->toBeTrue()
        ->and($payload['should_deduct_inventory'])->toBeFalse();
});

it('arma payload de persistencia no cubierto sin inventario', function (): void {
    $payload = TelemedicineMedicationCoverage::persistPayloadFromRow([
        'covered_medicines' => '',
        'medicines' => 'IBUPROFENO 400MG',
        'operation_inventory_id' => null,
    ]);

    expect($payload)->not->toBeNull()
        ->and($payload['medicine'])->toBe('IBUPROFENO 400MG')
        ->and($payload['is_covered'])->toBeFalse()
        ->and($payload['should_deduct_inventory'])->toBeFalse();
});

it('rechaza mezclar fuentes de medicamento en la misma fila', function (): void {
    expect(TelemedicineMedicationCoverage::exclusiveSourceError([
        'operation_inventory_id' => 1,
        'covered_medicines' => 'AMOXICILINA',
        'medicines' => null,
    ], 2))->toContain('fila 2')
        ->and(TelemedicineMedicationCoverage::exclusiveSourceError([
            'operation_inventory_id' => null,
            'covered_medicines' => 'AMOXICILINA',
            'medicines' => null,
        ], 1))->toBeNull();
});

it('registra is_covered al crear medicamentos en telemedicina', function (): void {
    $createPath = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php');
    $editPath = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/EditTelemedicineConsultationPatient.php');
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php');

    expect($createPath)
        ->toContain('TelemedicineMedicationCoverage::persistPayloadFromRow')
        ->toContain('should_deduct_inventory')
        ->toContain('is_covered');

    expect($editPath)
        ->toContain('TelemedicineMedicationCoverage::persistPayloadFromRow')
        ->toContain('should_deduct_inventory')
        ->toContain('is_covered');

    expect($form)
        ->toContain("TextInput::make('covered_medicines')")
        ->toContain("TableColumn::make('Cubierto (Operaciones)')")
        ->toContain('exclusiveSourceError');
});
