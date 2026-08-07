<?php

namespace App\Filament\Business\Resources\Affiliations;

use App\Filament\Business\Resources\Affiliations\Pages\CreateAffiliation;
use App\Filament\Business\Resources\Affiliations\Pages\EditAffiliation;
use App\Filament\Business\Resources\Affiliations\Pages\ListAffiliations;
use App\Filament\Business\Resources\Affiliations\Pages\ViewAffiliation;
use App\Filament\Business\Resources\Affiliations\RelationManagers\AffiliatesRelationManager;
use App\Filament\Business\Resources\Affiliations\RelationManagers\PaidMembershipsRelationManager;
use App\Filament\Business\Resources\Affiliations\Schemas\AffiliationForm;
use App\Filament\Business\Resources\Affiliations\Schemas\AffiliationInfolist;
use App\Filament\Business\Resources\Affiliations\Tables\AffiliationsTable;
use App\Filament\Business\Resources\Concerns\ConfiguresBusinessGlobalSearch;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\Affiliation;
use App\Support\Filament\BusinessGlobalSearch;
use BackedEnum;
use Carbon\Carbon;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

class AffiliationResource extends Resource
{
    use AuthorizesDepartmentNavigation;
    use ConfiguresBusinessGlobalSearch;

    protected static ?string $model = Affiliation::class;

    protected static ?string $navigationLabel = 'Individuales';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user';

    protected static string|UnitEnum|null $navigationGroup = 'AFILIACIONES';

    protected static ?string $recordTitleAttribute = 'code';

    protected static int $globalSearchResultsLimit = 8;

    protected static ?int $globalSearchSort = 30;

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchSelectColumns(): array
    {
        return [
            'id',
            'code',
            'full_name_ti',
            'nro_identificacion_ti',
            'full_name_payer',
            'nro_identificacion_payer',
            'email_ti',
            'status',
            'code_agency',
        ];
    }

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchTextColumns(): array
    {
        return ['full_name_ti', 'full_name_payer', 'email_ti'];
    }

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchCodeColumns(): array
    {
        return ['code', 'code_agency', 'owner_code'];
    }

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchDocumentColumns(): array
    {
        return ['nro_identificacion_ti', 'nro_identificacion_payer'];
    }

    protected static function businessGlobalSearchExtraConstraints(Builder $query, string $term): void
    {
        $query->orWhereHas('affiliates', function (Builder $affiliates) use ($term): void {
            $affiliates->where(function (Builder $inner) use ($term): void {
                BusinessGlobalSearch::applyTextOrCodeMatch($inner, ['full_name'], $term);
                BusinessGlobalSearch::applyNormalizedDocumentMatch($inner, ['nro_identificacion', 'document'], $term);
            });
        });
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        if (! $record instanceof Affiliation) {
            return [];
        }

        return [
            'Titular' => filled($record->full_name_ti) ? (string) $record->full_name_ti : '—',
            'CI/RIF titular' => filled($record->nro_identificacion_ti) ? (string) $record->nro_identificacion_ti : '—',
            'Agencia' => filled($record->code_agency) ? (string) $record->code_agency : '—',
            'Estatus' => filled($record->status) ? (string) $record->status : '—',
        ];
    }

    /**
     * Muestra un badge con la palabra NEW y el conteo de afiliados
     * con estatus 'ACTIVA' registrados el día de hoy.
     */
    public static function getNavigationBadge(): ?string
    {
        $todayCount = Cache::remember(
            'business.affiliation_navigation_badge.'.Carbon::today()->toDateString(),
            now()->addSeconds(60),
            fn (): int => (int) static::getModel()::query()
                ->where('status', 'ACTIVA')
                ->whereDate('created_at', Carbon::today())
                ->count(),
        );

        return $todayCount > 0 ? "NUEVO {$todayCount}" : null;
    }

    /**
     * Color personalizado para el badge (Verde iOS).
     */
    public static function getNavigationBadgeColor(): ?string
    {

        return 'verdeApple';
    }

    public static function form(Schema $schema): Schema
    {
        return AffiliationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AffiliationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AffiliatesRelationManager::class,
            PaidMembershipsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliations::route('/'),
            'create' => CreateAffiliation::route('/create'),
            'view' => ViewAffiliation::route('/{record}'),
            'edit' => EditAffiliation::route('/{record}/edit'),
        ];
    }
}
