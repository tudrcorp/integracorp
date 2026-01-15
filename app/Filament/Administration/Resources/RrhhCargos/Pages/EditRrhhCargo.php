<?php

namespace App\Filament\Administration\Resources\RrhhCargos\Pages;

use App\Filament\Administration\Resources\RrhhCargos\RrhhCargoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRrhhCargo extends EditRecord
{
    protected static string $resource = RrhhCargoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
