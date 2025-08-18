<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Pages;

use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\TelemedicineConsultationPatientResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTelemedicineConsultationPatient extends CreateRecord
{
    protected static string $resource = TelemedicineConsultationPatientResource::class;
}
