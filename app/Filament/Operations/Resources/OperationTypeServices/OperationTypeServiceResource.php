<?php

namespace App\Filament\Operations\Resources\OperationTypeServices;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Resources\OperationTypeServices\Pages\CreateOperationTypeService;
use App\Filament\Operations\Resources\OperationTypeServices\Pages\EditOperationTypeService;
use App\Filament\Operations\Resources\OperationTypeServices\Pages\ListOperationTypeServices;
use App\Filament\Operations\Resources\OperationTypeServices\Pages\ViewOperationTypeService;
use App\Filament\Operations\Resources\OperationTypeServices\Schemas\OperationTypeServiceForm;
use App\Filament\Operations\Resources\OperationTypeServices\Schemas\OperationTypeServiceInfolist;
use App\Filament\Operations\Resources\OperationTypeServices\Tables\OperationTypeServicesTable;
use App\Models\OperationTypeService;
use App\Models\Permission;
use App\Models\UserPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class OperationTypeServiceResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = OperationTypeService::class;

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACION';

    protected static ?string $navigationLabel = 'Tipos de Servicios';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-view-columns';

    public static function form(Schema $schema): Schema
    {
        return OperationTypeServiceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OperationTypeServiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OperationTypeServicesTable::configure($table);
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
            'index' => ListOperationTypeServices::route('/'),
            'create' => CreateOperationTypeService::route('/create'),
            'view' => ViewOperationTypeService::route('/{record}'),
            'edit' => EditOperationTypeService::route('/{record}/edit'),
        ];
    }

    // public static function canAccess(): bool
    // {
    //     $module = 'OPERACIONES';
    //     $permission = Permission::where('module', $module)->where('slug', 'tipos-de-servicios')->first();

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
