<?php

namespace App\Filament\Resources\TelemedicineDoctors\Pages;

use App\Filament\Resources\TelemedicineDoctors\TelemedicineDoctorResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTelemedicineDoctor extends EditRecord
{
    protected static string $resource = TelemedicineDoctorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
