<?php

namespace App\Filament\Administration\Resources\RrhhAsignacions\Pages;

use App\Filament\Administration\Resources\RrhhAsignacions\RrhhAsignacionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRrhhAsignacion extends EditRecord
{
    protected static string $resource = RrhhAsignacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
