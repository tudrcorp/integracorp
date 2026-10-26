<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PersonIdentityFindings;

use App\Enums\PersonIdentitySource;
use App\Filament\Business\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Filament\Business\Resources\Affiliations\AffiliationResource;
use App\Filament\Business\Resources\CompanyAssociates\CompanyAssociateResource;
use App\Filament\Business\Resources\PersonIdentityFindings\Pages\ListPersonIdentityFindings;
use App\Filament\Business\Resources\PersonIdentityFindings\Pages\ViewPersonIdentityFinding;
use App\Filament\Business\Resources\PersonIdentityFindings\Schemas\PersonIdentityFindingInfolist;
use App\Filament\Business\Resources\PersonIdentityFindings\Tables\PersonIdentityFindingsTable;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\PersonIdentityFinding;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Throwable;
use UnitEnum;

/**
 * Detector de personas duplicadas entre afiliados individuales, corporativos y Nuevos Negocios,
 * y de nombres cargados dos veces.
 *
 * Verlo y resolver casos exige el permiso de menú `personas-duplicadas` de Negocios.
 */
class PersonIdentityFindingResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = PersonIdentityFinding::class;

    protected static ?string $navigationLabel = 'Personas duplicadas';

    protected static ?string $modelLabel = 'caso de persona duplicada';

    protected static ?string $pluralModelLabel = 'personas duplicadas';

    protected static ?string $slug = 'personas-duplicadas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'AFILIACIONES';

    protected static ?int $navigationSort = 6;

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
            $open = PersonIdentityFinding::query()->where('status', PersonIdentityFinding::STATUS_OPEN)->count();
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
        return 'Casos abiertos';
    }

    /**
     * Ficha donde vive el registro de un lado del caso, o null si no aplica.
     *
     * @param  array<string, mixed>  $side
     */
    public static function recordUrl(array $side): ?string
    {
        $source = PersonIdentitySource::tryFrom((string) ($side['source'] ?? ''));

        try {
            return match ($source) {
                PersonIdentitySource::Individual => filled($side['parent_id'] ?? null) ? AffiliationResource::getUrl('view', ['record' => $side['parent_id']]) : null,
                PersonIdentitySource::Corporate => filled($side['parent_id'] ?? null) ? AffiliationCorporateResource::getUrl('view', ['record' => $side['parent_id']]) : null,
                PersonIdentitySource::Associate => filled($side['id'] ?? null) ? CompanyAssociateResource::getUrl('view', ['record' => $side['id']]) : null,
                null => null,
            };
        } catch (Throwable) {
            return null;
        }
    }

    public static function infolist(Schema $schema): Schema
    {
        return PersonIdentityFindingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PersonIdentityFindingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPersonIdentityFindings::route('/'),
            'view' => ViewPersonIdentityFinding::route('/{record}'),
        ];
    }
}
