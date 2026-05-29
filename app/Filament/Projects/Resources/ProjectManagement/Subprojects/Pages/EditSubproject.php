<?php

namespace App\Filament\Projects\Resources\ProjectManagement\Subprojects\Pages;

use App\Filament\Projects\Resources\ProjectManagement\Subprojects\SubprojectResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSubproject extends EditRecord
{
    protected static string $resource = SubprojectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
