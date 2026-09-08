<?php

declare(strict_types=1);

use App\Support\Telemedicine\TelemedicineFollowUpReportDocument;
use App\Support\Telemedicine\TelemedicineInitialDiagnosisUpdater;

it('el informe de seguimiento aplica a todo acto que no sea consulta inicial', function (): void {
    expect(TelemedicineFollowUpReportDocument::appliesTo('EN SEGUIMIENTO'))->toBeTrue()
        ->and(TelemedicineFollowUpReportDocument::appliesTo('ALTA MEDICA'))->toBeTrue()
        ->and(TelemedicineFollowUpReportDocument::appliesTo(TelemedicineInitialDiagnosisUpdater::INITIAL_STATUS))->toBeFalse()
        ->and(TelemedicineFollowUpReportDocument::appliesTo(null))->toBeFalse()
        ->and(TelemedicineFollowUpReportDocument::appliesTo(''))->toBeFalse();
});

it('el job y la plantilla del informe de seguimiento cubren los tres campos clínicos', function (): void {
    $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/GeneratePdfInformeSeguimiento.php');
    $support = file_get_contents(dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineFollowUpReportDocument.php');
    $blade = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/informe-seguimiento.blade.php');
    $partial = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/partials/informe-medico-homologado.blade.php');
    $renderer = file_get_contents(dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineInformePdfRenderer.php');
    $create = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php');
    $edit = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/EditTelemedicineConsultationPatient.php');
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_09_07_200000_add_informe_seguimiento_to_operation_document_lists_table.php');

    expect($job)
        ->toContain('use Illuminate\Bus\Batchable;')
        ->toContain('TelemedicineInformePdfRenderer::VIEW_SEGUIMIENTO')
        ->toContain('TelemedicineConsultationUploadedDocuments::sync')
        ->toContain('TelemedicineFollowUpReportDocument::DOCUMENT_TYPE_NAME')
        ->not->toContain("\$this->onQueue('telemedicina')");

    expect($support)
        ->toContain("public const TYPE_DOCUMENT = 'informe-seguimiento'")
        ->toContain("public const DOCUMENT_TYPE_NAME = 'INFORME DE SEGUIMIENTO'")
        ->toContain("'current_illness_history'")
        ->toContain("'patient_evolution'")
        ->toContain("'diagnostic_impression'");

    expect($blade)->toContain("'variant' => 'seguimiento'");

    expect($partial)
        ->toContain("'corto' | 'largo' | 'seguimiento'")
        ->toContain('Informe de seguimiento')
        ->toContain('Diagnóstico principal de la consulta inicial')
        ->toContain('Historia de la enfermedad actual')
        ->toContain('Evolución del paciente');

    expect($renderer)->toContain("VIEW_SEGUIMIENTO = 'documents.informe-seguimiento'");

    expect($create)
        ->toContain('TelemedicineFollowUpReportDocument::makeJob')
        ->toContain('TelemedicineFollowUpReportDocument::payloadFromCreateData')
        ->toContain('TelemedicineFollowUpReportDocument::appliesTo')
        ->toContain('SendTelemedicineConsultationDocuments::dispatch(')
        ->not->toContain("SendTelemedicineConsultationDocuments::dispatch(\n                                    \$consultationId,\n                                    \$patientPhone,\n                                    \$patientEmail !== '' ? \$patientEmail : null,\n                                    \$patientName,\n                                    \$userId,\n                                )->onQueue('telemedicina')");

    expect($edit)
        ->toContain('GeneratePdfInformeSeguimiento::dispatch')
        ->toContain('payloadFromSavedConsultation');

    expect($migration)
        ->toContain("'INFORME DE SEGUIMIENTO'")
        ->toContain("hasTable('operation_document_lists')");
});
