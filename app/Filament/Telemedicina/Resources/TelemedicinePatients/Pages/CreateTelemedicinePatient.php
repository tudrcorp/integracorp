<?php

namespace App\Filament\Telemedicina\Resources\TelemedicinePatients\Pages;

use App\Filament\Telemedicina\Resources\TelemedicinePatients\TelemedicinePatientResource;
use App\Models\TelemedicineRepresentative;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateTelemedicinePatient extends CreateRecord
{
    protected static string $resource = TelemedicinePatientResource::class;

    protected static ?string $title = 'Formulario Registro de Pacientes';

    protected function getFormActions(): array
    {
        return [];
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

    protected function afterCreate(): void
    {
        try {

            $record = $this->getRecord();

            if ($record->age < 18) {
                $representante = TelemedicineRepresentative::create([
                    'telemedicine_patient_id' => $record->id,
                    'full_name' => $this->data['re_full_name'],
                    'email' => $this->data['re_email'],
                    'nro_identificacion' => $this->data['re_nro_identificacion'],
                    'phone' => $this->data['re_phone'],
                    'relationship' => $this->data['re_relationship'],
                ]);
            }

        } catch (\Throwable $th) {
            Notification::make()
                ->title('Error')
                ->body($th->getMessage())
                ->danger()
                ->send();
        }
    }
}
