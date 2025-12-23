<?php

namespace App\Filament\Operations\Resources\TelemedicinePatients\Pages;

use App\Filament\Operations\Resources\TelemedicinePatients\TelemedicinePatientResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTelemedicinePatient extends CreateRecord
{
    protected static string $resource = TelemedicinePatientResource::class;

    protected static ?string $title = 'Formulario de Creación de Pacientes';
}