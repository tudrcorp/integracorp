<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\TelemedicinePatient;
use App\Support\Telemedicine\TelemedicineCaseIdentity;
use App\Support\Telemedicine\TelemedicinePatientIdentity;
use Illuminate\Support\Facades\Log;

class TelemedicinePatientObserver
{
    public function saving(TelemedicinePatient $patient): void
    {
        if (! $patient->isDirty('nro_identificacion')) {
            return;
        }

        $normalized = TelemedicinePatientIdentity::normalizeDocument($patient->nro_identificacion);
        $patient->nro_identificacion = $normalized !== '' ? $normalized : $patient->nro_identificacion;

        TelemedicinePatientIdentity::assertDocumentIsAvailable(
            $patient->nro_identificacion,
            $patient->exists ? (int) $patient->id : null,
        );

        if ($patient->exists) {
            Log::warning('TelemedicinePatientObserver: cambio de cédula en paciente de telemedicina', [
                'telemedicine_patient_id' => $patient->id,
                'previous_nro_identificacion' => $patient->getOriginal('nro_identificacion'),
                'new_nro_identificacion' => $patient->nro_identificacion,
                'full_name' => $patient->full_name,
            ]);
        }
    }

    public function updated(TelemedicinePatient $patient): void
    {
        if (! $patient->wasChanged(['full_name', 'age', 'sex', 'nro_identificacion'])) {
            return;
        }

        TelemedicineCaseIdentity::syncCaseSnapshotsForPatient($patient);
    }
}
