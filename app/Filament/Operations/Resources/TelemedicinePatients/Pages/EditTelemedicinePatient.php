<?php

namespace App\Filament\Operations\Resources\TelemedicinePatients\Pages;

use App\Filament\Operations\Resources\TelemedicinePatients\TelemedicinePatientResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTelemedicinePatient extends EditRecord
{
    protected static string $resource = TelemedicinePatientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
