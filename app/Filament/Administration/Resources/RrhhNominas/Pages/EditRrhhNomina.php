<?php

namespace App\Filament\Administration\Resources\RrhhNominas\Pages;

use App\Filament\Administration\Resources\RrhhNominas\RrhhNominaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRrhhNomina extends EditRecord
{
    protected static string $resource = RrhhNominaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
