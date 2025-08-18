<?php

namespace App\Filament\Resources\TelemedicineHistoryPatients\Pages;

use App\Filament\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTelemedicineHistoryPatient extends CreateRecord
{
    protected static string $resource = TelemedicineHistoryPatientResource::class;

    protected static ?string $title = 'Crear Historia Clínica';
}