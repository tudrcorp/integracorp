<?php

namespace App\Filament\Operations\Resources\OperationInventoryProductCategories\Pages;

use App\Filament\Operations\Resources\OperationInventoryProductCategories\OperationInventoryProductCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOperationInventoryProductCategory extends EditRecord
{
    protected static string $resource = OperationInventoryProductCategoryResource::class;

    protected static ?string $title = 'Editar Categoría';

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->label('Ver'),
            DeleteAction::make()->label('Eliminar'),
        ];
    }
}
