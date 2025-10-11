<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\Pages;

use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTelemedicineHistoryPatient extends EditRecord
{
    protected static string $resource = TelemedicineHistoryPatientResource::class;

    protected static ?string $title = 'Edición de historia clínica';

    protected function getRedirectUrl(): ?string
    {
        //... Le digo a la varianle de sesion que maneja el formulario que lo muestre
        session()->put('redCode', false);
        
        $patient = session()->get('patient'); //$
        return route('filament.telemedicina.resources.telemedicine-consultation-patients.create', ['id' => $patient->id]);
    }

    
}