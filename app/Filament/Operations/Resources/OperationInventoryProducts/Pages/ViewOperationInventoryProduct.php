<?php

namespace App\Filament\Operations\Resources\OperationInventoryProducts\Pages;

use App\Filament\Operations\Resources\OperationInventoryProducts\Actions\LoadProductExistenceAction;
use App\Filament\Operations\Resources\OperationInventoryProducts\OperationInventoryProductResource;
use App\Models\OperationInventoryProduct;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewOperationInventoryProduct extends ViewRecord
{
    protected static string $resource = OperationInventoryProductResource::class;

    protected static ?string $title = 'Ver Producto';

    protected function resolveRecord(int|string $key): Model
    {
        /** @var OperationInventoryProduct $record */
        $record = parent::resolveRecord($key);

        $record->load([
            'category',
            'stocks.ubication',
        ]);

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            LoadProductExistenceAction::make(),
            EditAction::make()->label('Editar'),
        ];
    }
}
