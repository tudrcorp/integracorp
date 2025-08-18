<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineDoctors\Pages;

use App\Filament\Telemedicina\Resources\TelemedicineDoctors\TelemedicineDoctorResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTelemedicineDoctor extends ViewRecord
{
    protected static string $resource = TelemedicineDoctorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
