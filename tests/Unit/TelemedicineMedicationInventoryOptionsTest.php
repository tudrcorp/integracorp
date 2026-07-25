<?php

declare(strict_types=1);

use App\Models\TelemedicineCase;
use App\Models\TelemedicineDoctor;
use App\Support\Telemedicine\TelemedicineMedicationInventoryOptions;

it('mapea belongs_to del caso al almacén de inventario', function (): void {
    expect(TelemedicineMedicationInventoryOptions::warehouseNameForBelongsTo('Diagnomovil'))
        ->toBe('DIAGNOMOVIL')
        ->and(TelemedicineMedicationInventoryOptions::warehouseNameForBelongsTo('Centro Diagnostico 3 de Febrero'))
        ->toBe('3 DE FEBRERO')
        ->and(TelemedicineMedicationInventoryOptions::warehouseNameForBelongsTo('Otro proveedor'))
        ->toBeNull();
});

it('detecta doctor TDG y proveedor', function (): void {
    $tdg = new TelemedicineDoctor(['managed_by' => 'TDG', 'supplier_id' => null]);
    $provider = new TelemedicineDoctor(['managed_by' => 'ATENMEDI', 'supplier_id' => 12]);

    expect(TelemedicineMedicationInventoryOptions::doctorIsTdg($tdg))->toBeTrue()
        ->and(TelemedicineMedicationInventoryOptions::doctorBelongsToProvider($tdg))->toBeFalse()
        ->and(TelemedicineMedicationInventoryOptions::doctorIsTdg($provider))->toBeFalse()
        ->and(TelemedicineMedicationInventoryOptions::doctorBelongsToProvider($provider))->toBeTrue();
});

it('solo descuenta inventario cuando el doctor es TDG y belongs_to apunta a un almacén conocido', function (): void {
    $tdg = new TelemedicineDoctor(['managed_by' => 'TDG']);
    $provider = new TelemedicineDoctor(['managed_by' => 'ATENMEDI', 'supplier_id' => 5]);

    $diagnomovilCase = new TelemedicineCase(['belongs_to' => 'Diagnomovil']);
    $febreroCase = new TelemedicineCase(['belongs_to' => 'Centro Diagnostico 3 de Febrero']);
    $otherCase = new TelemedicineCase(['belongs_to' => 'Proveedor X']);

    expect(TelemedicineMedicationInventoryOptions::shouldDeductInventory($tdg, $diagnomovilCase))->toBeTrue()
        ->and(TelemedicineMedicationInventoryOptions::shouldDeductInventory($tdg, $febreroCase))->toBeTrue()
        ->and(TelemedicineMedicationInventoryOptions::shouldDeductInventory($tdg, $otherCase))->toBeFalse()
        ->and(TelemedicineMedicationInventoryOptions::shouldDeductInventory($provider, $diagnomovilCase))->toBeFalse();
});

it('el formulario de consulta usa opciones filtradas de inventario de medicamentos', function (): void {
    $form = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php'
    );

    expect($form)
        ->toContain('TelemedicineMedicationInventoryOptions::optionsForCase')
        ->not->toContain('OperationInventory::all()->pluck');
});

it('la consulta de medicamentos TDG exige categoría Medicamento y existencia mayor a cero', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineMedicationInventoryOptions.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("->where('existence', '>', 0)")
        ->toContain("UPPER(name) LIKE ?', ['MEDICAMENTO%']")
        ->toContain('uniqueMedicationCatalogOptions')
        ->toContain('WAREHOUSE_DIAGNOMOVIL')
        ->toContain('WAREHOUSE_3_DE_FEBRERO');
});
