<?php

declare(strict_types=1);

it('el formulario de consulta incluye el botón Informar AMD visible solo para servicio AMD', function (): void {
    $root = dirname(__DIR__, 2);
    $form = file_get_contents(
        $root.'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php'
    );

    expect($form)
        ->toContain('inform-amd-trigger')
        ->toContain('informAmdTrigger')
        ->toContain('TelemedicineCaseTdgReassignmentCoordination::AMD_SERVICE_LIST_ID');

    $trigger = file_get_contents($root.'/resources/views/filament/telemedicina/consultations/inform-amd-trigger.blade.php');
    expect($trigger)->toContain("mountAction('informAmd')")
        ->and($trigger)->toContain("mountAction('uploadAmdFile')")
        ->and($trigger)->toContain('Cargar Archivo AMD');
});

it('TelemedicineAmdFileRegistrar guarda archivos AMD en documentos del caso', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineAmdFileRegistrar.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('TelemedicineDocument::query()->create')
        ->toContain('attachPendingToConsultation')
        ->toContain('ARCHIVO AMD')
        ->toContain('syncConsultationUploadedDocument');
});

it('HasInformAmdModal incluye la acción uploadAmdFile para cargar archivos AMD', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Concerns/HasInformAmdModal.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('uploadAmdFileAction')
        ->toContain("Action::make('uploadAmdFile')")
        ->toContain('TelemedicineAmdFileRegistrar::register');
});

it('CreateTelemedicineConsultationPatient omite el informe largo automático cuando el servicio es AMD', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('TelemedicineCaseTdgReassignmentCoordination::AMD_SERVICE_LIST_ID')
        ->toContain('if (! $isAmdService)')
        ->toContain('GeneratePdfInformeMedicoLargo')
        ->toContain('TelemedicineAmdInformRegistrar::attachPendingToConsultation')
        ->toContain('TelemedicineAmdFileRegistrar::attachPendingToConsultation');
});

it('TelemedicineAmdInformRegistrar soporta registro pendiente y vinculación posterior', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineAmdInformRegistrar.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('registerPending')
        ->toContain('attachPendingToConsultation')
        ->toContain('whereNull(\'telemedicine_consultation_patient_id\')')
        ->toContain('ensureInformPdfExists')
        ->toContain('TelemedicineInformeLargoPdfGenerator::generateAndSave')
        ->toContain('syncConsultationUploadedDocument')
        ->toContain('Schema::hasColumn(\'telemedicine_documents\'');
});

it('TelemedicineInformeLargoPdfGenerator guarda el PDF en telemedicina-doc', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineInformeLargoPdfGenerator.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('informe-medico-largo')
        ->toContain('telemedicina-doc')
        ->toContain('fileExists');
});

it('HasInformAmdModal permite informar AMD antes de guardar la consulta', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Concerns/HasInformAmdModal.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('registerPending')
        ->toContain('informAmdPendingContext')
        ->toContain('pendingAmdInformId')
        ->not->toContain('Debe guardar la consulta antes de informar AMD');
});

it('TelemedicineInformeLargoDataBuilder arma los datos del informe largo desde contexto del formulario', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineInformeLargoDataBuilder.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('buildFromContext')
        ->toContain("'telemedicine_consultation_id'")
        ->toContain("'pa'")
        ->toContain("'saturacion'")
        ->toContain('pdfDocumentName');
});

it('la migración telemedicine_amd_informs define las relaciones estándar', function (): void {
    $path = dirname(__DIR__, 2).'/database/migrations/2026_06_15_114307_create_telemedicine_amd_informs_table.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('telemedicine_patient_id')
        ->toContain('telemedicine_case_id')
        ->toContain('telemedicine_consultation_patient_id')
        ->toContain('telemedicine_doctor_id')
        ->toContain('supplier_id')
        ->toContain('pdf_document_name');
});

it('la migración permite informes AMD sin consulta vinculada inicialmente', function (): void {
    $path = dirname(__DIR__, 2).'/database/migrations/2026_06_15_115127_make_telemedicine_consultation_patient_id_nullable_on_amd_informs.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('telemedicine_consultation_patient_id')
        ->toContain('nullable');
});

it('existe migración para uploaded_documents en telemedicine_consultation_patients', function (): void {
    $path = dirname(__DIR__, 2).'/database/migrations/2026_06_15_115557_add_uploaded_documents_to_telemedicine_consultation_patients_table.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('uploaded_documents')
        ->toContain('telemedicine_consultation_patients');
});
