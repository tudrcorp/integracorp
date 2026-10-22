<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\AffiliationDateInconsistencies;

use App\Filament\Administration\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Filament\Administration\Resources\AffiliationDateInconsistencies\Pages\ListAffiliationDateInconsistencies;
use App\Filament\Administration\Resources\AffiliationDateInconsistencies\Pages\ViewAffiliationDateInconsistency;
use App\Filament\Administration\Resources\AffiliationDateInconsistencies\Schemas\AffiliationDateInconsistencyInfolist;
use App\Filament\Administration\Resources\AffiliationDateInconsistencies\Tables\AffiliationDateInconsistenciesTable;
use App\Filament\Administration\Resources\Affiliations\AffiliationResource;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\AffiliationDateInconsistency;
use App\Support\Affiliations\DateCorrection\DateCorrectionSubject;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Throwable;
use UnitEnum;

/**
 * Detector de inconsistencias de fechas en afiliaciones individuales y corporativas.
 *
 * Verlo exige el permiso de menú de Administración. Reparar, revertir y marcar como
 * «no es una inconsistencia» exigen además el permiso de acción propio.
 */
class AffiliationDateInconsistencyResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = AffiliationDateInconsistency::class;

    protected static ?string $navigationLabel = 'Inconsistencias de fechas';

    protected static ?string $modelLabel = 'inconsistencia de fechas';

    protected static ?string $pluralModelLabel = 'inconsistencias de fechas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'ADMINISTRACIÓN';

    protected static ?int $navigationSort = 31;

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

    public static function getNavigationBadge(): ?string
    {
        try {
            $open = AffiliationDateInconsistency::query()->where('status', AffiliationDateInconsistency::STATUS_OPEN)->count();
        } catch (Throwable) {
            return null;
        }

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Inconsistencias abiertas';
    }

    public static function affiliationUrl(AffiliationDateInconsistency $record): string
    {
        return $record->affiliation_type === DateCorrectionSubject::TYPE_CORPORATE
            ? AffiliationCorporateResource::getUrl('view', ['record' => $record->affiliation_id])
            : AffiliationResource::getUrl('view', ['record' => $record->affiliation_id]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AffiliationDateInconsistencyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliationDateInconsistenciesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliationDateInconsistencies::route('/'),
            'view' => ViewAffiliationDateInconsistency::route('/{record}'),
        ];
    }
}
