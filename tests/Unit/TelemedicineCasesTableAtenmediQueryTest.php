<?php

declare(strict_types=1);

it('restringe casos a managed_by ATENMEDI cuando el usuario pertenece a ATENMEDI', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineCases/Tables/TelemedicineCasesTable.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('modifyQueryUsing')
        ->toContain("in_array('ATENMEDI', Auth::user()?->departament ?? [], true)")
        ->toContain("->where('managed_by', 'ATENMEDI')");
});

it('abre modal de paciente desde la columna número de caso', function (): void {
    $base = dirname(__DIR__, 2);

    expect(file_exists($base.'/resources/views/filament/operations/telemedicine-cases/patient-summary-modal.blade.php'))
        ->toBeTrue();

    $contents = file_get_contents($base.'/app/Filament/Operations/Resources/TelemedicineCases/Tables/TelemedicineCasesTable.php');

    expect($contents)
        ->toContain('openPatientSummaryFromCase')
        ->toContain('patient-summary-modal')
        ->toContain('TextColumn::make(\'code\')');
});

it('precarga datos de paciente para el modal desde el recurso de casos', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineCases/TelemedicineCaseResource.php');

    expect($contents)
        ->toContain('telemedicinePatient.afilliation')
        ->toContain('telemedicinePatient.plan');
});

it('oculta relation managers de observaciones y referencias médicas en el recurso', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineCases/TelemedicineCaseResource.php');

    expect($contents)
        ->toContain("'consultations' => ConsultationsRelationManager::class")
        ->not->toContain('ObservationsRelationManager')
        ->not->toContain('TelemedicineDocumentsRelationManager');
});
