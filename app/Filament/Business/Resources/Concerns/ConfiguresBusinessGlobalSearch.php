<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Concerns;

use App\Support\Filament\BusinessGlobalSearch;
use Filament\Actions\Action;
use Filament\GlobalSearch\GlobalSearchResult;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait ConfiguresBusinessGlobalSearch
{
    public static function shouldSplitGlobalSearchTerms(): bool
    {
        return false;
    }

    /**
     * @return list<string>
     */
    abstract protected static function businessGlobalSearchSelectColumns(): array;

    /**
     * @return list<string>
     */
    abstract protected static function businessGlobalSearchTextColumns(): array;

    /**
     * @return list<string>
     */
    abstract protected static function businessGlobalSearchCodeColumns(): array;

    /**
     * @return list<string>
     */
    abstract protected static function businessGlobalSearchDocumentColumns(): array;

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return array_values(array_unique(array_merge(
            static::businessGlobalSearchTextColumns(),
            static::businessGlobalSearchCodeColumns(),
            static::businessGlobalSearchDocumentColumns(),
        )));
    }

    /**
     * Hook opcional para whereHas / predicados extra dentro del OR.
     */
    protected static function businessGlobalSearchExtraConstraints(Builder $query, string $term): void
    {
        //
    }

    public static function getGlobalSearchResults(string $search): Collection
    {
        $search = trim($search);

        if ($search === '') {
            return collect();
        }

        $table = (new (static::getModel()))->getTable();

        $qualify = static function (array $columns) use ($table): array {
            return array_map(
                static fn (string $column): string => str_contains($column, '.') ? $column : "{$table}.{$column}",
                $columns,
            );
        };

        $query = static::getModel()::query()
            ->select($qualify(static::businessGlobalSearchSelectColumns()));

        BusinessGlobalSearch::constrain(
            query: $query,
            search: $search,
            textColumns: $qualify(static::businessGlobalSearchTextColumns()),
            codeColumns: $qualify(static::businessGlobalSearchCodeColumns()),
            documentColumns: $qualify(static::businessGlobalSearchDocumentColumns()),
            extra: static function (Builder $inner) use ($search): void {
                static::businessGlobalSearchExtraConstraints($inner, $search);
            },
        );

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
                        fn (Action $action) => $action->hasRecord() ? $action : $action->record($record),
                        static::getGlobalSearchResultActions($record),
                    ),
                );
            })
            ->filter();
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        $titleAttribute = static::getRecordTitleAttribute();

        if (filled($titleAttribute) && filled($record->getAttribute($titleAttribute))) {
            return (string) $record->getAttribute($titleAttribute);
        }

        return static::getRecordTitle($record);
    }
}
