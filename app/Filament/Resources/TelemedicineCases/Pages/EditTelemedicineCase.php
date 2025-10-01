<?php

namespace App\Filament\Resources\TelemedicineCases\Pages;

use App\Filament\Resources\TelemedicineCases\TelemedicineCaseResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTelemedicineCase extends EditRecord
{
    protected static string $resource = TelemedicineCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
