<?php

namespace App\Filament\Operations\Resources\AccountsReceivables;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Resources\AccountsReceivables\Pages\ListAccountsReceivables;
use App\Filament\Operations\Resources\AccountsReceivables\Pages\ViewAccountsReceivable;
use App\Filament\Operations\Resources\AccountsReceivables\Schemas\AccountsReceivableInfolist;
use App\Filament\Operations\Resources\AccountsReceivables\Tables\AccountsReceivablesTable;
use App\Models\OperationAccountsReceivable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AccountsReceivableResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = OperationAccountsReceivable::class;

    protected static ?string $navigationLabel = 'Cuentas por cobrar';

    protected static ?string $modelLabel = 'cuenta por cobrar';

    protected static ?string $pluralModelLabel = 'cuentas por cobrar';

    protected static ?string $slug = 'accounts-receivables';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'COORDINACIÓN DE SERVICIOS';

    protected static ?int $navigationSort = 25;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return AccountsReceivableInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AccountsReceivablesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccountsReceivables::route('/'),
            'view' => ViewAccountsReceivable::route('/{record}'),
        ];
    }
}
