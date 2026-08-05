<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\TelemedicinePatient;
use App\Support\Telemedicine\TelemedicineCaseIdentity;

class TelemedicinePatientObserver
{
    public function updated(TelemedicinePatient $patient): void
    {
        if (! $patient->wasChanged(['full_name', 'age', 'sex'])) {
            return;
        }

        TelemedicineCaseIdentity::syncCaseSnapshotsForPatient($patient);
    }
}
