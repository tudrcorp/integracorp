<?php

namespace App\Filament\Resources\TelemedicinePatients\Pages;

use App\Filament\Resources\TelemedicinePatients\TelemedicinePatientResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTelemedicinePatient extends CreateRecord
{
    protected static string $resource = TelemedicinePatientResource::class;

    protected static ?string $title = 'Crear Paciente';
}