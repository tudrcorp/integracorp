<?php

namespace App\Filament\Telemedicina\Resources\TelemedicinePatients\Pages;

use Filament\Notifications\Notification;
use App\Models\TelemedicineRepresentative;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Telemedicina\Resources\TelemedicinePatients\TelemedicinePatientResource;

class CreateTelemedicinePatient extends CreateRecord
{
    protected static string $resource = TelemedicinePatientResource::class;

    protected static ?string $title = 'Formulario Registro de Pacientes';

    protected function getFormActions(): array
    {
        return [];
    }

    protected function afterCreate(): void
    {
        try {

                $record = $this->getRecord();

                if($record->age < 18) {
                    $representante = TelemedicineRepresentative::create([
                        'telemedicine_patient_id'   => $record->id,
                        'full_name'                 => $this->data['re_full_name'],
                        'email'                     => $this->data['re_email'],
                        'nro_identificacion'        => $this->data['re_nro_identificacion'],
                        'phone'                     => $this->data['re_phone'],
                        'relationship'              => $this->data['re_relationship'],
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