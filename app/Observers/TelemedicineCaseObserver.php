<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;
use App\Support\Telemedicine\TelemedicineCaseIdentity;
use InvalidArgumentException;

class TelemedicineCaseObserver
{
    public function saving(TelemedicineCase $case): void
    {
        if (! filled($case->telemedicine_patient_id)) {
            throw new InvalidArgumentException('El caso de telemedicina requiere telemedicine_patient_id.');
        }

        $patient = $case->relationLoaded('telemedicinePatient')
            ? $case->telemedicinePatient
            : TelemedicinePatient::query()->find($case->telemedicine_patient_id);

        if ($patient === null) {
            throw new InvalidArgumentException('El telemedicine_patient_id del caso no existe.');
        }

        $canonicalName = trim((string) $patient->full_name);
        $incomingName = $case->patient_name;

        if (filled($incomingName) && ! TelemedicineCaseIdentity::namesMatch((string) $incomingName, $canonicalName)) {
            logger()->warning('TelemedicineCaseObserver: patient_name divergente corregido', [
                'telemedicine_case_id' => $case->id,
                'telemedicine_patient_id' => $patient->id,
                'incoming_patient_name' => $incomingName,
                'canonical_patient_name' => $canonicalName,
            ]);
        }

        $case->telemedicine_patient_id = (int) $patient->id;
        $case->patient_name = $canonicalName;

        if (! filled($case->patient_age)) {
            $case->patient_age = $patient->age;
        }

        if (! filled($case->patient_sex)) {
            $case->patient_sex = $patient->sex;
        }
    }
}
