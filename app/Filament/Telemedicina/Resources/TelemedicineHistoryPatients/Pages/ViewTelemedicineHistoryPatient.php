<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\Pages;

use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTelemedicineHistoryPatient extends ViewRecord
{
    protected static string $resource = TelemedicineHistoryPatientResource::class;

    protected static ?string $title = 'Información de Paciente';
    
    protected function getHeaderActions(): array
    {
        return [
            // EditAction::make(),
        ];
    }
}