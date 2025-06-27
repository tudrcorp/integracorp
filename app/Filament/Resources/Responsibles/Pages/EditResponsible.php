<?php

namespace App\Filament\Resources\Responsibles\Pages;

use App\Filament\Resources\Responsibles\ResponsibleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditResponsible extends EditRecord
{
    protected static string $resource = ResponsibleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
