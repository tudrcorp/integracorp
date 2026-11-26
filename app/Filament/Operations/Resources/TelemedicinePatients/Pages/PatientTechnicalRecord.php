<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicinePatients\Pages;

use App\Filament\Shared\ClinicalRecord\ClinicalRecordPage;
use App\Support\Telemedicine\ClinicalRecord\ClinicalRecordAccess;
use Filament\Panel;

/**
 * Ficha técnica del paciente (uso interno): la ficha clínica más afiliación, cobertura y cupos.
 * Solo Operaciones, para analistas TDG y superadmin.
 */
class PatientTechnicalRecord extends PatientClinicalRecord
{
    public static function recordKind(): string
    {
        return ClinicalRecordAccess::KIND_TECHNICAL;
    }

    public static function getRoutePath(Panel $panel): string
    {
        return '/{record}/ficha-tecnica';
    }
}
