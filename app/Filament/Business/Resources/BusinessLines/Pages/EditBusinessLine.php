<?php

namespace App\Filament\Business\Resources\BusinessLines\Pages;

use App\Filament\Business\Resources\BusinessLines\BusinessLineResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditBusinessLine extends EditRecord
{
    protected static string $resource = BusinessLineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
