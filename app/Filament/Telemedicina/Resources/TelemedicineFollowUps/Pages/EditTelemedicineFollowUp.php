<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineFollowUps\Pages;

use App\Filament\Telemedicina\Resources\TelemedicineFollowUps\TelemedicineFollowUpResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTelemedicineFollowUp extends EditRecord
{
    protected static string $resource = TelemedicineFollowUpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
