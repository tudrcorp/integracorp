<?php

declare(strict_types=1);

it('filtra doctores por managed_by ATENMEDI cuando el usuario pertenece a ATENMEDI', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicinePatients/Tables/TelemedicinePatientsTable.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->not->toContain("in_array('OPERACIONES', \$departments, true)")
        ->toContain("in_array('ATENMEDI', \$departments, true)")
        ->toContain("->where('managed_by', 'ATENMEDI')")
        ->toContain('mapWithKeys(fn (TelemedicineDoctor $doctor)')
        ->toContain('managed_by) ? $doctor->managed_by');
});
