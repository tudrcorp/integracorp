<?php

declare(strict_types=1);

use App\Models\OperationCoordinationService;
use App\Models\TelemedicineConsultationPatient;
use App\Support\Operations\LabImagingResultsFollowUpRegistrar;

it('reconoce los tipos de documento de resultados de laboratorio e imagenología', function (): void {
    expect(LabImagingResultsFollowUpRegistrar::isResultDocumentTypeName('RESULTADOS DE LABORATORIO'))->toBeTrue()
        ->and(LabImagingResultsFollowUpRegistrar::isResultDocumentTypeName('informe de resultados de laboratorios'))->toBeTrue()
        ->and(LabImagingResultsFollowUpRegistrar::isResultDocumentTypeName('INFORME DE ESTUDIO Y/O IMAGENOLOGÍA'))->toBeTrue()
        ->and(LabImagingResultsFollowUpRegistrar::isResultDocumentTypeName('COMPROBANTE DE ENTREGA DE LABORATORIOS'))->toBeFalse()
        ->and(LabImagingResultsFollowUpRegistrar::isResultDocumentTypeName(null))->toBeFalse();
});

it('reconoce servicios de laboratorio e imagenología por tipo de orden y por clave de ítem', function (): void {
    expect(LabImagingResultsFollowUpRegistrar::isLabOrImagingServiceType('LABORATORIOS'))->toBeTrue()
        ->and(LabImagingResultsFollowUpRegistrar::isLabOrImagingServiceType('imagenologia'))->toBeTrue()
        ->and(LabImagingResultsFollowUpRegistrar::isLabOrImagingServiceType('MEDICAMENTOS'))->toBeFalse()
        ->and(LabImagingResultsFollowUpRegistrar::serviceKeysAreLabOrImaging(['lab:12', 'medication:3']))->toBeTrue()
        ->and(LabImagingResultsFollowUpRegistrar::serviceKeysAreLabOrImaging(['study:8']))->toBeTrue()
        ->and(LabImagingResultsFollowUpRegistrar::serviceKeysAreLabOrImaging(['medication:3', 'specialty:1']))->toBeFalse()
        ->and(LabImagingResultsFollowUpRegistrar::serviceKeysAreLabOrImaging([]))->toBeFalse();
});

it('solo dispara el seguimiento cuando hay resultado clínico y contexto de lab o imagenología', function (): void {
    $labResult = [
        'document_types' => ['RESULTADOS DE LABORATORIO'],
        'document_type_ids' => [10],
        'service_item_keys' => ['lab:1'],
        'file_path' => 'docs/a.pdf',
    ];
    $imagingResult = [
        'document_types' => ['INFORME DE ESTUDIO Y/O IMAGENOLOGIA'],
        'service_item_keys' => ['study:4'],
        'file_path' => 'docs/b.pdf',
    ];
    $receipt = [
        'document_types' => ['COMPROBANTE DE ENTREGA DE LABORATORIOS'],
        'service_item_keys' => ['lab:1'],
        'file_path' => 'docs/c.pdf',
    ];
    $resultWithoutService = [
        'document_types' => ['RESULTADOS DE LABORATORIO'],
        'service_item_keys' => [],
        'file_path' => 'docs/d.pdf',
    ];
    $medicationResult = [
        'document_types' => ['RESULTADOS DE LABORATORIO'],
        'service_item_keys' => ['medication:9'],
        'file_path' => 'docs/e.pdf',
    ];

    expect(LabImagingResultsFollowUpRegistrar::shouldRegister([$labResult], null, false))->toBeTrue()
        ->and(LabImagingResultsFollowUpRegistrar::shouldRegister([$imagingResult], null, false))->toBeTrue()
        ->and(LabImagingResultsFollowUpRegistrar::shouldRegister([$receipt], 'LABORATORIOS', true))->toBeFalse()
        ->and(LabImagingResultsFollowUpRegistrar::shouldRegister([$resultWithoutService], 'LABORATORIOS', false))->toBeTrue()
        ->and(LabImagingResultsFollowUpRegistrar::shouldRegister([$resultWithoutService], null, true))->toBeTrue()
        ->and(LabImagingResultsFollowUpRegistrar::shouldRegister([$resultWithoutService], null, false))->toBeFalse()
        ->and(LabImagingResultsFollowUpRegistrar::shouldRegister([$medicationResult], null, false))->toBeFalse()
        ->and(LabImagingResultsFollowUpRegistrar::shouldRegister([], 'LABORATORIOS', true))->toBeFalse();
});

it('resuelve el tipo de documento por id cuando el nombre no viene en el archivo', function (): void {
    $document = [
        'document_type_ids' => [22],
        'document_types' => [],
        'service_item_keys' => ['lab:1'],
    ];

    expect(LabImagingResultsFollowUpRegistrar::documentHasResultType($document))->toBeFalse()
        ->and(LabImagingResultsFollowUpRegistrar::documentHasResultType(
            $document,
            [22 => 'INFORME DE RESULTADOS DE LABORATORIOS'],
        ))->toBeTrue();
});

it('considera incompleto solo el seguimiento de lectura pendiente sin notas clínicas', function (): void {
    $pending = new TelemedicineConsultationPatient([
        'telemedicine_service_list_id' => 4,
        'status' => 'EN SEGUIMIENTO',
        'current_illness_history' => null,
        'patient_evolution' => null,
    ]);
    $filled = new TelemedicineConsultationPatient([
        'telemedicine_service_list_id' => 4,
        'status' => 'EN SEGUIMIENTO',
        'current_illness_history' => 'El paciente evolucionó.',
        'patient_evolution' => null,
    ]);
    $otherService = new TelemedicineConsultationPatient([
        'telemedicine_service_list_id' => 1,
        'status' => 'EN SEGUIMIENTO',
    ]);

    expect(LabImagingResultsFollowUpRegistrar::isIncompleteReadingFollowUp($pending, 4))->toBeTrue()
        ->and(LabImagingResultsFollowUpRegistrar::isIncompleteReadingFollowUp($filled, 4))->toBeFalse()
        ->and(LabImagingResultsFollowUpRegistrar::isIncompleteReadingFollowUp($otherService, 4))->toBeFalse();
});

it('fusiona documentos de resultados sin duplicar la misma ruta', function (): void {
    $existing = [
        ['file_path' => 'docs/a.pdf', 'document_name' => 'a'],
        ['file_path' => 'docs/b.pdf', 'document_name' => 'b'],
    ];
    $incoming = [
        ['file_path' => 'docs/a.pdf', 'document_name' => 'a-dup'],
        ['file_path' => 'docs/c.pdf', 'document_name' => 'c'],
    ];

    $merged = LabImagingResultsFollowUpRegistrar::mergeUploadedDocuments($existing, $incoming);

    expect($merged)->toHaveCount(3)
        ->and(collect($merged)->pluck('file_path')->all())->toBe(['docs/a.pdf', 'docs/b.pdf', 'docs/c.pdf']);
});

it('arma la ficha del seguimiento con el servicio de lectura y los resultados adjuntos', function (): void {
    $case = new App\Models\TelemedicineCase([
        'code' => 'TDG-TEST',
        'patient_name' => 'PACIENTE PRUEBA',
        'telemedicine_doctor_id' => 7,
        'telemedicine_priority_id' => 2,
    ]);
    $case->id = 99;

    $patient = new App\Models\TelemedicinePatient([
        'full_name' => 'PACIENTE PRUEBA',
        'nro_identificacion' => '12345678',
    ]);
    $patient->id = 15;

    $coordination = new OperationCoordinationService([
        'telemedicine_doctor_id' => 7,
    ]);

    $last = new TelemedicineConsultationPatient([
        'actual_phatology' => 'HTA',
        'diagnostic_impression' => 'CONTROL',
        'pa' => '120/80',
        'assigned_by' => 33,
        'telemedicine_priority_id' => 5,
    ]);

    $attributes = LabImagingResultsFollowUpRegistrar::consultationAttributes(
        $case,
        $patient,
        $coordination,
        4,
        [['file_path' => 'docs/resultado.pdf', 'document_types' => ['RESULTADOS DE LABORATORIO']]],
        $last,
    );

    expect($attributes['telemedicine_case_id'])->toBe(99)
        ->and($attributes['telemedicine_case_code'])->toBe('TDG-TEST')
        ->and($attributes['telemedicine_patient_id'])->toBe(15)
        ->and($attributes['telemedicine_service_list_id'])->toBe(4)
        ->and($attributes['status'])->toBe('EN SEGUIMIENTO')
        ->and($attributes['reason_consultation'])->toBe(LabImagingResultsFollowUpRegistrar::REASON_CONSULTATION)
        ->and($attributes['full_name'])->toBe('PACIENTE PRUEBA')
        ->and($attributes['actual_phatology'])->toBe('HTA')
        ->and($attributes['pa'])->toBe('120/80')
        ->and($attributes['assigned_by'])->toBe(33)
        ->and($attributes['uploaded_documents'])->toHaveCount(1);
});

it('no registra seguimiento si la coordinación no tiene caso vinculado', function (): void {
    $coordination = new OperationCoordinationService([
        'telemedicine_case_id' => null,
    ]);
    $coordination->setRelation('telemedicinePatientLabs', collect());
    $coordination->setRelation('telemedicinePatientStudies', collect());

    $result = LabImagingResultsFollowUpRegistrar::register($coordination, [[
        'document_types' => ['RESULTADOS DE LABORATORIO'],
        'service_item_keys' => ['lab:1'],
        'file_path' => 'docs/a.pdf',
    ]]);

    expect($result['triggered'])->toBeFalse()
        ->and($result['consultation'])->toBeNull()
        ->and($result['message'])->toBeNull();
});

it('expone el sufijo de notificación solo cuando hubo seguimiento', function (): void {
    expect(LabImagingResultsFollowUpRegistrar::analystMessageSuffix(LabImagingResultsFollowUpRegistrar::emptyResult()))->toBe('')
        ->and(LabImagingResultsFollowUpRegistrar::analystMessageSuffix([
            'message' => 'Se generó un seguimiento de lectura de resultados en el caso TDG-1.',
        ]))->toBe(' Se generó un seguimiento de lectura de resultados en el caso TDG-1.');
});

it('la carga de documentos de coordinación dispara el registrador de lectura de resultados', function (): void {
    $view = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Pages/ViewOperationCoordinationService.php');
    $finalizer = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/CoordinationServiceCoveredItemsFinalizer.php');
    $order = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationServiceOrders/Pages/ViewOperationServiceOrder.php');

    expect($view)
        ->toContain('LabImagingResultsFollowUpRegistrar::register($record, $newDocuments)')
        ->and($finalizer)->toContain('LabImagingResultsFollowUpRegistrar::register($record, $newDocuments)')
        ->and($order)->toContain('LabImagingResultsFollowUpRegistrar::registerFromServiceOrder($record, $newDocuments)');
});

it('el médico abre el mismo asistente de seguimiento y no la ficha de edición', function (): void {
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineCases/Tables/TelemedicineCasesTable.php');
    $relation = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicinePatients/RelationManagers/TelemedicineCasesRelationManager.php');
    $dash = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Widgets/TelemedicineCaseTableDash.php');
    $registrar = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/LabImagingResultsFollowUpRegistrar.php');
    $create = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php');
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php');
    $preview = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/telemedicina/consultations/lab-imaging-results-preview.blade.php');

    expect($table)
        ->toContain('LabImagingResultsFollowUpRegistrar::startDoctorFollowUp')
        ->and($relation)->toContain('LabImagingResultsFollowUpRegistrar::startDoctorFollowUp')
        ->and($dash)->toContain('LabImagingResultsFollowUpRegistrar::startDoctorFollowUp')
        ->and($registrar)->toContain('ConsultationCreateRoute::url')
        ->and($registrar)->not->toContain('ConsultationEditSession::storeForEdit')
        ->and($registrar)->not->toContain('telemedicine-consultation-patients.edit')
        ->and($registrar)->toContain("->update(['status' => self::CONSULTATION_STATUS])")
        ->and($create)->toContain("Action::make('preview_lab_imaging_results')")
        ->and($create)->toContain('FilamentIosActionsMenu::make([')
        ->and($create)->toContain('lab-imaging-results-preview')
        ->and($create)->toContain('attachLabImagingResultDocuments')
        ->and($create)->toContain('discardPlaceholderIfReplaced')
        ->and($form)->toContain('labImagingResultsPreview')
        ->and($form)->toContain("Step::make('Cuestionario de Seguimiento')")
        ->and($preview)->toContain('Vista previa')
        ->and($preview)->toContain('Pestaña nueva')
        ->and($preview)->toContain('Resultados cargados por Operaciones')
        ->and($preview)->toContain('x-teleport="body"')
        ->and($preview)->toContain('h-[100dvh]')
        ->and($preview)->toContain('absolute inset-0 h-full w-full');
});

it('lista todos los resultados de laboratorio e imagenología sin duplicar archivos', function (): void {
    $documents = LabImagingResultsFollowUpRegistrar::collectResultPreviewDocuments([
        [
            'document_name' => 'hemograma',
            'file_path' => 'docs/hemo.pdf',
            'document_types' => ['RESULTADOS DE LABORATORIO'],
            'services' => ['Laboratorio: HEMOGRAMA'],
            'source' => 'Coordinación',
            'uploaded_at' => '2026-09-07 10:00:00',
        ],
        [
            'document_name' => 'eco',
            'file_path' => 'docs/eco.pdf',
            'document_types' => ['INFORME DE ESTUDIO Y/O IMAGENOLOGIA'],
            'uploaded_at' => '2026-09-07 11:00:00',
        ],
        [
            'document_name' => 'duplicado',
            'file_path' => 'docs/hemo.pdf',
            'document_types' => ['RESULTADOS DE LABORATORIO'],
        ],
        [
            'document_name' => 'entrega',
            'file_path' => 'docs/recibo.pdf',
            'document_types' => ['COMPROBANTE DE ENTREGA DE LABORATORIOS'],
        ],
    ]);

    expect($documents)->toHaveCount(2)
        ->and($documents[0]['file_path'])->toBe('docs/eco.pdf')
        ->and($documents[0]['is_pdf'])->toBeTrue()
        ->and($documents[1]['document_name'])->toBe('hemograma')
        ->and($documents[1]['services'])->toBe(['Laboratorio: HEMOGRAMA']);
});
