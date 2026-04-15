<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicinePatient;

final class ConsultationCreateWizardDefaults
{
    /**
     * Campos del paso «Datos del Paciente» y hiddens asociados al caso y paciente en sesión.
     *
     * @return array<string, mixed>
     */
    public static function formStatePatientStepFromCaseAndPatient(
        TelemedicineCase $case,
        TelemedicinePatient $patient,
        int $assignedByUserId,
        int $countCase,
    ): array {
        return [
            'telemedicine_case_id' => $case->id,
            'telemedicine_doctor_id' => $case->telemedicine_doctor_id,
            'telemedicine_patient_id' => $case->telemedicine_patient_id,
            'assigned_by' => $assignedByUserId,
            'status' => $countCase < 1 ? 'CONSULTA INICIAL' : 'EN SEGUIMIENTO',
            'code_reference' => 'REF-'.random_int(10000, 99999),
            'telemedicine_case_code' => $case->code,
            'full_name' => $patient->full_name ?? $case->patient_name,
            'nro_identificacion' => $patient->nro_identificacion,
            'sex' => $patient->sex ?? $case->patient_sex,
            'age' => $patient->age ?? $case->patient_age,
            'phone_ppal' => $case->patient_phone,
            'phone_secondary' => $case->patient_phone_2,
            'address' => $case->patient_address,
            'directionAmbulance' => $case->directionAmbulance,
        ];
    }

    /**
     * Estado adicional desde la última consulta del mismo caso (clínica, prioridad, cadena servicio/derivado).
     * Si hubo servicio derivado, pasa a ser el nuevo `telemedicine_service_list_id`; si no, se conserva el principal.
     * No duplica identidad del paciente: esa viene de {@see formStatePatientStepFromCaseAndPatient}.
     *
     * @return array<string, mixed>
     */
    public static function formStatePrefillFromLastConsultation(TelemedicineConsultationPatient $last): array
    {
        $out = [];

        foreach ([
            'reason_consultation',
            'actual_phatology',
            'background',
            'diagnostic_impression',
        ] as $field) {
            $value = $last->getAttribute($field);
            if (filled($value)) {
                $out[$field] = $value;
            }
        }

        foreach (['pa', 'fc', 'fr', 'temp', 'saturacion', 'peso', 'estatura', 'imc'] as $field) {
            $value = $last->getAttribute($field);
            if ($value !== null && $value !== '') {
                $out[$field] = $value;
            }
        }

        if (filled($last->telemedicine_priority_id)) {
            $out['telemedicine_priority_id'] = (int) $last->telemedicine_priority_id;
        }

        if (filled($last->telemedicine_service_list_drift_id)) {
            $out['telemedicine_service_list_id'] = (int) $last->telemedicine_service_list_drift_id;
        } elseif (filled($last->telemedicine_service_list_id)) {
            $out['telemedicine_service_list_id'] = (int) $last->telemedicine_service_list_id;
        }

        return $out;
    }
}
