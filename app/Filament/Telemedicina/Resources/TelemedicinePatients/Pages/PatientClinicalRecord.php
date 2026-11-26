<?php

declare(strict_types=1);

namespace App\Filament\Telemedicina\Resources\TelemedicinePatients\Pages;

use App\Filament\Shared\ClinicalRecord\ClinicalRecordPage;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\TelemedicineConsultationPatientResource;
use App\Filament\Telemedicina\Resources\TelemedicinePatients\TelemedicinePatientResource;
use App\Support\Telemedicine\ClinicalRecord\ClinicalRecordAccess;

/**
 * Ficha clínica en Telemedicina: la ve el médico (o su equipo) que atiende o atendió al paciente.
 */
class PatientClinicalRecord extends ClinicalRecordPage
{
    protected static string $resource = TelemedicinePatientResource::class;

    public static function panelKey(): string
    {
        return ClinicalRecordAccess::PANEL_TELEMEDICINE;
    }

    protected function eventUrl(array $event): ?string
    {
        return $event['source'] === 'consultation' && is_int($event['id'] ?? null)
            ? TelemedicineConsultationPatientResource::getUrl('view', ['record' => $event['id']])
            : null;
    }
}
