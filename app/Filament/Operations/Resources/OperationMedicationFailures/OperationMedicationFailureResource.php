<?php

namespace App\Filament\Operations\Resources\OperationMedicationFailures;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Resources\OperationMedicationFailures\Pages\ListOperationMedicationFailures;
use App\Filament\Operations\Resources\OperationMedicationFailures\Pages\ViewOperationMedicationFailure;
use App\Filament\Operations\Resources\OperationMedicationFailures\Schemas\OperationMedicationFailureInfolist;
use App\Filament\Operations\Resources\OperationMedicationFailures\Tables\OperationMedicationFailuresTable;
use App\Models\OperationMedicationFailure;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Fallas de medicamentos: lo que el inventario no cubrió y la empresa debe adquirir.
 * Se registran desde «Gestionar servicio»; aquí se consultan y se cierran.
 */
class OperationMedicationFailureResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = OperationMedicationFailure::class;

    protected static ?string $navigationLabel = 'Fallas de medicamentos';

    protected static ?string $modelLabel = 'falla de medicamento';

    protected static ?string $pluralModelLabel = 'fallas de medicamentos';

    protected static ?string $slug = 'medication-failures';

    protected static ?string $recordTitleAttribute = 'medicine_name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'INVENTARIO DIAGNOMOVIL';

    protected static ?int $navigationSort = 9;

    public static function getNavigationBadge(): ?string
    {
        $pending = once(fn (): int => OperationMedicationFailure::query()
            ->where('status', OperationMedicationFailure::STATUS_PENDING_PURCHASE)
            ->count());

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pendientes de compra';
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
        return OperationMedicationFailureInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OperationMedicationFailuresTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOperationMedicationFailures::route('/'),
            'view' => ViewOperationMedicationFailure::route('/{record}'),
        ];
    }
}
