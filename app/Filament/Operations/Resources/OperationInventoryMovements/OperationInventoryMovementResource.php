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
use App\Models\Permission;
use App\Models\UserPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
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

    // public static function canAccess(): bool
    // {
    //     $module = 'OPERACIONES';
    //     $permission = Permission::where('module', $module)->where('slug', 'movimientos-de-inventario')->first();

    //     // si es superadmin, retornar true
    //     if (in_array('SUPERADMIN', Auth::user()->departament)) {
    //         return true;
    //     }

    //     if (in_array($module, Auth::user()->departament)) {
    //         if (UserPermission::where('user_id', Auth::user()->id)->where('permission_id', $permission->id)->exists()) {
    //             return true;
    //         }
    //     }

    //     return false;
    // }
}
