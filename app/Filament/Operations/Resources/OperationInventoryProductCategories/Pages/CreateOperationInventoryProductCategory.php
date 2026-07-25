<?php

namespace App\Filament\Operations\Resources\OperationInventoryProductCategories\Pages;

use App\Filament\Operations\Resources\OperationInventoryProductCategories\OperationInventoryProductCategoryResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateOperationInventoryProductCategory extends CreateRecord
{
    protected static string $resource = OperationInventoryProductCategoryResource::class;

    protected static ?string $title = 'Crear Categoría';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::user()?->name;

        return $data;
    }
}
