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

it('usa is_covered del medicamento sin inventario vinculado', function (): void {
    $medication = new TelemedicinePatientMedications;
    $medication->operation_inventory_id = null;
    $medication->is_covered = true;

    expect(TelemedicineMedicationCoverage::isCovered($medication))->toBeTrue();
});
