<?php

namespace App\Filament\Operations\Resources\OperationInventoryEntries;

use App\Filament\Operations\Resources\OperationInventoryEntries\Pages\CreateOperationInventoryEntry;
use App\Filament\Operations\Resources\OperationInventoryEntries\Pages\EditOperationInventoryEntry;
use App\Filament\Operations\Resources\OperationInventoryEntries\Pages\ListOperationInventoryEntries;
use App\Filament\Operations\Resources\OperationInventoryEntries\Pages\ViewOperationInventoryEntry;
use App\Filament\Operations\Resources\OperationInventoryEntries\Schemas\OperationInventoryEntryForm;
use App\Filament\Operations\Resources\OperationInventoryEntries\Schemas\OperationInventoryEntryInfolist;
use App\Filament\Operations\Resources\OperationInventoryEntries\Tables\OperationInventoryEntriesTable;
use App\Models\OperationInventoryEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class OperationInventoryEntryResource extends Resource
{
    protected static ?string $model = OperationInventoryEntry::class;

    protected static ?string $navigationLabel = 'Entradas de Inventario';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|UnitEnum|null $navigationGroup = 'INVENTARIO DIAGNOMOVIL';

    public static function form(Schema $schema): Schema
    {
        return OperationInventoryEntryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OperationInventoryEntryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OperationInventoryEntriesTable::configure($table);
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
            'index' => ListOperationInventoryEntries::route('/'),
            'create' => CreateOperationInventoryEntry::route('/create'),
            'view' => ViewOperationInventoryEntry::route('/{record}'),
            'edit' => EditOperationInventoryEntry::route('/{record}/edit'),
        ];
    }
}
