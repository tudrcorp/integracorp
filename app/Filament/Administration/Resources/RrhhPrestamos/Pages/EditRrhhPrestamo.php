<?php

namespace App\Filament\Administration\Resources\RrhhPrestamos\Pages;

use App\Filament\Administration\Resources\RrhhPrestamos\RrhhPrestamoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRrhhPrestamo extends EditRecord
{
    protected static string $resource = RrhhPrestamoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
