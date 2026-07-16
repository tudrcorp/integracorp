<?php

declare(strict_types=1);

it('filtra por managed_by ATENMEDI cuando el usuario pertenece a ese departamento', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicinePatients/Tables/TelemedicinePatientsTable.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("in_array('ATENMEDI', Auth::user()?->departament ?? [], true)")
        ->toContain("->where('managed_by', 'ATENMEDI')")
        ->toContain('OperationsSupplierScope::applyToQuery($query)')
        ->toContain("'supplier'")
        ->toContain("TextColumn::make('full_name')")
        ->toContain('patientIdentificationDescription')
        ->toContain('patientSupplierSummaryLine')
        ->toContain('Proveedor: TUDRGROUP')
        ->toContain('Proveedor: #')
        ->not->toContain("TextColumn::make('supplier_id')")
        ->not->toContain("->label('Proveedor')")
        ->not->toContain('fi-telemedicine-patient-supplier-cell')
        ->not->toContain("TextColumn::make('supplier.name')")
        ->not->toContain('border-l-[3px]');
});
