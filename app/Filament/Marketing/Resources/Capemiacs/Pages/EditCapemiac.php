<?php

namespace App\Filament\Marketing\Resources\Capemiacs\Pages;

use App\Filament\Marketing\Resources\Capemiacs\CapemiacResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCapemiac extends EditRecord
{
    protected static string $resource = CapemiacResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
