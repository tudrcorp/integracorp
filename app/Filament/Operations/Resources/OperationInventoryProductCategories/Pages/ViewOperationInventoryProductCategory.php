<?php

namespace App\Filament\Operations\Resources\OperationInventoryProductCategories\Pages;

use App\Filament\Operations\Resources\OperationInventoryProductCategories\OperationInventoryProductCategoryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOperationInventoryProductCategory extends ViewRecord
{
    protected static string $resource = OperationInventoryProductCategoryResource::class;

    protected static ?string $title = 'Ver Categoría';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Editar'),
        ];
    }
}
