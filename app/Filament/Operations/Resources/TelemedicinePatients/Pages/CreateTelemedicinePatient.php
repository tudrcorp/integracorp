<?php

namespace App\Filament\Operations\Resources\TelemedicinePatients\Pages;

use App\Filament\Operations\Resources\TelemedicinePatients\TelemedicinePatientResource;
use App\Models\TelemedicinePatient;
use App\Support\Filament\Operations\OperationsSupplierScope;
use Filament\Notifications\Notification;
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
        return TelemedicinePatientResource::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        $name = trim((string) ($this->getRecord()?->full_name ?? ''));

        return Notification::make()
            ->success()
            ->icon('heroicon-o-check-circle')
            ->title('Paciente registrado')
            ->body($name !== ''
                ? 'Se registró a '.$name.'. Ya aparece en el listado de pacientes.'
                : 'El paciente se registró y ya aparece en el listado.');
    }
}
