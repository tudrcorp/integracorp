<?php

namespace App\Filament\Operations\Resources\TelemedicineConsultationPatients\Pages;

use App\Filament\Operations\Resources\TelemedicineConsultationPatients\TelemedicineConsultationPatientResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTelemedicineConsultationPatient extends EditRecord
{
    protected static string $resource = TelemedicineConsultationPatientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
