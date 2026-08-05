<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Http\Controllers\UtilsController;
use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;

class TelemedicineCaseFactory
{
    /**
     * Crea un caso con identidad forzada desde el paciente.
     * Los overrides pueden cambiar doctor, dirección/teléfono de atención, motivo, etc.
     * La identidad (id, nombre, edad, sexo) siempre sale del paciente.
     *
     * @param  array<string, mixed>  $overrides
     */
    public static function createForPatient(TelemedicinePatient $patient, array $overrides = []): TelemedicineCase
    {
        $identity = TelemedicineCaseIdentity::snapshotFromPatient($patient);

        $attributes = array_merge(
            [
                'code' => UtilsController::generateCaseCode(),
                'status' => 'ASIGNADO',
                'patient_phone' => $identity['patient_phone'],
                'patient_address' => $identity['patient_address'],
                'patient_country_id' => $identity['patient_country_id'],
                'patient_state_id' => $identity['patient_state_id'],
                'patient_city_id' => $identity['patient_city_id'],
            ],
            $overrides,
            [
                'telemedicine_patient_id' => $identity['telemedicine_patient_id'],
                'patient_name' => $identity['patient_name'],
                'patient_age' => $identity['patient_age'],
                'patient_sex' => $identity['patient_sex'],
            ],
        );

        return TelemedicineCase::create($attributes);
    }
}
