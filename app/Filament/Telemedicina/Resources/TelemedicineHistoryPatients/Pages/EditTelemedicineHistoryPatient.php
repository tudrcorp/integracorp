<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\Pages;

use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTelemedicineHistoryPatient extends EditRecord
{
    protected static string $resource = TelemedicineHistoryPatientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
