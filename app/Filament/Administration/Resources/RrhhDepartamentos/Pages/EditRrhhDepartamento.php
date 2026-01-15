<?php

namespace App\Filament\Administration\Resources\RrhhDepartamentos\Pages;

use App\Filament\Administration\Resources\RrhhDepartamentos\RrhhDepartamentoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRrhhDepartamento extends EditRecord
{
    protected static string $resource = RrhhDepartamentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
