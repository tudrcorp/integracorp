<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\TelemedicinePatients\Tables\TelemedicinePatientsTable;
use App\Models\TelemedicinePatient;

it('supplierSummaryDescription combina nombre de proveedor y gestionado por', function (): void {
    $record = new TelemedicinePatient([
        'managed_by' => 'ATENMEDI',
    ]);
    $record->setRelation('supplier', (object) ['name' => 'CORPORACION VMC, C.A.']);

    $method = new ReflectionMethod(TelemedicinePatientsTable::class, 'supplierSummaryDescription');

    expect($method->invoke(null, $record))->toBe('CORPORACION VMC, C.A. (ATENMEDI)');
});

it('patientIdentificationDescription muestra cedula y telefono en la columna paciente', function (): void {
    $record = new TelemedicinePatient([
        'nro_identificacion' => 'V-12.345.678',
        'phone' => '04141234567',
    ]);

    $method = new ReflectionMethod(TelemedicinePatientsTable::class, 'patientIdentificationDescription');

    expect($method->invoke(null, $record))->toBe('C.I. V-12.345.678 · 04141234567');
});
