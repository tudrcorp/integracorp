<?php

namespace App\Filament\Operations\Resources\OperationInventories;

use App\Filament\Operations\Resources\OperationInventories\Pages\CreateOperationInventory;
use App\Filament\Operations\Resources\OperationInventories\Pages\EditOperationInventory;
use App\Filament\Operations\Resources\OperationInventories\Pages\ListOperationInventories;
use App\Filament\Operations\Resources\OperationInventories\Pages\ViewOperationInventory;
use App\Filament\Operations\Resources\OperationInventories\Schemas\OperationInventoryForm;
use App\Filament\Operations\Resources\OperationInventories\Schemas\OperationInventoryInfolist;
use App\Filament\Operations\Resources\OperationInventories\Tables\OperationInventoriesTable;
use App\Models\OperationInventory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class OperationInventoryResource extends Resource
{
    protected static ?string $model = OperationInventory::class;

    protected static ?string $navigationLabel = 'Inventario General';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static string|UnitEnum|null $navigationGroup = 'INVENTARIO DIAGNOMOVIL';

    public static function form(Schema $schema): Schema
    {
        return OperationInventoryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OperationInventoryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OperationInventoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOperationInventories::route('/'),
            'create' => CreateOperationInventory::route('/create'),
            'view' => ViewOperationInventory::route('/{record}'),
            'edit' => EditOperationInventory::route('/{record}/edit'),
        ];
    }
}
