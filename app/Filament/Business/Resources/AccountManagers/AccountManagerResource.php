<?php

namespace App\Filament\Business\Resources\AccountManagers;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use App\Models\AccountManager;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use App\Filament\Business\Resources\AccountManagers\Pages\EditAccountManager;
use App\Filament\Business\Resources\AccountManagers\Pages\ListAccountManagers;
use App\Filament\Business\Resources\AccountManagers\Pages\CreateAccountManager;
use App\Filament\Business\Resources\AccountManagers\Schemas\AccountManagerForm;
use App\Filament\Business\Resources\AccountManagers\Tables\AccountManagersTable;

class AccountManagerResource extends Resource
{
    protected static ?string $model = AccountManager::class;

    protected static ?string $navigationLabel = 'Account Managers';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-globe-europe-africa';

    protected static string | UnitEnum | null $navigationGroup = 'CONFIGURACIÓN';

    public static function form(Schema $schema): Schema
    {
        return AccountManagerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AccountManagersTable::configure($table);
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
            'index' => ListAccountManagers::route('/'),
            'create' => CreateAccountManager::route('/create'),
            'edit' => EditAccountManager::route('/{record}/edit'),
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