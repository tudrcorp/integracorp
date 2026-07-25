<?php

namespace App\Filament\Operations\Resources\OperationInventoryProducts\Pages;

use App\Filament\Operations\Resources\OperationInventoryProducts\OperationInventoryProductResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOperationInventoryProduct extends EditRecord
{
    protected static string $resource = OperationInventoryProductResource::class;

    protected static ?string $title = 'Editar Producto';

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->label('Ver'),
            DeleteAction::make()->label('Eliminar'),
        ];
    }
}
