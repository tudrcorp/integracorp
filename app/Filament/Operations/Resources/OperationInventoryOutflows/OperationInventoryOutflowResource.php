<?php

namespace App\Filament\Operations\Resources\OperationInventoryOutflows;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Resources\OperationInventoryOutflows\Pages\CreateOperationInventoryOutflow;
use App\Filament\Operations\Resources\OperationInventoryOutflows\Pages\EditOperationInventoryOutflow;
use App\Filament\Operations\Resources\OperationInventoryOutflows\Pages\ListOperationInventoryOutflows;
use App\Filament\Operations\Resources\OperationInventoryOutflows\Pages\ViewOperationInventoryOutflow;
use App\Filament\Operations\Resources\OperationInventoryOutflows\Schemas\OperationInventoryOutflowForm;
use App\Filament\Operations\Resources\OperationInventoryOutflows\Schemas\OperationInventoryOutflowInfolist;
use App\Filament\Operations\Resources\OperationInventoryOutflows\Tables\OperationInventoryOutflowsTable;
use App\Models\OperationInventoryOutflow;
use App\Models\Permission;
use App\Models\UserPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class OperationInventoryOutflowResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = OperationInventoryOutflow::class;

    protected static ?string $navigationLabel = 'Salidas de Inventario';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-left-start-on-rectangle';

    protected static string|UnitEnum|null $navigationGroup = 'INVENTARIO DIAGNOMOVIL';

    public static function form(Schema $schema): Schema
    {
        return OperationInventoryOutflowForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OperationInventoryOutflowInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OperationInventoryOutflowsTable::configure($table);
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
            'index' => ListOperationInventoryOutflows::route('/'),
            'create' => CreateOperationInventoryOutflow::route('/create'),
            'view' => ViewOperationInventoryOutflow::route('/{record}'),
            'edit' => EditOperationInventoryOutflow::route('/{record}/edit'),
        ];
    }

    // public static function canAccess(): bool
    // {
    //     $module = 'OPERACIONES';
    //     $permission = Permission::where('module', $module)->where('slug', 'salidas-de-inventario')->first();

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
