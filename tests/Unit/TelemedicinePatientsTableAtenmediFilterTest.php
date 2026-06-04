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
        ->toContain("TextColumn::make('supplier.name')")
        ->toContain("TextColumn::make('supplier_id')")
        ->toContain('fi-telemedicine-patient-supplier-id-cell');
});
