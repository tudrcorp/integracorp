<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\WhiteCompany;
use App\Support\WhiteCompanies\WhiteCompanyOwnership;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

final class AffiliationWhiteCompany
{
    /** @var list<string> */
    public const RECORD_ROW_CLASSES = [
        'fi-affiliation-white-company',
    ];

    /** @var array<string, bool> */
    private static array $columnCache = [];

    public static function belongsToWhiteCompany(Model $record): bool
    {
        if ($record instanceof Affiliation || $record instanceof AffiliationCorporate) {
            return WhiteCompanyOwnership::isAllied($record);
        }

        if (filled($record->getAttribute('white_company_id'))) {
            return true;
        }

        if ($record->relationLoaded('whiteCompanyUser')) {
            return filled($record->getRelation('whiteCompanyUser')?->white_company_id);
        }

        return WhiteCompanyOwnership::companyIdForAgencyCode(
            is_string($record->getAttribute('code_agency')) ? $record->getAttribute('code_agency') : null
        ) !== null;
    }

    public static function belongsToAlliedCompany(Affiliation|AffiliationCorporate $affiliation): bool
    {
        return WhiteCompanyOwnership::isAllied($affiliation);
    }

    /**
     * @param  list<string>  $fallback
     * @return list<string>
     */
    public static function recordRowClasses(Model $record, array $fallback = []): array
    {
        if (! self::belongsToWhiteCompany($record)) {
            return $fallback;
        }

        return self::RECORD_ROW_CLASSES;
    }

    public static function constrainQuery(Builder $query, mixed $whiteCompanyId): Builder
    {
        if (blank($whiteCompanyId)) {
            return $query;
        }

        $table = $query->getModel()->getTable();
        $codes = WhiteCompanyOwnership::agencyCodesFor(is_numeric($whiteCompanyId) ? (int) $whiteCompanyId : null);
        $hasDirectColumn = self::hasWhiteCompanyColumn($table);

        if (! $hasDirectColumn && $codes === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $builder) use ($table, $whiteCompanyId, $codes, $hasDirectColumn): void {
            if ($hasDirectColumn) {
                $builder->where($table.'.white_company_id', $whiteCompanyId);
            }

            if ($codes !== []) {
                $hasDirectColumn
                    ? $builder->orWhereIn($table.'.code_agency', $codes)
                    : $builder->whereIn($table.'.code_agency', $codes);
            }
        });
    }

    private static function hasWhiteCompanyColumn(string $table): bool
    {
        return self::$columnCache[$table] ??= Schema::hasColumn($table, 'white_company_id');
    }

    public static function tableFilter(): SelectFilter
    {
        return SelectFilter::make('white_company_id')
            ->label('Empresa aliada')
            ->options(fn (): array => WhiteCompany::query()->orderBy('name')->pluck('name', 'id')->all())
            ->query(fn (Builder $query, array $data): Builder => self::constrainQuery($query, $data['value'] ?? null))
            ->searchable()
            ->preload();
    }
}
