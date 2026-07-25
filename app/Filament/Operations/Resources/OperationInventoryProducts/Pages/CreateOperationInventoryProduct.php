<?php

namespace App\Filament\Operations\Resources\OperationInventoryProducts\Pages;

use App\Filament\Operations\Resources\OperationInventoryProducts\OperationInventoryProductResource;
use App\Services\OperationInventoryProductCodeGenerator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateOperationInventoryProduct extends CreateRecord
{
    protected static string $resource = OperationInventoryProductResource::class;

    protected static ?string $title = 'Crear Producto';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['code'] = app(OperationInventoryProductCodeGenerator::class)->next();
        $data['created_by'] = Auth::user()?->name;

        return $data;
    }
}
