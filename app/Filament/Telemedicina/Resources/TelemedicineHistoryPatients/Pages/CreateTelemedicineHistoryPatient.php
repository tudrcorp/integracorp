<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\Pages;

use Illuminate\Support\Facades\URL;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;

class CreateTelemedicineHistoryPatient extends CreateRecord
{
    protected static string $resource = TelemedicineHistoryPatientResource::class;

    protected function getRedirectUrl(): string
    {
        // dd('test');
        // $patient = session()->get('patient');
        // return redirect()->route('filament.telemedicina.resources.telemedicine-consultation-patients.create', ['id' => $patient->id]);
        // return $this->getResource()::getUrl('index');

        return URL::route('filament.telemedicina.resources.telemedicine-consultation-patients.create', [
            'record' => $this->data['telemedicine_patient_id'],
        ]);
    }
}