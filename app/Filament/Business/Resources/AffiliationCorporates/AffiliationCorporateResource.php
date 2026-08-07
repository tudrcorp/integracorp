<?php

namespace App\Filament\Business\Resources\AffiliationCorporates;

use App\Filament\Business\Resources\AffiliationCorporates\Pages\CreateAffiliationCorporate;
use App\Filament\Business\Resources\AffiliationCorporates\Pages\EditAffiliationCorporate;
use App\Filament\Business\Resources\AffiliationCorporates\Pages\ListAffiliationCorporates;
use App\Filament\Business\Resources\AffiliationCorporates\Pages\ViewAffiliationCorporate;
use App\Filament\Business\Resources\AffiliationCorporates\RelationManagers\AffiliationCorporatePlansRelationManager;
use App\Filament\Business\Resources\AffiliationCorporates\RelationManagers\CorporateAffiliatesRelationManager;
use App\Filament\Business\Resources\AffiliationCorporates\RelationManagers\PaidMembershipCorporatesRelationManager;
use App\Filament\Business\Resources\AffiliationCorporates\RelationManagers\StatusLogCorporateAffiliationsRelationManager;
use App\Filament\Business\Resources\AffiliationCorporates\Schemas\AffiliationCorporateForm;
use App\Filament\Business\Resources\AffiliationCorporates\Schemas\AffiliationCorporateInfolist;
use App\Filament\Business\Resources\AffiliationCorporates\Tables\AffiliationCorporatesTable;
use App\Filament\Business\Resources\Concerns\ConfiguresBusinessGlobalSearch;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\AffiliationCorporate;
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

class AffiliationCorporateResource extends Resource
{
    use AuthorizesDepartmentNavigation;
    use ConfiguresBusinessGlobalSearch;

    protected static ?string $model = AffiliationCorporate::class;

    protected static ?string $navigationLabel = 'Corporativas';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|UnitEnum|null $navigationGroup = 'AFILIACIONES';

    protected static ?string $recordTitleAttribute = 'name_corporate';

    protected static int $globalSearchResultsLimit = 8;

    protected static ?int $globalSearchSort = 40;

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchSelectColumns(): array
    {
        return [
            'id',
            'code',
            'name_corporate',
            'rif',
            'email',
            'full_name_contact',
            'nro_identificacion_contact',
            'status',
            'code_agency',
        ];
    }

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchTextColumns(): array
    {
        return ['name_corporate', 'full_name_contact', 'email'];
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
        return ['rif', 'nro_identificacion_contact'];
    }

    protected static function businessGlobalSearchExtraConstraints(Builder $query, string $term): void
    {
        $query->orWhereHas('corporateAffiliates', function (Builder $affiliates) use ($term): void {
            $affiliates->where(function (Builder $inner) use ($term): void {
                BusinessGlobalSearch::applyTextOrCodeMatch($inner, ['first_name', 'last_name'], $term);
                BusinessGlobalSearch::applyNormalizedDocumentMatch($inner, ['nro_identificacion', 'document'], $term);
            });
        });
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        if (! $record instanceof AffiliationCorporate) {
            return [];
        }

        return [
            'Código' => filled($record->code) ? (string) $record->code : '—',
            'RIF' => filled($record->rif) ? (string) $record->rif : '—',
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
            'business.affiliation_corporate_navigation_badge.'.Carbon::today()->toDateString(),
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
        return AffiliationCorporateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AffiliationCorporateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliationCorporatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AffiliationCorporatePlansRelationManager::class,
            CorporateAffiliatesRelationManager::class,
            PaidMembershipCorporatesRelationManager::class,
            StatusLogCorporateAffiliationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliationCorporates::route('/'),
            'create' => CreateAffiliationCorporate::route('/create'),
            'view' => ViewAffiliationCorporate::route('/{record}'),
            'edit' => EditAffiliationCorporate::route('/{record}/edit'),
        ];
    }
}
