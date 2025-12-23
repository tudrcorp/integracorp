<?php

namespace App\Filament\Operations\Resources\TelemedicineConsultationPatients\Pages;

use App\Filament\Operations\Resources\TelemedicineConsultationPatients\TelemedicineConsultationPatientResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTelemedicineConsultationPatient extends CreateRecord
{
    protected static string $resource = TelemedicineConsultationPatientResource::class;
}
