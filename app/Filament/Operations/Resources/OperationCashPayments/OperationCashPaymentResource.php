<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationCashPayments;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Resources\OperationCashPayments\Pages\ListOperationCashPayments;
use App\Filament\Operations\Resources\OperationCashPayments\Tables\OperationCashPaymentsTable;
use App\Models\OperationCashPayment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Pagos de contado de facturas de proveedores: se registran al cargar la factura de una orden
 * de servicio y se reversan aquí (permiso `reversar-pagos-de-contado`, razón obligatoria).
 * Solo lectura: un pago nunca se edita ni se borra.
 */
class OperationCashPaymentResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = OperationCashPayment::class;

    protected static ?string $slug = 'pagos-de-contado';

    protected static ?string $navigationLabel = 'Pagos de contado';

    protected static ?string $modelLabel = 'pago de contado';

    protected static ?string $pluralModelLabel = 'pagos de contado';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'COORDINACIÓN DE SERVICIOS';

    protected static ?int $navigationSort = 30;

    public static function table(Table $table): Table
    {
        return OperationCashPaymentsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListOperationCashPayments::route('/')];
    }
}
