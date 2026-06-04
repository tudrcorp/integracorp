<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class GlobalSearchSupplierQuery
{
    /**
     * Columnas mínimas para resultados de búsqueda global (sin relaciones).
     *
     * @return list<string>
     */
    public static function selectColumns(Model $model): array
    {
        $table = $model->getTable();

        return [
            "{$table}.id",
            "{$table}.name",
            "{$table}.rif",
            "{$table}.razon_social",
            "{$table}.status_sistema",
            "{$table}.status_convenio",
            "{$table}.correo_principal",
            "{$table}.personal_phone",
            "{$table}.local_phone",
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function baseQuery(Builder $query): Builder
    {
        /** @var TModel $model */
        $model = $query->getModel();

        return $query->select(self::selectColumns($model));
    }

    /**
     * Búsqueda por nombre, razón social y RIF (con y sin guiones/espacios).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function applyToQuery(Builder $query, string $search): Builder
    {
        $term = trim($search);

        if ($term === '') {
            return $query->whereRaw('0 = 1');
        }

        $table = $query->getModel()->getTable();
        $like = '%'.$term.'%';
        $normalizedRif = self::normalizeRif($term);
        $normalizedLike = $normalizedRif !== '' ? '%'.$normalizedRif.'%' : null;

        return $query->where(function (Builder $inner) use ($table, $like, $normalizedLike): void {
            $inner
                ->where("{$table}.name", 'like', $like)
                ->orWhere("{$table}.razon_social", 'like', $like)
                ->orWhere("{$table}.rif", 'like', $like);

            if ($normalizedLike !== null) {
                $inner->orWhereRaw(
                    "REPLACE(REPLACE(REPLACE(UPPER({$table}.rif), '-', ''), ' ', ''), '.', '') LIKE ?",
                    [$normalizedLike],
                );
            }
        });
    }

    public static function normalizeRif(string $value): string
    {
        $normalized = preg_replace('/[\s\-\.]+/u', '', mb_strtoupper(trim($value)));

        return is_string($normalized) ? $normalized : '';
    }
}
