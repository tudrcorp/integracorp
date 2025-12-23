<?php

namespace App\Filament\Operations\Resources\TelemedicinePatients\Pages;

use App\Filament\Operations\Resources\TelemedicinePatients\TelemedicinePatientResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTelemedicinePatient extends ViewRecord
{
    protected static string $resource = TelemedicinePatientResource::class;

    protected static ?string $title = 'Ficha del Paciente';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}