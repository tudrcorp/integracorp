<?php

namespace App\Filament\Operations\Resources\OperationOnCallUsers;

use App\Filament\Operations\Resources\OperationOnCallUsers\Pages\CreateOperationOnCallUser;
use App\Filament\Operations\Resources\OperationOnCallUsers\Pages\EditOperationOnCallUser;
use App\Filament\Operations\Resources\OperationOnCallUsers\Pages\ListOperationOnCallUsers;
use App\Filament\Operations\Resources\OperationOnCallUsers\Pages\ViewOperationOnCallUser;
use App\Filament\Operations\Resources\OperationOnCallUsers\Schemas\OperationOnCallUserForm;
use App\Filament\Operations\Resources\OperationOnCallUsers\Schemas\OperationOnCallUserInfolist;
use App\Filament\Operations\Resources\OperationOnCallUsers\Tables\OperationOnCallUsersTable;
use App\Models\OperationOnCallUser;
use App\Models\Permission;
use App\Models\UserPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class OperationOnCallUserResource extends Resource
{
    protected static ?string $model = OperationOnCallUser::class;

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACION';

    protected static ?string $navigationLabel = 'Roles de Guardia';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    public static function form(Schema $schema): Schema
    {
        return OperationOnCallUserForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OperationOnCallUserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OperationOnCallUsersTable::configure($table);
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
            'index' => ListOperationOnCallUsers::route('/'),
            'create' => CreateOperationOnCallUser::route('/create'),
            'view' => ViewOperationOnCallUser::route('/{record}'),
            'edit' => EditOperationOnCallUser::route('/{record}/edit'),
        ];
    }

    // public static function canAccess(): bool
    // {
    //     $module = 'OPERACIONES';
    //     $permission = Permission::where('module', $module)->where('slug', 'roles-de-guardia')->first();

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
