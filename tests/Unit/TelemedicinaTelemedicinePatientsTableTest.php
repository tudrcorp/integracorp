<?php

declare(strict_types=1);

it('aplica estilo ios y acciones en tabla de pacientes telemedicina', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicinePatients/Tables/TelemedicinePatientsTable.php');

    expect($contents)
        ->toContain('telemedicine-case-table-ios')
        ->toContain('telemedicine-patients-table')
        ->toContain('Pacientes asignados')
        ->toContain('Hacer consulta')
        ->toContain('Historia clínica')
        ->toContain('whereHas(\'telemedicineCases\'')
        ->toContain('userIsInAtenmediTelemedicinaContext')
        ->toContain('telemedicine-patient-email-column')
        ->toContain("TextColumn::make('email')")
        ->toContain('Str::limit((string) $state, 22)');
});
