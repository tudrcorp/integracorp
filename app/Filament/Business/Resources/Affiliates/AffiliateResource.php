<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Affiliates;

use App\Filament\Business\Resources\Affiliations\AffiliationResource;
use App\Filament\Business\Resources\Concerns\ConfiguresBusinessGlobalSearch;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\Affiliate;
use Filament\Resources\Resource;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class AffiliateResource extends Resource
{
    use AuthorizesDepartmentNavigation;
    use ConfiguresBusinessGlobalSearch;

    protected static ?string $model = Affiliate::class;

    protected static ?string $modelLabel = 'afiliado individual';

    protected static ?string $pluralModelLabel = 'Afiliados individuales';

    protected static ?string $recordTitleAttribute = 'full_name';

    protected static int $globalSearchResultsLimit = 8;

    protected static ?int $globalSearchSort = 31;

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchSelectColumns(): array
    {
        return [
            'id',
            'affiliation_id',
            'full_name',
            'nro_identificacion',
            'relationship',
            'status',
        ];
    }

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchTextColumns(): array
    {
        return ['full_name'];
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
        return ['affiliation'];
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

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        if (! $record instanceof Affiliate) {
            return parent::getGlobalSearchResultTitle($record);
        }

        if (filled($record->full_name)) {
            return (string) $record->full_name;
        }

        if (filled($record->nro_identificacion)) {
            return (string) $record->nro_identificacion;
        }

        return 'Afiliado individual';
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        if (! $record instanceof Affiliate) {
            return [];
        }

        return [
            'Cédula' => filled($record->nro_identificacion) ? (string) $record->nro_identificacion : '—',
            'Afiliación' => filled($record->affiliation?->code) ? (string) $record->affiliation->code : '—',
            'Parentesco' => filled($record->relationship) ? (string) $record->relationship : '—',
            'Estatus' => filled($record->status) ? (string) $record->status : '—',
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        if (! $record instanceof Affiliate || blank($record->affiliation_id)) {
            return null;
        }

        if (! AffiliationResource::canAccess()) {
            return null;
        }

        return AffiliationResource::getUrl('view', ['record' => $record->affiliation_id]);
    }
}
