<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;

final class ConsultationEditSession
{
    public static function storeForEdit(TelemedicineCase $case, TelemedicinePatient $patient, string $consultationStatus): void
    {
        session()->forget('case');
        session()->forget('patient');
        session(['case' => $case, 'patient' => $patient]);
        session()->forget('action');
        session()->forget('status');
        session()->put('action', 'edit');
        session()->put('status', $consultationStatus);
    }
}
