<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicinePatients\Pages;

use App\Filament\Operations\Resources\OperationCoordinationServices\OperationCoordinationServiceResource;
use App\Filament\Operations\Resources\TelemedicineConsultationPatients\TelemedicineConsultationPatientResource;
use App\Filament\Operations\Resources\TelemedicinePatients\TelemedicinePatientResource;
use App\Filament\Shared\ClinicalRecord\ClinicalRecordPage;
use App\Support\Telemedicine\ClinicalRecord\ClinicalRecordAccess;

/**
 * Ficha clínica en Operaciones: solo analistas TDG y superadmin (no proveedores, AMD ni ATENMEDI).
 */
class PatientClinicalRecord extends ClinicalRecordPage
{
    protected static string $resource = TelemedicinePatientResource::class;

    public static function panelKey(): string
    {
        return ClinicalRecordAccess::PANEL_OPERATIONS;
    }

    protected function eventUrl(array $event): ?string
    {
        if (! is_int($event['id'] ?? null)) {
            return null;
        }

        return match ($event['source']) {
            'consultation' => TelemedicineConsultationPatientResource::getUrl('view', ['record' => $event['id']]),
            'coordination' => OperationCoordinationServiceResource::canAccess() ? OperationCoordinationServiceResource::getUrl('view', ['record' => $event['id']]) : null,
            default => null,
        };
    }
}
