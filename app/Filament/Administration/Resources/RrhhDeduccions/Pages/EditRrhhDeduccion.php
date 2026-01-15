<?php

namespace App\Filament\Administration\Resources\RrhhDeduccions\Pages;

use App\Filament\Administration\Resources\RrhhDeduccions\RrhhDeduccionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRrhhDeduccion extends EditRecord
{
    protected static string $resource = RrhhDeduccionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
