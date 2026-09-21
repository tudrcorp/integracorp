<?php

declare(strict_types=1);

use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicinePatient;
use App\Support\Telemedicine\ConsultationClinicalSelections;
use App\Support\Telemedicine\ConsultationFormContext;

it('detecta edicion de consulta inicial y servicio por defecto', function (): void {
    $consultation = new TelemedicineConsultationPatient([
        'telemedicine_service_list_id' => 3,
        'telemedicine_service_list_drift_id' => 8,
    ]);

    $case = new TelemedicineCase;
    $case->id = 1;
    $patient = new TelemedicinePatient;
    $patient->id = 2;

    $context = new ConsultationFormContext(
        case: $case,
        patient: $patient,
        consultation: $consultation,
        action: 'edit',
        status: 'CONSULTA INICIAL',
    );

    expect($context->isEditingInitialConsultation())->toBeTrue()
        ->and($context->defaultServiceListId())->toBe(8)
        ->and($context->caseId())->toBe(1)
        ->and($context->patientId())->toBe(2);
});

it('las selecciones clinicas viven fuera de la sesion', function (): void {
    $selections = ConsultationClinicalSelections::fromFormData([
        'medications' => [['medicine' => 'A']],
        'labs' => [['id' => 1]],
        'other_labs' => [['id' => 2]],
        'studies' => [],
        'other_studies' => [['id' => 3]],
        'consult_specialist' => [['id' => 4]],
        'other_specialist' => [],
        'feedbackOne' => true,
    ]);

    expect($selections->medications)->toHaveCount(1)
        ->and($selections->mergedLabs())->toHaveCount(2)
        ->and($selections->mergedStudies())->toHaveCount(1)
        ->and($selections->mergedSpecialists())->toHaveCount(1)
        ->and($selections->discharge)->toBeTrue();
});
