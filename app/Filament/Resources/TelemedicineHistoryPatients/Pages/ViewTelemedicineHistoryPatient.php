<?php

namespace App\Filament\Resources\TelemedicineHistoryPatients\Pages;

use App\Filament\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTelemedicineHistoryPatient extends ViewRecord
{
    protected static string $resource = TelemedicineHistoryPatientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
