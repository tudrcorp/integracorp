<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\Concerns;

use App\Support\Filament\GlobalSearchSupplierQuery;
use App\Support\Filament\GlobalSearchSupplierResultDetails;
use App\Support\Filament\GlobalSearchSupplierResultTitle;
use Filament\Actions\Action;
use Filament\GlobalSearch\GlobalSearchResult;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait ConfiguresOperationsSupplierGlobalSearch
{
    public static function shouldSplitGlobalSearchTerms(): bool
    {
        return false;
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }

    abstract protected static function operationsSupplierGlobalSearchKind(): string;

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected static function operationsSupplierGlobalSearchBaseQuery(Builder $query): Builder
    {
        return GlobalSearchSupplierQuery::baseQuery($query);
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        return GlobalSearchSupplierResultTitle::html($record, static::operationsSupplierGlobalSearchKind());
    }

    /**
     * @return array<string, Htmlable|string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return GlobalSearchSupplierResultDetails::forRecord($record);
    }

    public static function getGlobalSearchResults(string $search): Collection
    {
        $search = trim($search);

        if ($search === '') {
            return collect();
        }

        $query = static::getGlobalSearchEloquentQuery();

        GlobalSearchSupplierQuery::applyToQuery($query, $search);

        return $query
            ->limit(static::getGlobalSearchResultsLimit())
            ->get()
            ->map(function (Model $record): ?GlobalSearchResult {
                $url = static::getGlobalSearchResultUrl($record);

                if (blank($url)) {
                    return null;
                }

                return new GlobalSearchResult(
                    title: static::getGlobalSearchResultTitle($record),
                    url: $url,
                    details: static::getGlobalSearchResultDetails($record),
                    actions: array_map(
                        fn (Action $action): Action => $action->hasRecord() ? $action : $action->record($record),
                        static::getGlobalSearchResultActions($record),
                    ),
                );
            })
            ->filter();
    }
}
