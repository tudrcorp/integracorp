<?php

namespace App\Filament\Resources\TelemedicinePatients\Pages;

use App\Filament\Resources\TelemedicinePatients\TelemedicinePatientResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTelemedicinePatient extends ViewRecord
{
    protected static string $resource = TelemedicinePatientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
