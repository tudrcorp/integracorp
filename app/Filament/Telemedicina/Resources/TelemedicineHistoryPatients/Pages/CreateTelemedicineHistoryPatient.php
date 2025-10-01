<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\Pages;

use App\Models\TelemedicineCase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;

class CreateTelemedicineHistoryPatient extends CreateRecord
{
    protected static string $resource = TelemedicineHistoryPatientResource::class;

    protected function getRedirectUrl(): string
    {
        try {
            
            session()->put('case', TelemedicineCase::where('telemedicine_patient_id', $this->data['telemedicine_patient_id'])->first());

            return URL::route('filament.telemedicina.resources.telemedicine-consultation-patients.create', [
                'record' => $this->data['telemedicine_patient_id'],
            ]);
            
        } catch (\Exception $e) {
            
            Notification::make()
                ->title('Error al obtener el estado del formulario.')
                ->danger()
                ->send();
                
            // Manejar la excepción aquí, por ejemplo, registrándola o mostrando un mensaje de error
            Log::error('Error al obtener el estado del formulario: ' . $e->getMessage());
            return $this->getResource()::getUrl('index'); // Redirigir a la página de índice en caso de error
            
        }
    }
}