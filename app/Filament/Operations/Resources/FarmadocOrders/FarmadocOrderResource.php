<?php

namespace App\Filament\Operations\Resources\FarmadocOrders;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Resources\FarmadocOrders\Pages\ListFarmadocOrders;
use App\Filament\Operations\Resources\FarmadocOrders\Pages\ViewFarmadocOrder;
use App\Filament\Operations\Resources\FarmadocOrders\Schemas\FarmadocOrderInfolist;
use App\Filament\Operations\Resources\FarmadocOrders\Tables\FarmadocOrdersTable;
use App\Models\FarmadocOrder;
use App\Support\Operations\MedicationInvoiceWizard;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Pedidos de medicamentos enviados a Farmadoc. Los ven todos los analistas de
 * Operaciones; el estatus se consulta en el API de Farmadoc.
 */
class FarmadocOrderResource extends Resource
{
    use AuthorizesDepartmentNavigation {
        canAccess as departmentCanAccess;
    }

    protected static ?string $model = FarmadocOrder::class;

    protected static ?string $navigationLabel = 'Pedidos a Farmadoc';

    protected static ?string $modelLabel = 'pedido a Farmadoc';

    protected static ?string $pluralModelLabel = 'pedidos a Farmadoc';

    protected static ?string $slug = 'farmadoc-orders';

    protected static ?string $recordTitleAttribute = 'order_number';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'INVENTARIO DIAGNOMOVIL';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if ($user === null || in_array('ATENMEDI', $user->departament ?? [], true)) {
            return false;
        }

        return static::departmentCanAccess()
            && MedicationInvoiceWizard::userCanSeeInventoryAvailability();
    }

    public static function getNavigationBadge(): ?string
    {
        $open = once(fn (): int => FarmadocOrder::query()->open()->count());

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pendientes o en proceso';
    }

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
        return FarmadocOrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FarmadocOrdersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFarmadocOrders::route('/'),
            'view' => ViewFarmadocOrder::route('/{record}'),
        ];
    }
}
