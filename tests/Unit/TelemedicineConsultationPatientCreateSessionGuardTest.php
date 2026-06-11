<?php

declare(strict_types=1);

it('valida la sesión antes de inicializar el formulario de creación', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php';
    $contents = file_get_contents($path);

    $sessionReadPosition = strpos($contents, '$this->patient = session()->get(\'patient\');');
    $mountPosition = strpos($contents, 'parent::mount();');

    expect($sessionReadPosition)->not->toBeFalse()
        ->and($mountPosition)->not->toBeFalse()
        ->and($sessionReadPosition)->toBeLessThan($mountPosition)
        ->and($contents)->toContain('if (! $this->patient || ! $this->case)');
});

it('resuelve telemedicine_service_list_drift_id sin error cuando no viene en el registro', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('$serviceListDriftId = (int) (')
        ->toContain("?? \$this->data['telemedicine_service_list_drift_id']")
        ->toContain('if ($serviceListDriftId === 3)')
        ->toContain('if ($serviceListDriftId === 8)');
});

it('usa un id de caso seguro en el schema de consulta', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('$caseId = $case?->id;')
        ->toContain('TelemedicineConsultationPatient::where(\'telemedicine_case_id\', $caseId)->count()')
        ->toContain(': 0;');
});
