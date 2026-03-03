<?php

namespace App\Filament\Operations\Resources\OperationInventoryMovements;

use App\Filament\Operations\Resources\OperationInventoryMovements\Pages\CreateOperationInventoryMovement;
use App\Filament\Operations\Resources\OperationInventoryMovements\Pages\EditOperationInventoryMovement;
use App\Filament\Operations\Resources\OperationInventoryMovements\Pages\ListOperationInventoryMovements;
use App\Filament\Operations\Resources\OperationInventoryMovements\Pages\ViewOperationInventoryMovement;
use App\Filament\Operations\Resources\OperationInventoryMovements\Schemas\OperationInventoryMovementForm;
use App\Filament\Operations\Resources\OperationInventoryMovements\Schemas\OperationInventoryMovementInfolist;
use App\Filament\Operations\Resources\OperationInventoryMovements\Tables\OperationInventoryMovementsTable;
use App\Models\OperationInventoryMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class OperationInventoryMovementResource extends Resource
{
    protected static ?string $model = OperationInventoryMovement::class;

    protected static ?string $navigationLabel = 'Movimientos de Inventario';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|UnitEnum|null $navigationGroup = 'INVENTARIO DIAGNOMOVIL';

    public static function form(Schema $schema): Schema
    {
        return OperationInventoryMovementForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OperationInventoryMovementInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OperationInventoryMovementsTable::configure($table);
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
            'index' => ListOperationInventoryMovements::route('/'),
            'create' => CreateOperationInventoryMovement::route('/create'),
            'view' => ViewOperationInventoryMovement::route('/{record}'),
            'edit' => EditOperationInventoryMovement::route('/{record}/edit'),
        ];
    }
}
