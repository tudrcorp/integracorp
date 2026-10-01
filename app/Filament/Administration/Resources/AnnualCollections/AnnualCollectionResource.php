<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\AnnualCollections;

use App\Filament\Administration\Resources\AnnualCollections\Pages\ListAnnualCollections;
use App\Filament\Administration\Resources\AnnualCollections\Tables\AnnualCollectionsTable;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\Collection;
use App\Support\Collections\CollectionReceivableReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Cobranza por mes: reporte de cuentas por cobrar.
 *
 * Sale de las cuotas reales (`collections`), no de `annual_collections`: una fila por
 * afiliación con su próxima cuota pendiente. Es de consulta: no crea, edita ni borra.
 */
class AnnualCollectionResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = Collection::class;

    /** El modelo es `Collection`: sin esto el slug chocaría con Gestión de Cobranza. */
    protected static ?string $slug = 'annual-collections';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'ADMINISTRACIÓN';

    protected static ?string $navigationLabel = 'Cobranza Por Mes';

    protected static ?string $modelLabel = 'cuenta por cobrar';

    protected static ?string $pluralModelLabel = 'cuentas por cobrar';

    public static function getEloquentQuery(): Builder
    {
        return CollectionReceivableReport::scopeNextPendingPerAffiliation(parent::getEloquentQuery());
    }

    public static function table(Table $table): Table
    {
        return AnnualCollectionsTable::configure($table);
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
            'index' => ListAnnualCollections::route('/'),
        ];
    }
}
