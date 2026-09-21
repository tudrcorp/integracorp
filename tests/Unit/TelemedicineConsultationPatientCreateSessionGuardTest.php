<?php

declare(strict_types=1);

it('valida el contexto por URL antes de inicializar el formulario de creación', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php';
    $contents = file_get_contents($path);

    $fromRequestPosition = strpos($contents, 'resolveConsultationContextFromRequest');
    $mountPosition = strpos($contents, 'parent::mount();');

    expect($fromRequestPosition)->not->toBeFalse()
        ->and($mountPosition)->not->toBeFalse()
        ->and($fromRequestPosition)->toBeLessThan($mountPosition)
        ->and($contents)->toContain('function consultationFormContext(): ConsultationFormContext')
        ->and($contents)->toContain('ConsultationClinicalSelections::fromFormData')
        ->and($contents)->toContain('if (! $this->patient instanceof TelemedicinePatient || ! $this->case instanceof TelemedicineCase)');
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

it('el schema de consulta toma el caso del contexto Livewire, no de la sesion global', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('function formContext(Schema $schema): ConsultationFormContext')
        ->toContain('ProvidesConsultationFormContext')
        ->toContain('$caseId = $context->caseId();')
        ->and($contents)->not->toContain("session()->get('case')")
        ->and($contents)->not->toContain("session()->get('patient')");
});

it('los puntos de entrada abren la consulta con caseId en la URL', function (): void {
    $dash = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Widgets/TelemedicineCaseTableDash.php');
    $cases = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineCases/Tables/TelemedicineCasesTable.php');
    $patients = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicinePatients/Tables/TelemedicinePatientsTable.php');
    $relation = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicinePatients/RelationManagers/TelemedicineCasesRelationManager.php');
    $registrar = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/LabImagingResultsFollowUpRegistrar.php');

    expect($dash)->toContain('ConsultationCreateRoute::url')
        ->and($patients)->toContain('ConsultationCreateRoute::url')
        ->and($cases)->toContain('LabImagingResultsFollowUpRegistrar::startDoctorFollowUp')
        ->and($relation)->toContain('LabImagingResultsFollowUpRegistrar::startDoctorFollowUp')
        ->and($registrar)->toContain('ConsultationCreateRoute::url');
});
