<?php

namespace App\Filament\Telemedicina\Resources\TelemedicinePatients\Pages;

use App\Filament\Telemedicina\Resources\TelemedicinePatients\TelemedicinePatientResource;
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
