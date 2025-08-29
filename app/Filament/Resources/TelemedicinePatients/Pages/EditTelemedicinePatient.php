<?php

namespace App\Filament\Resources\TelemedicinePatients\Pages;

use App\Filament\Resources\TelemedicinePatients\TelemedicinePatientResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTelemedicinePatient extends EditRecord
{
    protected static string $resource = TelemedicinePatientResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}