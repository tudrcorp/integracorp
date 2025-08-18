<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\Pages;

use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTelemedicineHistoryPatient extends CreateRecord
{
    protected static string $resource = TelemedicineHistoryPatientResource::class;
}
