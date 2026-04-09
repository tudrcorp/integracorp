<?php

declare(strict_types=1);

use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicinePatient;
use App\Support\Telemedicine\ConsultationCreateWizardDefaults;

uses(Tests\TestCase::class);

it('arma el paso datos del paciente desde caso y paciente', function (): void {
    $case = new TelemedicineCase([
        'telemedicine_patient_id' => 20,
        'telemedicine_doctor_id' => 30,
        'code' => 'CASO-99',
        'patient_name' => 'María Caso',
        'patient_age' => '45',
        'patient_sex' => 'F',
        'patient_phone' => '04141234567',
        'patient_phone_2' => '04241234567',
        'patient_address' => 'Av. Principal',
        'directionAmbulance' => 'Zona industrial',
    ]);
    $case->id = 10;

    $patient = new TelemedicinePatient([
        'full_name' => 'María Paciente',
        'nro_identificacion' => '12345678',
        'sex' => 'F',
        'age' => '45',
    ]);

    $state = ConsultationCreateWizardDefaults::formStatePatientStepFromCaseAndPatient($case, $patient, 7, 0);

    expect($state['telemedicine_case_id'])->toBe(10)
        ->and($state['telemedicine_doctor_id'])->toBe(30)
        ->and($state['telemedicine_patient_id'])->toBe(20)
        ->and($state['assigned_by'])->toBe(7)
        ->and($state['status'])->toBe('CONSULTA INICIAL')
        ->and($state['telemedicine_case_code'])->toBe('CASO-99')
        ->and($state['full_name'])->toBe('María Paciente')
        ->and($state['nro_identificacion'])->toBe('12345678')
        ->and($state['phone_ppal'])->toBe('04141234567')
        ->and($state['phone_secondary'])->toBe('04241234567')
        ->and($state['address'])->toBe('Av. Principal')
        ->and($state['directionAmbulance'])->toBe('Zona industrial')
        ->and($state['code_reference'])->toStartWith('REF-');
});

it('usa estatus de seguimiento cuando ya hay consultas en el caso', function (): void {
    $case = new TelemedicineCase([
        'telemedicine_patient_id' => 2,
        'telemedicine_doctor_id' => 3,
        'code' => 'X',
        'patient_phone' => null,
        'patient_phone_2' => null,
        'patient_address' => null,
    ]);
    $case->id = 1;
    $patient = new TelemedicinePatient(['full_name' => 'A', 'nro_identificacion' => '1']);

    $state = ConsultationCreateWizardDefaults::formStatePatientStepFromCaseAndPatient($case, $patient, 1, 2);

    expect($state['status'])->toBe('EN SEGUIMIENTO');
});

it('rellena tipo de servicio solo con el id derivado de la última consulta', function (): void {
    $consultation = new TelemedicineConsultationPatient([
        'telemedicine_service_list_id' => 5,
        'telemedicine_service_list_drift_id' => 7,
    ]);

    $state = ConsultationCreateWizardDefaults::formStatePrefillFromLastConsultation($consultation);

    expect($state['telemedicine_service_list_id'])->toBe(7);
});

it('no incluye tipo de servicio si no hay servicio derivado', function (): void {
    $consultation = new TelemedicineConsultationPatient([
        'telemedicine_service_list_id' => 5,
        'telemedicine_service_list_drift_id' => null,
    ]);

    $state = ConsultationCreateWizardDefaults::formStatePrefillFromLastConsultation($consultation);

    expect($state)->not->toHaveKey('telemedicine_service_list_id');
});

it('prefill clínico no incluye nombre ni cédula', function (): void {
    $consultation = new TelemedicineConsultationPatient([
        'full_name' => 'Otro',
        'nro_identificacion' => '999',
        'reason_consultation' => 'Control',
        'telemedicine_service_list_drift_id' => 2,
    ]);

    $state = ConsultationCreateWizardDefaults::formStatePrefillFromLastConsultation($consultation);

    expect($state)->not->toHaveKey('full_name')
        ->and($state)->not->toHaveKey('nro_identificacion')
        ->and($state['reason_consultation'])->toBe('Control')
        ->and($state['telemedicine_service_list_id'])->toBe(2);
});
