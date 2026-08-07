<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Illuminate\Database\Eloquent\Builder;

/**
 * Utilidades de búsqueda global del panel Business: términos normalizados y
 * predicados SQL orientados a índices (prefijo en códigos, documento normalizado).
 */
final class BusinessGlobalSearch
{
    public static function normalizeDocument(string $value): string
    {
        $normalized = preg_replace('/[\s\-\.]+/u', '', mb_strtoupper(trim($value)));

        return is_string($normalized) ? $normalized : '';
    }

    public static function looksLikeCode(string $term): bool
    {
        $term = trim($term);

        if ($term === '' || str_contains($term, ' ')) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9\-_\/\.]{1,}$/', $term);
    }

    public static function looksLikeDocument(string $term): bool
    {
        $normalized = self::normalizeDocument($term);

        if ($normalized === '') {
            return false;
        }

        return (bool) preg_match('/^[JEVPjevP]?\d{5,}$/', $normalized);
    }

    /**
     * Extrae el id numérico de códigos tipo AGT-000123 / AGT000123.
     */
    public static function extractAgentDisplayCodeId(string $term): ?int
    {
        if (! preg_match('/^AGT-?0*(\d+)$/i', trim($term), $matches)) {
            return null;
        }

        $id = (int) $matches[1];

        return $id > 0 ? $id : null;
    }

    /**
     * @param  list<string>  $columns  Columnas calificadas (table.column) o no.
     */
    public static function applyTextOrCodeMatch(Builder $query, array $columns, string $term): void
    {
        $term = trim($term);

        if ($term === '' || $columns === []) {
            return;
        }

        $preferPrefix = self::looksLikeCode($term);
        $like = $preferPrefix ? $term.'%' : '%'.$term.'%';

        foreach ($columns as $column) {
            $query->orWhere($column, 'like', $like);
        }
    }

    /**
     * Coincide RIF/CI con y sin guiones, puntos o espacios.
     *
     * @param  list<string>  $columns
     */
    public static function applyNormalizedDocumentMatch(Builder $query, array $columns, string $term): void
    {
        $normalized = self::normalizeDocument($term);

        if ($normalized === '' || $columns === []) {
            return;
        }

        $like = '%'.$normalized.'%';

        foreach ($columns as $column) {
            $query->orWhere($column, 'like', '%'.trim($term).'%');
            $query->orWhereRaw(
                'REPLACE(REPLACE(REPLACE(UPPER('.$column."), '-', ''), ' ', ''), '.', '') LIKE ?",
                [$like],
            );
        }
    }

    /**
     * Aplica el bloque OR principal de búsqueda para un recurso Business.
     *
     * @param  list<string>  $textColumns
     * @param  list<string>  $codeColumns
     * @param  list<string>  $documentColumns
     * @param  (callable(Builder): void)|null  $extra
     */
    public static function constrain(
        Builder $query,
        string $search,
        array $textColumns = [],
        array $codeColumns = [],
        array $documentColumns = [],
        ?callable $extra = null,
    ): Builder {
        $term = trim($search);

        if ($term === '') {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function (Builder $inner) use ($term, $textColumns, $codeColumns, $documentColumns, $extra): void {
            self::applyTextOrCodeMatch($inner, $textColumns, $term);
            self::applyTextOrCodeMatch($inner, $codeColumns, $term);

            if (self::looksLikeDocument($term) || self::normalizeDocument($term) !== '') {
                self::applyNormalizedDocumentMatch($inner, $documentColumns, $term);
            }

            if ($extra !== null) {
                $extra($inner);
            }
        });
    }
}
