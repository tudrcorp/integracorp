<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\AffiliateCorporates;

use App\Filament\Business\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Filament\Business\Resources\Concerns\ConfiguresBusinessGlobalSearch;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\AffiliateCorporate;
use Filament\Resources\Resource;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class AffiliateCorporateResource extends Resource
{
    use AuthorizesDepartmentNavigation;
    use ConfiguresBusinessGlobalSearch;

    protected static ?string $model = AffiliateCorporate::class;

    protected static ?string $modelLabel = 'afiliado corporativo';

    protected static ?string $pluralModelLabel = 'Afiliados corporativos';

    protected static ?string $recordTitleAttribute = 'first_name';

    protected static int $globalSearchResultsLimit = 8;

    protected static ?int $globalSearchSort = 41;

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchSelectColumns(): array
    {
        return [
            'id',
            'affiliation_corporate_id',
            'first_name',
            'last_name',
            'nro_identificacion',
            'status',
        ];
    }

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchTextColumns(): array
    {
        return ['first_name', 'last_name'];
    }

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchCodeColumns(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchDocumentColumns(): array
    {
        return ['nro_identificacion'];
    }

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchEagerLoads(): array
    {
        return ['affiliationCorporate'];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
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

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        if ($record === null) {
            return parent::getRecordTitle(null);
        }

        if ($record instanceof AffiliateCorporate) {
            $name = self::corporateAffiliateDisplayName($record);

            if ($name !== '') {
                return $name;
            }

            if (filled($record->nro_identificacion)) {
                return (string) $record->nro_identificacion;
            }
        }

        return parent::getRecordTitle($record);
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        if (! $record instanceof AffiliateCorporate) {
            return parent::getGlobalSearchResultTitle($record);
        }

        $name = self::corporateAffiliateDisplayName($record);

        if ($name !== '') {
            return $name;
        }

        if (filled($record->nro_identificacion)) {
            return (string) $record->nro_identificacion;
        }

        return 'Afiliado corporativo';
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        if (! $record instanceof AffiliateCorporate) {
            return [];
        }

        $affiliation = $record->affiliationCorporate;

        return [
            'Cédula' => filled($record->nro_identificacion) ? (string) $record->nro_identificacion : '—',
            'Empresa' => filled($affiliation?->name_corporate) ? (string) $affiliation->name_corporate : '—',
            'Afiliación' => filled($affiliation?->code) ? (string) $affiliation->code : '—',
            'Estatus' => filled($record->status) ? (string) $record->status : '—',
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        if (! $record instanceof AffiliateCorporate || blank($record->affiliation_corporate_id)) {
            return null;
        }

        if (! AffiliationCorporateResource::canAccess()) {
            return null;
        }

        return AffiliationCorporateResource::getUrl('view', ['record' => $record->affiliation_corporate_id]);
    }

    private static function corporateAffiliateDisplayName(AffiliateCorporate $record): string
    {
        return trim(implode(' ', array_filter([
            filled($record->first_name) ? (string) $record->first_name : null,
            filled($record->last_name) ? (string) $record->last_name : null,
        ])));
    }
}
