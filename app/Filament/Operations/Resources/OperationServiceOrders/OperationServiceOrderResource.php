<?php

namespace App\Filament\Operations\Resources\OperationServiceOrders;

use App\Filament\Operations\Resources\OperationServiceOrders\Pages\CreateOperationServiceOrder;
use App\Filament\Operations\Resources\OperationServiceOrders\Pages\EditOperationServiceOrder;
use App\Filament\Operations\Resources\OperationServiceOrders\Pages\ListOperationServiceOrders;
use App\Filament\Operations\Resources\OperationServiceOrders\Pages\ViewOperationServiceOrder;
use App\Filament\Operations\Resources\OperationServiceOrders\Schemas\OperationServiceOrderForm;
use App\Filament\Operations\Resources\OperationServiceOrders\Schemas\OperationServiceOrderInfolist;
use App\Filament\Operations\Resources\OperationServiceOrders\Tables\OperationServiceOrdersTable;
use App\Models\OperationServiceOrder;
use App\Models\Permission;
use App\Models\UserPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class OperationServiceOrderResource extends Resource
{
    protected static ?string $model = OperationServiceOrder::class;

    protected static ?string $navigationLabel = 'Ordenes de Servicios';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|UnitEnum|null $navigationGroup = 'COORDINACIÓN DE SERVICIOS';

    public static function form(Schema $schema): Schema
    {
        return OperationServiceOrderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OperationServiceOrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OperationServiceOrdersTable::configure($table);
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
            'index' => ListOperationServiceOrders::route('/'),
            'create' => CreateOperationServiceOrder::route('/create'),
            'view' => ViewOperationServiceOrder::route('/{record}'),
            'edit' => EditOperationServiceOrder::route('/{record}/edit'),
        ];
    }

    // public static function canAccess(): bool
    // {
    //     $module = 'OPERACIONES';
    //     $permission = Permission::where('module', $module)->where('slug', 'ordenes-de-servicio')->first();

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
