<?php

declare(strict_types=1);

it('restringe historias a pacientes managed_by ATENMEDI cuando el usuario pertenece a ATENMEDI', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineHistoryPatients/Tables/TelemedicineHistoryPatientsTable.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('modifyQueryUsing')
        ->toContain("in_array('ATENMEDI', Auth::user()?->departament ?? [], true)")
        ->toContain("whereHas('telemedicinePatient'")
        ->toContain("->where('managed_by', 'ATENMEDI')");
});
