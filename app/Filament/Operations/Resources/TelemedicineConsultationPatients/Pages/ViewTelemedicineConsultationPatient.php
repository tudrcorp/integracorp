<?php

namespace App\Filament\Operations\Resources\TelemedicineConsultationPatients\Pages;

use App\Filament\Operations\Resources\TelemedicineConsultationPatients\TelemedicineConsultationPatientResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTelemedicineConsultationPatient extends ViewRecord
{
    protected static string $resource = TelemedicineConsultationPatientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
