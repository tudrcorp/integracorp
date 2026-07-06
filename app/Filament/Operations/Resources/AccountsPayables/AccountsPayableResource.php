<?php

namespace App\Filament\Operations\Resources\AccountsPayables;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Resources\AccountsPayables\Pages\ListAccountsPayables;
use App\Filament\Operations\Resources\AccountsPayables\Pages\ViewAccountsPayable;
use App\Filament\Operations\Resources\AccountsPayables\Schemas\AccountsPayableInfolist;
use App\Filament\Operations\Resources\AccountsPayables\Tables\AccountsPayablesTable;
use App\Models\OperationQuoteGenerator;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AccountsPayableResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = OperationQuoteGenerator::class;

    protected static ?string $navigationLabel = 'Cuentas por pagar';

    protected static ?string $modelLabel = 'cuenta por pagar';

    protected static ?string $pluralModelLabel = 'cuentas por pagar';

    protected static ?string $slug = 'accounts-payables';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'COORDINACIÓN DE SERVICIOS';

    protected static ?int $navigationSort = 30;

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
        return AccountsPayableInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AccountsPayablesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccountsPayables::route('/'),
            'view' => ViewAccountsPayable::route('/{record}'),
        ];
    }
}
