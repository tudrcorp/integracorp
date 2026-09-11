<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationAccountsPayables;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Resources\OperationAccountsPayables\Pages\CreateOperationAccountsPayable;
use App\Filament\Operations\Resources\OperationAccountsPayables\Pages\EditOperationAccountsPayable;
use App\Filament\Operations\Resources\OperationAccountsPayables\Pages\ListOperationAccountsPayables;
use App\Filament\Operations\Resources\OperationAccountsPayables\Pages\ViewOperationAccountsPayable;
use App\Filament\Operations\Resources\OperationAccountsPayables\Schemas\OperationAccountsPayableForm;
use App\Filament\Operations\Resources\OperationAccountsPayables\Schemas\OperationAccountsPayableInfolist;
use App\Filament\Operations\Resources\OperationAccountsPayables\Tables\OperationAccountsPayablesTable;
use App\Models\OperationAccountsPayable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class OperationAccountsPayableResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = OperationAccountsPayable::class;

    protected static ?string $navigationLabel = 'Cuentas por pagar';

    protected static ?string $modelLabel = 'factura por pagar';

    protected static ?string $pluralModelLabel = 'cuentas por pagar';

    protected static ?string $recordTitleAttribute = 'invoice_number';

    protected static ?string $slug = 'cuentas-por-pagar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'COORDINACIÓN DE SERVICIOS';

    protected static ?int $navigationSort = 29;

    /**
     * Las cuentas por pagar se generan al cargar la factura en la orden de
     * servicio; no se crean a mano desde este panel.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return OperationAccountsPayableForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OperationAccountsPayableInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OperationAccountsPayablesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['businessUnit', 'supplier']);
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['invoice_number', 'invoice_control_number', 'supplier_name', 'supplier_rif', 'payment_reference'];
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOperationAccountsPayables::route('/'),
            'create' => CreateOperationAccountsPayable::route('/create'),
            'view' => ViewOperationAccountsPayable::route('/{record}'),
            'edit' => EditOperationAccountsPayable::route('/{record}/edit'),
        ];
    }
}
