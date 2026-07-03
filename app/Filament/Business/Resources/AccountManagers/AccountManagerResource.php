<?php

namespace App\Filament\Business\Resources\AccountManagers;

use App\Filament\Business\Resources\AccountManagers\Pages\CreateAccountManager;
use App\Filament\Business\Resources\AccountManagers\Pages\EditAccountManager;
use App\Filament\Business\Resources\AccountManagers\Pages\ListAccountManagers;
use App\Filament\Business\Resources\AccountManagers\RelationManagers\AgenciesRelationManager;
use App\Filament\Business\Resources\AccountManagers\RelationManagers\AgentsRelationManager;
use App\Filament\Business\Resources\AccountManagers\Schemas\AccountManagerForm;
use App\Filament\Business\Resources\AccountManagers\Tables\AccountManagersTable;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\AccountManager;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class AccountManagerResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = AccountManager::class;

    protected static ?string $navigationLabel = 'Account Managers';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-globe-europe-africa';

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACIÓN';

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
            AgenciesRelationManager::class,
            AgentsRelationManager::class,
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
}
