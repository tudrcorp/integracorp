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

    expect(TelemedicineMedicationCoverage::isCovered($medication))->toBeTrue();
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
    $medication->is_covered = true;

    expect(TelemedicineMedicationCoverage::isCovered($medication))->toBeFalse()
        ->and(TelemedicineMedicationCoverage::isManualMedication($medication))->toBeTrue();
});

it('persiste medicamento manual como no cubierto al guardar', function (): void {
    expect(TelemedicineMedicationCoverage::coverageForPersist(null))->toBeFalse();
});

it('registra is_covered al crear medicamentos en telemedicina', function (): void {
    $createPath = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php');
    $editPath = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/EditTelemedicineConsultationPatient.php');

    expect($createPath)
        ->toContain('TelemedicineMedicationCoverage::coverageForPersist')
        ->toContain('is_covered');

    expect($editPath)
        ->toContain('TelemedicineMedicationCoverage::coverageForPersist')
        ->toContain('is_covered');
});
