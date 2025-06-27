<?php

namespace App\Filament\Resources\Takers\Pages;

use App\Filament\Resources\Takers\TakerResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTaker extends EditRecord
{
    protected static string $resource = TakerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
