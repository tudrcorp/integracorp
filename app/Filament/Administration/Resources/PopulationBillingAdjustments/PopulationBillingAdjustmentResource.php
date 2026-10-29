<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\PopulationBillingAdjustments;

use App\Filament\Administration\Resources\PopulationBillingAdjustments\Pages\ListPopulationBillingAdjustments;
use App\Filament\Administration\Resources\PopulationBillingAdjustments\Pages\ViewPopulationBillingAdjustment;
use App\Filament\Administration\Resources\PopulationBillingAdjustments\RelationManagers\AdjustmentLinesRelationManager;
use App\Filament\Administration\Resources\PopulationBillingAdjustments\Schemas\PopulationBillingAdjustmentInfolist;
use App\Filament\Administration\Resources\PopulationBillingAdjustments\Tables\PopulationBillingAdjustmentsTable;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\CorporatePopulationUpdate;
use App\Support\AffiliationCorporates\BillingAdjustments\PopulationBillingAdjuster;
use App\Support\AffiliationCorporates\BillingAdjustments\PopulationBillingTray;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;
use UnitEnum;

/**
 * Ajustes por población: donde Administración factura la diferencia cuando un grupo corporativo
 * suma o resta personas. Una fila por carga de población aplicada.
 *
 * Verla exige el permiso de menú; aplicar y revertir exigen además el permiso de acción propio.
 * No crea, edita ni borra cargas de población: eso sigue en Negocios.
 */
class PopulationBillingAdjustmentResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = CorporatePopulationUpdate::class;

    protected static ?string $slug = 'ajustes-por-poblacion';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'ADMINISTRACIÓN';

    protected static ?string $navigationLabel = 'Ajustes por población';

    protected static ?string $modelLabel = 'ajuste por población';

    protected static ?string $pluralModelLabel = 'ajustes por población';

    protected static ?int $navigationSort = 33;

    public static function getEloquentQuery(): Builder
    {
        return PopulationBillingTray::query();
    }

    public static function infolist(Schema $schema): Schema
    {
        return PopulationBillingAdjustmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PopulationBillingAdjustmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AdjustmentLinesRelationManager::class,
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            if (! PopulationBillingAdjuster::tablesReady()) {
                return null;
            }

            $pending = PopulationBillingTray::summary()['pending_count'];
        } catch (Throwable) {
            return null;
        }

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Cargas de población por ajustar';
    }

    public static function canView(Model $record): bool
    {
        return static::canAccess();
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
        return [
            'index' => ListPopulationBillingAdjustments::route('/'),
            'view' => ViewPopulationBillingAdjustment::route('/{record}'),
        ];
    }
}
