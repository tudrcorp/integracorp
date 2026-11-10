<?php

namespace App\Filament\Operations\Resources\OperationMedicationInvoices;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Resources\OperationMedicationInvoices\Pages\ListOperationMedicationInvoices;
use App\Filament\Operations\Resources\OperationMedicationInvoices\Pages\ViewOperationMedicationInvoice;
use App\Filament\Operations\Resources\OperationMedicationInvoices\Schemas\OperationMedicationInvoiceInfolist;
use App\Filament\Operations\Resources\OperationMedicationInvoices\Tables\OperationMedicationInvoicesTable;
use App\Models\OperationMedicationInvoice;
use App\Support\Filament\Operations\OperationsSupplierScope;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Facturas a pacientes (medicamentos, laboratorios, estudios y especialistas no cubiertos).
 * Nacen en «Gestionar servicio»; aquí solo se
 * consultan, se registra el pago o se anulan (con permiso).
 */
class OperationMedicationInvoiceResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = OperationMedicationInvoice::class;

    protected static ?string $navigationLabel = 'Facturas a pacientes';

    protected static ?string $modelLabel = 'factura a paciente';

    protected static ?string $pluralModelLabel = 'facturas a pacientes';

    protected static ?string $slug = 'medication-invoices';

    protected static ?string $recordTitleAttribute = 'invoice_number';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'COORDINACIÓN DE SERVICIOS';

    protected static ?int $navigationSort = 26;

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
        return OperationMedicationInvoiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OperationMedicationInvoicesTable::configure($table);
    }

    /**
     * El usuario de un proveedor solo ve las facturas de las coordinaciones que le corresponden.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas(
                'operationCoordinationService',
                fn (Builder $coordinationQuery): Builder => OperationsSupplierScope::applyCoordinationListScope($coordinationQuery)
            );
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOperationMedicationInvoices::route('/'),
            'view' => ViewOperationMedicationInvoice::route('/{record}'),
        ];
    }
}
