<?php

namespace App\Filament\Operations\Resources\Suppliers\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Forms\Components\Repeater\TableColumn;
use App\Filament\Operations\Resources\Suppliers\SupplierResource;

class ViewSupplier extends ViewRecord
{
    protected static string $resource = SupplierResource::class;

    protected static ?string $title = 'Ficha Técnica del Proveedor';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function getRelationManagers(): array
    {
        return [
            
        ];
    }
}