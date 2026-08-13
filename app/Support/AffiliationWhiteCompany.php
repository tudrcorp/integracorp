<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use App\Models\WhiteCompany;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class AffiliationWhiteCompany
{
    /** @var list<string> */
    public const RECORD_ROW_CLASSES = [
        'fi-affiliation-white-company',
    ];

    public static function belongsToWhiteCompany(Model $record): bool
    {
        if ($record->relationLoaded('whiteCompanyUser')) {
            return filled($record->getRelation('whiteCompanyUser')?->white_company_id);
        }

        return filled($record->getAttribute('white_company_id'));
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

        return $query->whereIn(
            $table.'.code_agency',
            User::query()
                ->select('code_agency')
                ->where('white_company_id', $whiteCompanyId)
                ->whereNotNull('code_agency')
        );
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
