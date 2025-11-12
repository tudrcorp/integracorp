<?php

namespace App\Filament\Business\Resources\Users;

use UnitEnum;
use BackedEnum;
use App\Models\User;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use App\Filament\Business\Resources\Users\Pages\EditUser;
use App\Filament\Business\Resources\Users\Pages\ViewUser;
use App\Filament\Business\Resources\Users\Pages\ListUsers;
use App\Filament\Business\Resources\Users\Pages\CreateUser;
use App\Filament\Business\Resources\Users\Schemas\UserForm;
use App\Filament\Business\Resources\Users\Tables\UsersTable;
use App\Filament\Business\Resources\Users\Schemas\UserInfolist;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static string | UnitEnum | null $navigationGroup = 'CONFIGURACIÓN';

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        //Solo el Administrador General del Modulo de Business puede acceder a este recurso
        if (Auth::user()->is_business_admin) {
            return true;
        }
        return false;
    }
}