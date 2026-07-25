<?php

namespace App\Filament\Operations\Resources\TelemedicinePatients\Pages;

use App\Filament\Operations\Resources\TelemedicinePatients\TelemedicinePatientResource;
use App\Models\TelemedicinePatient;
use App\Support\Filament\Operations\OperationsSupplierScope;
use Filament\Resources\Pages\CreateRecord;

class CreateTelemedicinePatient extends CreateRecord
{
    protected static string $resource = TelemedicinePatientResource::class;

    protected static ?string $title = 'Formulario de Creación de Pacientes';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $supplierId = OperationsSupplierScope::currentSupplierId();

        if ($supplierId !== null) {
            $data['supplier_id'] = $supplierId;
        }

        $data['patient_portal_password'] ??= TelemedicinePatient::DEFAULT_PATIENT_PORTAL_PASSWORD;
        $data['patient_portal_authorized'] ??= true;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return TelemedicinePatientResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
