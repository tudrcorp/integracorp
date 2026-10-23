<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\DroppedAffiliationCollections;

use App\Filament\Administration\Resources\DroppedAffiliationCollections\Pages\ListDroppedAffiliationCollections;
use App\Filament\Administration\Resources\DroppedAffiliationCollections\Tables\DroppedAffiliationCollectionsTable;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\Collection;
use App\Support\Collections\DroppedAffiliationCollections;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;
use UnitEnum;

/**
 * Cuotas de afiliaciones dadas de baja: donde Administración decide, después de estudiar el
 * reporte, si cada cuota pendiente se anula o se mantiene como deuda, y revierte si hace falta.
 *
 * Verla exige el permiso de menú; anular, mantener y revertir exigen además el permiso de
 * acción propio. No crea, edita ni borra cuotas.
 */
class DroppedAffiliationCollectionResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = Collection::class;

    /** El modelo es `Collection`: sin esto el slug chocaría con Gestión de Cobranza. */
    protected static ?string $slug = 'cuotas-afiliaciones-dadas-de-baja';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserMinus;

    protected static string|UnitEnum|null $navigationGroup = 'ADMINISTRACIÓN';

    protected static ?string $navigationLabel = 'Cuotas de afiliaciones dadas de baja';

    protected static ?string $modelLabel = 'cuota de afiliación dada de baja';

    protected static ?string $pluralModelLabel = 'cuotas de afiliaciones dadas de baja';

    protected static ?int $navigationSort = 32;

    public static function getEloquentQuery(): Builder
    {
        return DroppedAffiliationCollections::reviewQuery();
    }

    public static function table(Table $table): Table
    {
        return DroppedAffiliationCollectionsTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            $undecided = DroppedAffiliationCollections::applyReviewFilter(
                DroppedAffiliationCollections::reviewQuery(),
                DroppedAffiliationCollections::REVIEW_UNDECIDED,
            )->count();
        } catch (Throwable) {
            return null;
        }

        return $undecided > 0 ? (string) $undecided : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Cuotas por decidir';
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
            'index' => ListDroppedAffiliationCollections::route('/'),
        ];
    }
}
